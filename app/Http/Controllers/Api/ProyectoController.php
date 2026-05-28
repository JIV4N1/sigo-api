<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incidencia;
use App\Models\Proyecto;
use App\Models\ReporteDiario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Controlador de proyectos del sistema SIGO.
 *
 * Expone los endpoints para consultar proyectos asignados al usuario
 * autenticado, el detalle de cada proyecto con sus KPIs y la actividad
 * reciente combinando reportes e incidencias.
 */
class ProyectoController extends Controller
{
    // =========================================================================
    // INDEX — Listado paginado de proyectos asignados
    // =========================================================================

    /**
     * Retorna los proyectos asignados al usuario autenticado.
     *
     * Soporta filtro opcional por estado (?estado=a_tiempo) y búsqueda
     * por nombre o código (?busqueda=torre). Los resultados se ordenan
     * por fecha_fin ascendente (los más próximos a vencer primero) y se
     * paginan en grupos de 10.
     *
     * Para cada proyecto se incluyen KPIs rápidos calculados con conteos
     * eficientes (withCount) y el último reporte de avance.
     *
     * @param  Request  $request  Petición autenticada con Sanctum.
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // 1. Construir la consulta base sobre los proyectos del usuario autenticado.
            //    Se usan withCount para evitar N+1 al calcular los totales.
            $query = $request->user()
                ->proyectos()
                ->withCount([
                    // Total de reportes diarios registrados en el proyecto
                    'reportesDiarios as total_reportes',
                    // Total de incidencias (abiertas y cerradas)
                    'incidencias as total_incidencias',
                    // Solo incidencias que no han sido cerradas aún
                    'incidencias as incidencias_abiertas' => fn ($q) =>
                        $q->where('estado', '!=', Incidencia::ESTADO_CERRADA),
                ])
                ->with([
                    // El último reporte del proyecto (fecha y avance)
                    'reportesDiarios' => fn ($q) =>
                        $q->select('proyecto_id', 'fecha_reporte', 'avance')
                          ->orderByDesc('fecha_reporte')
                          ->limit(1),
                ]);

            // 2. Filtro opcional por estado del proyecto
            if ($request->filled('estado')) {
                $query->where('proyectos.estado', $request->estado);
            }

            // 3. Búsqueda por nombre o código (insensible a mayúsculas/minúsculas)
            if ($request->filled('busqueda')) {
                $termino = $request->busqueda;
                $query->where(function ($q) use ($termino): void {
                    $q->where('proyectos.nombre', 'ILIKE', "%{$termino}%")
                      ->orWhere('proyectos.codigo', 'ILIKE', "%{$termino}%");
                });
            }

            // 4. Ordenar por fecha_fin ascendente: los que vencen antes, primero
            $query->orderBy('proyectos.fecha_fin', 'asc');

            // 5. Paginar (10 resultados por página)
            $paginado = $query->paginate(10);

            // 6. Transformar cada proyecto al formato de respuesta esperado
            $proyectos = $paginado->getCollection()->map(function (Proyecto $proyecto): array {
                // El último reporte viene precargado por el eager loading
                $ultimoReporte = $proyecto->reportesDiarios->first();

                return [
                    'id'        => $proyecto->id,
                    'codigo'    => $proyecto->codigo,
                    'nombre'    => $proyecto->nombre,
                    'ubicacion' => $proyecto->ubicacion,
                    'estado'    => $proyecto->estado,
                    'avance'    => $proyecto->avance,

                    // KPIs rápidos calculados con withCount (sin subconsultas adicionales)
                    'kpis' => [
                        'total_reportes'       => $proyecto->total_reportes,
                        'total_incidencias'    => $proyecto->total_incidencias,
                        'incidencias_abiertas' => $proyecto->incidencias_abiertas,
                        'ultimo_reporte'       => $ultimoReporte ? [
                            'fecha_reporte' => $ultimoReporte->fecha_reporte,
                            'avance'        => $ultimoReporte->avance,
                        ] : null,
                    ],

                    // Conteo del personal asignado al proyecto
                    'personal_asignado' => $proyecto->usuarios_count
                        ?? $proyecto->usuarios()->count(),
                ];
            });

            // 7. Reconstruir la paginación con los datos transformados
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
    // SHOW — Detalle completo de un proyecto
    // =========================================================================

    /**
     * Retorna el detalle completo de un proyecto, incluyendo KPIs calculados,
     * personal asignado, últimos 5 reportes y últimas 5 incidencias.
     *
     * Verifica que el usuario autenticado tenga acceso al proyecto antes de
     * devolver cualquier información.
     *
     * @param  Request  $request  Petición autenticada.
     * @param  int      $id       ID del proyecto a consultar.
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            // 1. Verificar que el proyecto exista
            $proyecto = Proyecto::find($id);

            if (! $proyecto) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Proyecto no encontrado.',
                ], 404);
            }

            // 2. Verificar que el usuario autenticado esté asignado al proyecto
            $tieneAcceso = $request->user()
                ->proyectos()
                ->where('proyectos.id', $id)
                ->exists();

            if (! $tieneAcceso) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No tienes acceso a este proyecto.',
                ], 403);
            }

            // 3. Cargar el proyecto con todas sus relaciones necesarias
            $proyecto->load([
                'cliente',
                // Personal asignado con su rol en el proyecto (del pivot)
                'usuarios' => fn ($q) =>
                    $q->select('usuarios.id', 'usuarios.nombre', 'usuarios.email', 'usuarios.telefono'),

                // Últimos 5 reportes con el nombre del usuario que lo elaboró
                'reportesDiarios' => fn ($q) =>
                    $q->select('reportes_diarios.id', 'proyecto_id', 'usuario_id',
                               'fecha_reporte', 'avance', 'categoria', 'turno')
                      ->with(['usuario:id,nombre'])
                      ->orderByDesc('fecha_reporte')
                      ->limit(5),

                // Últimas 5 incidencias con el nombre del reportante
                'incidencias' => fn ($q) =>
                    $q->select('incidencias.id', 'proyecto_id', 'reportante_id',
                               'titulo', 'severidad', 'estado', 'created_at')
                      ->with(['reportante:id,nombre'])
                      ->orderByDesc('created_at')
                      ->limit(5),
            ]);

            // 4. Calcular KPIs de tiempo basados en fechas del proyecto
            $hoy              = Carbon::today();
            $fechaInicio      = $proyecto->fecha_inicio
                                    ? Carbon::parse($proyecto->fecha_inicio)
                                    : null;
            $fechaFin         = $proyecto->fecha_fin
                                    ? Carbon::parse($proyecto->fecha_fin)
                                    : null;

            $diasTranscurridos = $fechaInicio ? $fechaInicio->diffInDays($hoy) : null;
            $diasTotales       = ($fechaInicio && $fechaFin)
                                    ? $fechaInicio->diffInDays($fechaFin)
                                    : null;
            $diasRestantes     = ($diasTotales !== null && $diasTranscurridos !== null)
                                    ? max(0, $diasTotales - $diasTranscurridos)
                                    : null;

            // 5. Calcular KPIs de incidencias (consultas directas y eficientes)
            $incidenciasActivas  = $proyecto->incidencias()
                ->where('estado', '!=', Incidencia::ESTADO_CERRADA)
                ->count();

            $incidenciasCriticas = $proyecto->incidencias()
                ->where('severidad', Incidencia::SEVERIDAD_CRITICA)
                ->where('estado', '!=', Incidencia::ESTADO_CERRADA)
                ->count();

            // 6. Formatear el personal asignado incluyendo el rol del pivot
            $personal = $proyecto->usuarios->map(fn ($usuario) => [
                'id'               => $usuario->id,
                'nombre'           => $usuario->nombre,
                'email'            => $usuario->email,
                'telefono'         => $usuario->telefono,
                'rol_en_proyecto'  => $usuario->pivot->rol_en_proyecto,
            ])->values();

            // 7. Formatear los últimos 5 reportes
            $ultimosReportes = $proyecto->reportesDiarios->map(fn ($reporte) => [
                'id'            => $reporte->id,
                'fecha_reporte' => $reporte->fecha_reporte,
                'avance'        => $reporte->avance,
                'categoria'     => $reporte->categoria,
                'turno'         => $reporte->turno,
                'usuario'       => $reporte->usuario
                                    ? ['nombre' => $reporte->usuario->nombre]
                                    : null,
            ])->values();

            // 8. Formatear las últimas 5 incidencias
            $ultimasIncidencias = $proyecto->incidencias->map(fn ($incidencia) => [
                'id'         => $incidencia->id,
                'titulo'     => $incidencia->titulo,
                'severidad'  => $incidencia->severidad,
                'estado'     => $incidencia->estado,
                'fecha'      => $incidencia->created_at,
                'reportante' => $incidencia->reportante
                                    ? ['nombre' => $incidencia->reportante->nombre]
                                    : null,
            ])->values();

            return response()->json([
                'status'  => 'success',
                'message' => 'Detalle del proyecto obtenido correctamente.',
                'data'    => [
                    // Datos generales del proyecto
                    'id'          => $proyecto->id,
                    'codigo'      => $proyecto->codigo,
                    'nombre'      => $proyecto->nombre,
                    'cliente'     => $proyecto->cliente,
                    'ubicacion'   => $proyecto->ubicacion,
                    'descripcion' => $proyecto->descripcion,
                    'estado'      => $proyecto->estado,
                    'avance'      => $proyecto->avance,
                    'fecha_inicio' => $proyecto->fecha_inicio,
                    'fecha_fin'    => $proyecto->fecha_fin,

                    // KPIs detallados del proyecto
                    'kpis' => [
                        'avance_total'         => $proyecto->avance,
                        'dias_transcurridos'   => $diasTranscurridos,
                        'dias_totales'         => $diasTotales,
                        'dias_restantes'       => $diasRestantes,
                        'incidencias_activas'  => $incidenciasActivas,
                        'incidencias_criticas' => $incidenciasCriticas,
                    ],

                    // Personal asignado con su rol en el proyecto
                    'personal_asignado' => $personal,

                    // Resumen de los últimos reportes e incidencias
                    'ultimos_reportes'    => $ultimosReportes,
                    'ultimas_incidencias' => $ultimasIncidencias,
                ],
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
    // ACTIVIDAD — Feed de actividad reciente del proyecto
    // =========================================================================

    /**
     * Retorna los 10 eventos más recientes del proyecto, combinando
     * reportes diarios e incidencias en un feed cronológico unificado.
     *
     * Cada item de actividad tiene el mismo formato independientemente
     * de su tipo (reporte o incidencia), facilitando su renderizado en la app.
     *
     * @param  Request  $request  Petición autenticada.
     * @param  int      $id       ID del proyecto a consultar.
     * @return JsonResponse
     */
    public function actividad(Request $request, int $id): JsonResponse
    {
        try {
            // 1. Verificar que el proyecto exista
            $proyecto = Proyecto::find($id);

            if (! $proyecto) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Proyecto no encontrado.',
                ], 404);
            }

            // 2. Verificar que el usuario autenticado tenga acceso al proyecto
            $tieneAcceso = $request->user()
                ->proyectos()
                ->where('proyectos.id', $id)
                ->exists();

            if (! $tieneAcceso) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No tienes acceso a este proyecto.',
                ], 403);
            }

            // 3. Obtener los últimos reportes del proyecto con el nombre del autor
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
                    'usuario' => $reporte->usuario?->nombre ?? 'Usuario desconocido',
                    'fecha'   => $reporte->created_at,
                    // Campo auxiliar para ordenar el feed unificado
                    '_sort_at' => $reporte->created_at,
                ]);

            // 4. Obtener las últimas incidencias del proyecto con el nombre del reportante
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
                    'usuario' => $incidencia->reportante?->nombre ?? 'Usuario desconocido',
                    'fecha'   => $incidencia->created_at,
                    '_sort_at' => $incidencia->created_at,
                ]);

            // 5. Combinar ambas colecciones, ordenar por fecha descendente y tomar 10
            $actividades = $reportes
                ->concat($incidencias)
                ->sortByDesc('_sort_at')
                ->take(10)
                ->map(fn ($item) => [
                    // Eliminar el campo auxiliar de ordenamiento antes de retornar
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
