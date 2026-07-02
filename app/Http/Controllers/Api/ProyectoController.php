<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incidencia;
use App\Models\Proyecto;
use App\Models\ReporteDiario;
use App\Models\Usuario;
use App\Http\Requests\ProyectoStoreRequest;
use App\Http\Requests\ProyectoUpdateRequest;
use App\Http\Requests\AsignacionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Controlador de proyectos del sistema SIGO.
 *
 * Expone los endpoints para consultar, crear, actualizar y desactivar proyectos,
 * así como la asignación de personal y la visualización de KPIs y actividad.
 */
class ProyectoController extends Controller
{
    // =========================================================================
    // INDEX — Listar proyectos con filtros
    // =========================================================================
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $empresaId = $user->empresa_id;

            // Gerentes y administradores ven todos los proyectos de su empresa.
            // Supervisores e ingenieros solo ven proyectos asignados activamente.
            if ($user->tieneRol(['gerente', 'administrador'])) {
                $query = Proyecto::where('empresa_id', $empresaId);
            } else {
                $query = $user->proyectosActivos();
            }

            // Eager load counts
            $query->withCount([
                'reportesDiarios as total_reportes',
                'incidencias as total_incidencias',
                'incidencias as incidencias_abiertas' => fn ($q) =>
                    $q->where('estado', '!=', Incidencia::ESTADO_CERRADA),
            ]);

            // Filtro por estado
            if ($request->filled('estado')) {
                $query->where('proyectos.estado', $request->estado);
            }

            // Filtro por cliente_id
            if ($request->filled('cliente_id')) {
                $query->where('proyectos.cliente_id', $request->cliente_id);
            }

            // Búsqueda por nombre o código
            if ($request->filled('busqueda')) {
                $termino = $request->busqueda;
                $query->where(function ($q) use ($termino): void {
                    $q->where('proyectos.nombre', 'ILIKE', "%{$termino}%")
                      ->orWhere('proyectos.codigo', 'ILIKE', "%{$termino}%");
                });
            }

            // Ordenar por fecha_fin ascendente
            $query->orderBy('proyectos.fecha_fin', 'asc');

            // Paginar (10 por página)
            $paginado = $query->paginate(10);

            // Formatear respuesta
            $proyectos = $paginado->getCollection()->map(function (Proyecto $proyecto) use ($user): array {
                $ultimoReporte = $proyecto->reportesDiarios()->orderByDesc('fecha_reporte')->first();

                // Buscar el pivot en proyecto_usuario para el usuario autenticado
                $pivot = $proyecto->usuariosActivos()->where('usuarios.id', $user->id)->first()?->pivot;
                $proyecto->mi_rol = $pivot->rol_en_proyecto ?? null;

                return [
                    'id'        => $proyecto->id,
                    'codigo'    => $proyecto->codigo,
                    'nombre'    => $proyecto->nombre,
                    'descripcion' => $proyecto->descripcion,
                    'ubicacion' => $proyecto->ubicacion,
                    'estado'    => $proyecto->estado,
                    'avance'    => (float) $proyecto->avance,
                    'fecha_inicio' => $proyecto->fecha_inicio ? $proyecto->fecha_inicio->format('Y-m-d') : null,
                    'fecha_fin'    => $proyecto->fecha_fin ? $proyecto->fecha_fin->format('Y-m-d') : null,
                    'presupuesto'  => $proyecto->presupuesto ? (float) $proyecto->presupuesto : null,
                    'cliente'   => $proyecto->cliente ? [
                        'id'     => $proyecto->cliente->id,
                        'nombre' => $proyecto->cliente->razon_social
                    ] : null,
                    'mi_rol'    => $proyecto->mi_rol,
                    'kpis' => [
                        'total_reportes'       => (int) $proyecto->total_reportes,
                        'total_incidencias'    => (int) $proyecto->total_incidencias,
                        'incidencias_abiertas' => (int) $proyecto->incidencias_abiertas,
                        'ultimo_reporte'       => $ultimoReporte ? [
                            'fecha_reporte' => $ultimoReporte->fecha_reporte ? Carbon::parse($ultimoReporte->fecha_reporte)->format('Y-m-d') : null,
                            'avance'        => (float) $ultimoReporte->avance,
                        ] : null,
                    ],
                    'personal_asignado' => (int) $proyecto->usuariosActivos()->count(),
                ];
            });

            $paginado->setCollection($proyectos);

            return response()->json([
                'status'  => 'success',
                'message' => 'Proyectos obtenidos correctamente.',
                'data'    => $paginado,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener los proyectos.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    // =========================================================================
    // STORE — Crear nuevo proyecto con asignaciones
    // =========================================================================
    public function store(ProyectoStoreRequest $request): JsonResponse
    {
        // 1. Autorización de rol
        if (! $request->user()->tieneRol(['gerente', 'administrador'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para crear proyectos.',
            ], 403);
        }

        try {
            $proyecto = DB::transaction(function () use ($request) {
                // Generar código si no se envió
                $codigo = $request->input('codigo') ?? Proyecto::generarCodigo();

                // Crear proyecto
                $proyecto = Proyecto::create([
                    'codigo'         => $codigo,
                    'nombre'         => $request->nombre,
                    'descripcion'    => $request->descripcion,
                    'ubicacion'      => $request->ubicacion,
                    'latitud'        => $request->latitud,
                    'longitud'       => $request->longitud,
                    'fecha_inicio'   => $request->fecha_inicio,
                    'fecha_fin'      => $request->fecha_fin,
                    'presupuesto'    => $request->presupuesto,
                    'avance'         => $request->avance ?? 0,
                    'estado'         => $request->estado ?? 'planeado',
                    'cliente_id'     => $request->cliente_id,
                    'empresa_id'     => $request->user()->empresa_id,
                    'imagen_portada' => $request->imagen_portada,
                    'creado_por'     => $request->user()->id,
                ]);

                // Asignar personal si viene en la petición
                if ($request->filled('asignaciones')) {
                    foreach ($request->asignaciones as $asignacion) {
                        $usuario = Usuario::find($asignacion['usuario_id']);
                        if ($usuario && $usuario->activo) {
                            $proyecto->asignarUsuario($usuario->id, $asignacion['rol']);
                        }
                    }
                }

                return $proyecto;
            });

            // Cargar relaciones para la respuesta
            $proyecto->load(['cliente', 'usuariosActivos']);

            return response()->json([
                'status'  => 'success',
                'message' => 'Proyecto creado correctamente',
                'data'    => [
                    'id'       => $proyecto->id,
                    'codigo'   => $proyecto->codigo,
                    'nombre'   => $proyecto->nombre,
                    'cliente'  => $proyecto->cliente ? [
                        'id'     => $proyecto->cliente->id,
                        'nombre' => $proyecto->cliente->razon_social
                    ] : null,
                    'usuarios' => $proyecto->usuariosActivos->map(fn ($u) => [
                        'id'     => $u->id,
                        'nombre' => $u->nombre,
                        'rol'    => $u->pivot->rol_en_proyecto
                    ])->values()
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al crear el proyecto.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    // =========================================================================
    // SHOW — Detalle de un proyecto
    // =========================================================================
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $proyecto = Proyecto::find($id);

            if (! $proyecto) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Proyecto no encontrado.',
                ], 404);
            }

            // Verificar acceso
            $user = $request->user();
            if ($user->tieneRol(['gerente', 'administrador'])) {
                $tieneAcceso = $proyecto->empresa_id === $user->empresa_id;
            } else {
                $tieneAcceso = $user->proyectosActivos()->where('proyectos.id', $id)->exists();
            }

            if (! $tieneAcceso) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No tienes acceso a este proyecto.',
                ], 403);
            }

            $proyecto->load([
                'cliente',
                'usuariosActivos' => fn ($q) =>
                    $q->select('usuarios.id', 'usuarios.nombre', 'usuarios.email', 'usuarios.telefono'),
            ]);

            // Determinar mi_rol para el detalle del proyecto
            $pivot = $proyecto->usuariosActivos()->where('usuarios.id', $user->id)->first()?->pivot;
            $proyecto->mi_rol = $pivot->rol_en_proyecto ?? null;

            return response()->json([
                'status'  => 'success',
                'message' => 'Proyecto obtenido correctamente',
                'data'    => [
                    'id'          => $proyecto->id,
                    'codigo'      => $proyecto->codigo,
                    'nombre'      => $proyecto->nombre,
                    'descripcion' => $proyecto->descripcion,
                    'ubicacion'   => $proyecto->ubicacion,
                    'latitud'     => $proyecto->latitud ? (float) $proyecto->latitud : null,
                    'longitud'    => $proyecto->longitud ? (float) $proyecto->longitud : null,
                    'fecha_inicio' => $proyecto->fecha_inicio ? $proyecto->fecha_inicio->format('Y-m-d') : null,
                    'fecha_fin'    => $proyecto->fecha_fin ? $proyecto->fecha_fin->format('Y-m-d') : null,
                    'presupuesto'  => $proyecto->presupuesto ? (float) $proyecto->presupuesto : null,
                    'avance'      => (float) $proyecto->avance,
                    'estado'      => $proyecto->estado,
                    'imagen_portada' => $proyecto->imagen_portada,
                    'cliente'     => $proyecto->cliente ? [
                        'id'     => $proyecto->cliente->id,
                        'nombre' => $proyecto->cliente->razon_social
                    ] : null,
                    'mi_rol'      => $proyecto->mi_rol,
                    'usuarios'    => $proyecto->usuariosActivos->map(fn ($u) => [
                        'id'       => $u->id,
                        'nombre'   => $u->nombre,
                        'email'    => $u->email,
                        'telefono' => $u->telefono,
                        'rol'      => $u->pivot->rol_en_proyecto
                    ])->values()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener el detalle del proyecto.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    // =========================================================================
    // UPDATE — Actualizar proyecto y asignaciones
    // =========================================================================
    public function update(ProyectoUpdateRequest $request, int $id): JsonResponse
    {
        if (! $request->user()->tieneRol(['gerente', 'administrador'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para editar proyectos.',
            ], 403);
        }

        try {
            $proyecto = Proyecto::find($id);

            if (! $proyecto || $proyecto->empresa_id !== $request->user()->empresa_id) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Proyecto no encontrado.',
                ], 404);
            }

            DB::transaction(function () use ($request, $proyecto) {
                $proyecto->update($request->only([
                    'nombre', 'descripcion', 'ubicacion', 'latitud', 'longitud',
                    'fecha_inicio', 'fecha_fin', 'presupuesto', 'avance', 'estado',
                    'cliente_id', 'imagen_portada'
                ]));

                // Si se envían asignaciones, actualizar (desactivar anteriores y asignar nuevas)
                if ($request->has('asignaciones')) {
                    // Desactivar todas las asignaciones activas actuales
                    $proyecto->usuariosActivos()->updateExistingPivot(
                        $proyecto->usuariosActivos->pluck('id'),
                        ['activo' => false]
                    );

                    // Agregar/reactivar las nuevas asignaciones
                    foreach ($request->asignaciones as $asignacion) {
                        $usuario = Usuario::find($asignacion['usuario_id']);
                        if ($usuario && $usuario->activo) {
                            $proyecto->asignarUsuario($usuario->id, $asignacion['rol']);
                        }
                    }
                }
            });

            $proyecto->load(['cliente', 'usuariosActivos']);

            return response()->json([
                'status'  => 'success',
                'message' => 'Proyecto actualizado correctamente',
                'data'    => [
                    'id'       => $proyecto->id,
                    'codigo'   => $proyecto->codigo,
                    'nombre'   => $proyecto->nombre,
                    'cliente'  => $proyecto->cliente ? [
                        'id'     => $proyecto->cliente->id,
                        'nombre' => $proyecto->cliente->razon_social
                    ] : null,
                    'usuarios' => $proyecto->usuariosActivos->map(fn ($u) => [
                        'id'     => $u->id,
                        'nombre' => $u->nombre,
                        'rol'    => $u->pivot->rol_en_proyecto
                    ])->values()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al actualizar el proyecto.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    // =========================================================================
    // DESTROY — Desactivar proyecto (Soft Delete)
    // =========================================================================
    public function destroy(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->tieneRol(['gerente', 'administrador'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para eliminar proyectos.',
            ], 403);
        }

        try {
            $proyecto = Proyecto::find($id);

            if (! $proyecto || $proyecto->empresa_id !== $request->user()->empresa_id) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Proyecto no encontrado.',
                ], 404);
            }

            $proyecto->delete(); // Soft delete

            return response()->json([
                'status'  => 'success',
                'message' => 'Proyecto desactivado correctamente.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al desactivar el proyecto.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    // =========================================================================
    // ASIGNAR USUARIO — Asignar usuario individual
    // =========================================================================
    public function asignarUsuario(AsignacionRequest $request, int $id): JsonResponse
    {
        if (! $request->user()->tieneRol(['gerente', 'administrador'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para asignar personal.',
            ], 403);
        }

        try {
            $proyecto = Proyecto::find($id);

            if (! $proyecto || $proyecto->empresa_id !== $request->user()->empresa_id) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Proyecto no encontrado.',
                ], 404);
            }

            $usuario = Usuario::find($request->usuario_id);

            if (! $usuario->activo) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'El usuario seleccionado está inactivo y no se puede asignar.',
                ], 422);
            }

            $proyecto->asignarUsuario($usuario->id, $request->rol);

            return response()->json([
                'status'  => 'success',
                'message' => 'Usuario asignado correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al asignar usuario.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    // =========================================================================
    // REMOVER USUARIO — Remover usuario individual
    // =========================================================================
    public function removerUsuario(Request $request, int $id, int $usuarioId): JsonResponse
    {
        if (! $request->user()->tieneRol(['gerente', 'administrador'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para remover personal.',
            ], 403);
        }

        try {
            $proyecto = Proyecto::find($id);

            if (! $proyecto || $proyecto->empresa_id !== $request->user()->empresa_id) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Proyecto no encontrado.',
                ], 404);
            }

            $removido = $proyecto->removerUsuario($usuarioId);

            if (! $removido) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'El usuario no está asignado activamente en este proyecto.',
                ], 422);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Usuario removido correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al remover usuario.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    // =========================================================================
    // USUARIOS DISPONIBLES — Listar usuarios de la empresa no asignados
    // =========================================================================
    public function usuariosDisponibles(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->tieneRol(['gerente', 'administrador'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No autorizado.',
            ], 403);
        }

        try {
            $proyecto = Proyecto::find($id);

            if (! $proyecto || $proyecto->empresa_id !== $request->user()->empresa_id) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Proyecto no encontrado.',
                ], 404);
            }

            // Obtener los IDs de usuarios asignados activamente
            $asignadosIds = $proyecto->usuariosActivos()->pluck('usuarios.id')->toArray();

            // Filtrar usuarios de la misma empresa, activos, que no estén asignados
            $usuarios = Usuario::where('empresa_id', $request->user()->empresa_id)
                ->where('activo', true)
                ->whereNotIn('id', $asignadosIds)
                ->orderBy('nombre', 'asc')
                ->get(['id', 'nombre', 'email', 'telefono']);

            return response()->json([
                'status'  => 'success',
                'data'    => $usuarios,
                'message' => 'Usuarios disponibles obtenidos correctamente.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener usuarios disponibles.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    // =========================================================================
    // KPIS — Obtener KPIs del proyecto
    // =========================================================================
    public function kpis(Request $request, int $id): JsonResponse
    {
        try {
            $proyecto = Proyecto::find($id);

            if (! $proyecto) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Proyecto no encontrado.',
                ], 404);
            }

            // Verificar acceso
            $user = $request->user();
            if ($user->tieneRol(['gerente', 'administrador'])) {
                $tieneAcceso = $proyecto->empresa_id === $user->empresa_id;
            } else {
                $tieneAcceso = $user->proyectosActivos()->where('proyectos.id', $id)->exists();
            }

            if (! $tieneAcceso) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No tienes acceso a este proyecto.',
                ], 403);
            }

            // Reportes
            $totalReportes = $proyecto->reportesDiarios()->count();

            // Incidencias
            $totalIncidencias = $proyecto->incidencias()->count();
            $incidenciasAbiertas = $proyecto->incidencias()->where('estado', '!=', Incidencia::ESTADO_CERRADA)->count();
            $incidenciasCerradas = $proyecto->incidencias()->where('estado', Incidencia::ESTADO_CERRADA)->count();

            // Tiempo / Fechas
            $hoy = Carbon::today();
            $fechaInicio = $proyecto->fecha_inicio ? Carbon::parse($proyecto->fecha_inicio) : null;
            $fechaFin = $proyecto->fecha_fin ? Carbon::parse($proyecto->fecha_fin) : null;

            $diasRestantes = 0;
            if ($fechaFin) {
                $diasRestantes = $hoy->greaterThan($fechaFin) ? 0 : $hoy->diffInDays($fechaFin);
            }

            return response()->json([
                'status'  => 'success',
                'data'    => [
                    'avance'               => (float) $proyecto->avance,
                    'total_incidencias'    => $totalIncidencias,
                    'incidencias_abiertas' => $incidenciasAbiertas,
                    'incidencias_cerradas' => $incidenciasCerradas,
                    'total_reportes'       => $totalReportes,
                    'presupuesto'          => $proyecto->presupuesto ? (float) $proyecto->presupuesto : null,
                    'fecha_inicio'         => $fechaInicio ? $fechaInicio->format('Y-m-d') : null,
                    'fecha_fin'            => $fechaFin ? $fechaFin->format('Y-m-d') : null,
                    'dias_restantes'       => $diasRestantes,
                ],
                'message' => 'KPIs obtenidos correctamente.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener los KPIs del proyecto.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    // =========================================================================
    // ACTIVIDAD — Feed de actividad reciente del proyecto (original)
    // =========================================================================
    public function actividad(Request $request, int $id): JsonResponse
    {
        try {
            $proyecto = Proyecto::find($id);

            if (! $proyecto) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Proyecto no encontrado.',
                ], 404);
            }

            // Verificar acceso
            $user = $request->user();
            if ($user->tieneRol(['gerente', 'administrador'])) {
                $tieneAcceso = $proyecto->empresa_id === $user->empresa_id;
            } else {
                $tieneAcceso = $user->proyectosActivos()->where('proyectos.id', $id)->exists();
            }

            if (! $tieneAcceso) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No tienes acceso a este proyecto.',
                ], 403);
            }

            // Obtener los últimos reportes
            $reportes = ReporteDiario::where('proyecto_id', $id)
                ->with(['usuario:id,nombre'])
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(fn ($reporte) => [
                    'tipo'        => 'reporte',
                    'titulo'      => 'Nuevo reporte de avance',
                    'descripcion' => sprintf(
                        'Se registró un avance del %s%% en el turno %s.',
                        number_format((float) $reporte->avance, 1),
                        $reporte->turno ?? 'sin especificar'
                    ),
                    'usuario'  => $reporte->usuario?->nombre ?? 'Usuario desconocido',
                    'fecha'    => $reporte->created_at,
                    '_sort_at' => $reporte->created_at,
                ]);

            // Obtener las últimas incidencias
            $incidencias = Incidencia::where('proyecto_id', $id)
                ->with(['reportante:id,nombre'])
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(fn ($incidencia) => [
                    'tipo'        => 'incidencia',
                    'titulo'      => 'Incidencia reportada',
                    'descripcion' => sprintf(
                        '%s — Severidad: %s. Estado actual: %s.',
                        $incidencia->titulo,
                        $incidencia->severidad,
                        $incidencia->estado
                    ),
                    'usuario'  => $incidencia->reportante?->nombre ?? 'Usuario desconocido',
                    'fecha'    => $incidencia->created_at,
                    '_sort_at' => $incidencia->created_at,
                ]);

            // Combinar y ordenar
            $actividades = $reportes
                ->concat($incidencias)
                ->sortByDesc('_sort_at')
                ->take(10)
                ->map(fn ($item) => [
                    'tipo'        => $item['tipo'],
                    'titulo'      => $item['titulo'],
                    'descripcion' => $item['descripcion'],
                    'usuario'     => $item['usuario'],
                    'fecha'       => $item['fecha'],
                ])
                ->values();

            return response()->json([
                'status'  => 'success',
                'message' => 'Actividad reciente obtenida correctamente.',
                'data'    => [
                    'proyecto_id' => $id,
                    'actividades' => $actividades,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener la actividad del proyecto.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }
}
