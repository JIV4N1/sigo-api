<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Incidencia\StoreIncidenciaRequest;
use App\Http\Traits\AdminBypassTrait;
use App\Models\ComentarioIncidencia;
use App\Models\FotoIncidencia;
use App\Models\HistorialIncidencia;
use App\Models\Incidencia;
use App\Models\Proyecto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Controlador de incidencias del sistema SIGO (Módulo 4).
 *
 * Gestiona el ciclo de vida completo de las incidencias: reporte, consulta,
 * cambio de estado, carga de fotos y comentarios. Todos los endpoints
 * requieren autenticación Sanctum y verifican acceso al proyecto.
 */
class IncidenciaController extends Controller
{
    use AdminBypassTrait;
    // =========================================================================
    // INDEX — Listado paginado de incidencias de un proyecto
    // =========================================================================

    /**
     * Lista paginada de TODAS las incidencias de la empresa del usuario autenticado.
     *
     * @param  Request  $request
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $empresaId = $this->getEmpresaId($request);

            if (! $empresaId) {
                return response()->json(['status' => 'error', 'message' => 'El usuario no tiene una empresa asignada.'], 403);
            }

            // Filtrar por proyectos que pertenezcan a la empresa del usuario
            $query = Incidencia::whereHas('proyecto', function ($q) use ($empresaId) {
                $q->where('empresa_id', $empresaId);
            })
            ->with([
                'proyecto:id,codigo,nombre',
                'reportante:id,nombre',
                'asignado:id,nombre',
            ])
            ->withCount(['fotos', 'comentarios']);

            // Filtros opcionales
            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }
            if ($request->filled('severidad')) {
                $query->where('severidad', $request->severidad);
            }
            if ($request->filled('proyecto_id')) {
                $query->where('proyecto_id', $request->proyecto_id);
            }
            if ($request->filled('busqueda')) {
                $termino = $request->busqueda;
                $query->where(function ($q) use ($termino): void {
                    $q->where('titulo', 'ILIKE', "%{$termino}%")
                      ->orWhere('descripcion', 'ILIKE', "%{$termino}%");
                });
            }

            // Ordenar y paginar: 15 por página
            $paginado = $query->orderByDesc('created_at')->paginate(15);

            // Transformar cada incidencia
            $incidencias = $paginado->getCollection()->map(function (Incidencia $inc): array {
                $requiereAtencion = $inc->estado === Incidencia::ESTADO_ABIERTA
                    && Carbon::parse($inc->created_at)->diffInHours(now()) > 4;

                return [
                    'id'                    => $inc->id,
                    'codigo'                => $inc->codigo,
                    'titulo'                => $inc->titulo,
                    'categoria'             => $inc->categoria,
                    'severidad'             => $inc->severidad,
                    'estado'                => $inc->estado,
                    'ubicacion_descripcion' => $inc->ubicacion_descripcion,
                    'requiere_atencion'     => $requiereAtencion,
                    'proyecto'              => $inc->proyecto ? ['id' => $inc->proyecto->id, 'codigo' => $inc->proyecto->codigo, 'nombre' => $inc->proyecto->nombre] : null,
                    'reportante'            => $inc->reportante ? ['id' => $inc->reportante->id, 'nombre' => $inc->reportante->nombre] : null,
                    'asignado'              => $inc->asignado  ? ['id' => $inc->asignado->id, 'nombre' => $inc->asignado->nombre]  : null,
                    'fotos_count'           => $inc->fotos_count,
                    'comentarios_count'     => $inc->comentarios_count,
                    'created_at'            => $inc->created_at,
                    'tiempo_relativo'       => Carbon::parse($inc->created_at)->diffForHumans(),
                ];
            });

            $paginado->setCollection($incidencias);

            return response()->json([
                'status'  => 'success',
                'message' => 'Incidencias obtenidas correctamente.',
                'data'    => $paginado,
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las incidencias.', $e);
        }
    }

    /**
     * Lista paginada de incidencias de un proyecto con filtros opcionales.
     *
     * Filtros disponibles (query params):
     * - ?estado=abierta | en_progreso | resuelta | cerrada
     * - ?severidad=baja | media | alta | critica
     * - ?asignadas_a_mi=true  (solo las asignadas al usuario autenticado)
     * - ?busqueda=fisura       (búsqueda en título y descripción)
     *
     * @param  Request  $request
     * @param  int      $proyectoId
     */
    public function porProyecto(Request $request, int $proyectoId): JsonResponse
    {
        try {
            // 1. Verificar que el proyecto existe
            $proyecto = Proyecto::find($proyectoId);
            if (! $proyecto) {
                return response()->json(['status' => 'error', 'message' => 'Proyecto no encontrado.'], 404);
            }

            // 2. Verificar que el usuario tiene acceso al proyecto (admin/gerente siempre tienen acceso)
            if (! $this->tieneAccesoAProyecto($request, $proyectoId)) {
                return response()->json(['status' => 'error', 'message' => 'No tienes acceso a este proyecto.'], 403);
            }

            // 3. Construir consulta con eager loading y conteos
            $query = Incidencia::where('proyecto_id', $proyectoId)
                ->with([
                    'reportante:id,nombre',
                    'asignado:id,nombre',
                ])
                ->withCount(['fotos', 'comentarios']);

            // 4. Aplicar filtros opcionales
            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }
            if ($request->filled('severidad')) {
                $query->where('severidad', $request->severidad);
            }
            if ($request->boolean('asignadas_a_mi')) {
                $query->where('asignado_a', $request->user()->id);
            }
            if ($request->filled('busqueda')) {
                $termino = $request->busqueda;
                $query->where(function ($q) use ($termino): void {
                    $q->where('titulo', 'ILIKE', "%{$termino}%")
                      ->orWhere('descripcion', 'ILIKE', "%{$termino}%");
                });
            }

            // 5. Ordenar y paginar
            $paginado = $query->orderByDesc('created_at')->paginate(10);

            // 6. Transformar cada incidencia al formato de respuesta
            $incidencias = $paginado->getCollection()->map(function (Incidencia $inc): array {
                // Indicador de urgencia: abierta y creada hace más de 4 horas
                $requiereAtencion = $inc->estado === Incidencia::ESTADO_ABIERTA
                    && Carbon::parse($inc->created_at)->diffInHours(now()) > 4;

                return [
                    'id'                    => $inc->id,
                    'codigo'                => $inc->codigo,
                    'titulo'                => $inc->titulo,
                    'categoria'             => $inc->categoria,
                    'severidad'             => $inc->severidad,
                    'estado'                => $inc->estado,
                    'ubicacion_descripcion' => $inc->ubicacion_descripcion,
                    'requiere_atencion'     => $requiereAtencion,
                    'reportante'            => $inc->reportante ? ['nombre' => $inc->reportante->nombre] : null,
                    'asignado'              => $inc->asignado  ? ['nombre' => $inc->asignado->nombre]  : null,
                    'fotos_count'           => $inc->fotos_count,
                    'comentarios_count'     => $inc->comentarios_count,
                    'created_at'            => $inc->created_at,
                    'tiempo_relativo'       => Carbon::parse($inc->created_at)->diffForHumans(),
                ];
            });

            $paginado->setCollection($incidencias);

            return response()->json([
                'status'  => 'success',
                'message' => 'Incidencias obtenidas correctamente.',
                'data'    => $paginado,
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las incidencias.', $e);
        }
    }

    // =========================================================================
    // STORE — Crear nueva incidencia
    // =========================================================================

    /**
     * Crea una nueva incidencia con fotos opcionales e historial inicial.
     * Todo se ejecuta dentro de una transacción DB para garantizar integridad.
     */
    public function store(StoreIncidenciaRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request): JsonResponse {
            try {
                // 1. Verificar acceso al proyecto
                if (! $this->tieneAccesoAProyecto($request, $request->proyecto_id)) {
                    return response()->json(['status' => 'error', 'message' => 'No tienes acceso a este proyecto.'], 403);
                }

                // 2. Generar código único (INC-001, INC-002, ...)
                $codigo = $this->generarCodigo();

                // 3. Crear la incidencia
                $incidencia = Incidencia::create([
                    'codigo'                => $codigo,
                    'proyecto_id'           => $request->proyecto_id,
                    'reportado_por'         => $request->user()->id,
                    'titulo'                => $request->titulo,
                    'descripcion'           => $request->descripcion,
                    'categoria'             => $request->categoria,
                    'severidad'             => $request->severidad,
                    'estado'                => Incidencia::ESTADO_ABIERTA,
                    'latitud'               => $request->latitud,
                    'longitud'              => $request->longitud,
                    'ubicacion_descripcion' => $request->ubicacion_descripcion,
                ]);

                // 4. Procesar fotos opcionales
                $fotosGuardadas = [];
                if ($request->hasFile('fotos')) {
                    $directorio = "incidencias/{$request->proyecto_id}/{$codigo}";
                    foreach ($request->file('fotos') as $archivo) {
                        $ruta = $archivo->store($directorio, 'public');
                        $foto = FotoIncidencia::create([
                            'incidencia_id' => $incidencia->id,
                            'ruta_imagen'   => $ruta,
                        ]);
                        $fotosGuardadas[] = [
                            'id'  => $foto->id,
                            'url' => Storage::disk('public')->url($ruta),
                        ];
                    }
                }

                // 5. Registrar entrada en historial
                HistorialIncidencia::create([
                    'incidencia_id' => $incidencia->id,
                    'usuario_id'    => $request->user()->id,
                    'accion'        => HistorialIncidencia::ACCION_CREADA,
                    'descripcion'   => "Incidencia reportada por {$request->user()->nombre}",
                    'metadatos'     => [
                        'categoria' => $request->categoria,
                        'severidad' => $request->severidad,
                    ],
                ]);

                // 6. Cargar relaciones para la respuesta
                $incidencia->load(['reportante:id,nombre', 'proyecto:id,codigo,nombre', 'historial']);

                return response()->json([
                    'status'  => 'success',
                    'message' => 'Incidencia creada correctamente.',
                    'data'    => [
                        'incidencia' => array_merge($incidencia->toArray(), ['fotos' => $fotosGuardadas])
                    ],
                ], 201);

            } catch (\Exception $e) {
                throw $e; // rollback automático por DB::transaction
            }
        });
    }

    // =========================================================================
    // SHOW — Detalle completo de una incidencia
    // =========================================================================

    /**
     * Retorna la incidencia completa con todas sus relaciones.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $incidencia = Incidencia::with([
                'reportante:id,nombre,email,telefono',
                'asignado:id,nombre,email,telefono',
                'proyecto:id,codigo,nombre',
                'fotos',
                'historial'  => fn ($q) => $q->orderBy('created_at')->with('usuario:id,nombre'),
                'comentarios' => fn ($q) => $q->orderBy('created_at')->with('usuario:id,nombre,foto_perfil'),
            ])->find($id);

            if (! $incidencia) {
                return response()->json(['status' => 'error', 'message' => 'Incidencia no encontrada.'], 404);
            }

            // Verificar acceso al proyecto de la incidencia (admin/gerente siempre tienen acceso)
            if (! $this->tieneAccesoAProyecto($request, $incidencia->proyecto_id)) {
                return response()->json(['status' => 'error', 'message' => 'No tienes acceso a esta incidencia.'], 403);
            }

            // Transformar fotos para incluir URL pública
            $incidencia->fotos->transform(fn (FotoIncidencia $f) => array_merge(
                $f->toArray(),
                ['url' => Storage::disk('public')->url($f->ruta_imagen)]
            ));

            return response()->json([
                'status'  => 'success',
                'message' => 'Incidencia obtenida correctamente.',
                'data'    => $incidencia,
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener la incidencia.', $e);
        }
    }

    // =========================================================================
    // CAMBIAR ESTADO
    // =========================================================================

    /**
     * Cambia el estado de una incidencia y registra el evento en el historial.
     * Si el nuevo estado es 'resuelta' o 'cerrada', el comentario es obligatorio.
     */
    public function cambiarEstado(Request $request, int $id): JsonResponse
    {
        try {
            $incidencia = Incidencia::find($id);

            if (! $incidencia) {
                return response()->json(['status' => 'error', 'message' => 'Incidencia no encontrada.'], 404);
            }

            $user = $request->user();
            $esAdminOGerente = $user->tieneRol(['gerente', 'administrador']);

            if (! $this->tieneAccesoAProyecto($request, $incidencia->proyecto_id)) {
                return response()->json(['status' => 'error', 'message' => 'No tienes acceso a esta incidencia.'], 403);
            }

            // Solo el ingeniero asignado (o un administrador/gerente) puede cambiar el estado
            if ($incidencia->asignado_a !== $user->id && ! $esAdminOGerente) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Solo el ingeniero asignado puede cambiar el estado de esta incidencia.'
                ], 403);
            }

            // Validar payload
            $estadosFinales = [Incidencia::ESTADO_RESUELTA, Incidencia::ESTADO_CERRADA];
            $request->validate([
                'estado'     => ['required', Rule::in([
                    Incidencia::ESTADO_ABIERTA,
                    Incidencia::ESTADO_EN_PROGRESO,
                    Incidencia::ESTADO_RESUELTA,
                    Incidencia::ESTADO_CERRADA,
                ])],
                'comentario' => [
                    in_array($request->estado, $estadosFinales) ? 'required' : 'nullable',
                    'string', 'max:500',
                ],
                'asignado_a' => ['nullable', 'integer', 'exists:usuarios,id'],
            ], [
                'estado.required'     => 'El nuevo estado es obligatorio.',
                'estado.in'           => 'Estado inválido. Use: abierta, en_progreso, resuelta o cerrada.',
                'comentario.required' => 'El comentario es obligatorio al resolver o cerrar una incidencia.',
            ]);

            // Cerrar una incidencia es exclusivo de administrador/gerente
            if ($request->estado === Incidencia::ESTADO_CERRADA && ! $esAdminOGerente) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Solo un administrador o gerente puede cerrar una incidencia.',
                ], 403);
            }

            // Verificar que el estado sea diferente al actual o se esté asignando un nuevo responsable
            $esNuevaAsignacion = $request->has('asignado_a') && $incidencia->asignado_a !== $request->asignado_a;
            if ($incidencia->estado === $request->estado && !$esNuevaAsignacion) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "La incidencia ya se encuentra en estado '{$request->estado}' y no hay cambios en el responsable.",
                ], 422);
            }

            return DB::transaction(function () use ($request, $incidencia, $estadosFinales, $esNuevaAsignacion, $esAdminOGerente): JsonResponse {
                $estadoAnterior = $incidencia->estado;

                // Actualizar el estado y la fecha de resolución si aplica
                $incidencia->estado = $request->estado;
                if ($request->estado === Incidencia::ESTADO_RESUELTA) {
                    $incidencia->resuelta_el = now();
                }

                // Asignar responsable si viene en la petición y es admin/gerente
                $asignadoNuevo = false;
                if ($request->has('asignado_a') && $esAdminOGerente) {
                    $incidencia->asignado_a = $request->asignado_a;
                    $asignadoNuevo = true;
                }

                $incidencia->save();

                $desc = "{$request->user()->nombre} cambió el estado de {$estadoAnterior} a {$request->estado}";
                if ($asignadoNuevo) {
                    $nombreAsignado = \App\Models\Usuario::find($request->asignado_a)?->nombre ?? 'un responsable';
                    $desc .= " y asignó a {$nombreAsignado}";
                }

                // Registrar en historial
                HistorialIncidencia::create([
                    'incidencia_id' => $incidencia->id,
                    'usuario_id'    => $request->user()->id,
                    'accion'        => HistorialIncidencia::ACCION_CAMBIO_ESTADO,
                    'descripcion'   => $desc,
                    'metadatos'     => [
                        'estado_anterior' => $estadoAnterior,
                        'estado_nuevo'    => $request->estado,
                        'comentario'      => $request->comentario,
                        'asignado_a'      => $request->asignado_a,
                    ],
                ]);

                // Crear comentario si se proporcionó
                if ($request->filled('comentario')) {
                    ComentarioIncidencia::create([
                        'incidencia_id' => $incidencia->id,
                        'usuario_id'    => $request->user()->id,
                        'comentario'    => $request->comentario,
                    ]);
                }

                return response()->json([
                    'status'  => 'success',
                    'message' => "Estado actualizado correctamente.",
                    'data'    => $incidencia->fresh(['reportante:id,nombre', 'asignado:id,nombre']),
                ]);
            });

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => 'Datos inválidos.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al cambiar el estado.', $e);
        }
    }

    // =========================================================================
    // SUBIR FOTOS
    // =========================================================================

    /**
     * Añade fotos de evidencia a una incidencia existente (máx. 6 en total).
     */
    public function subirFotos(Request $request, int $id): JsonResponse
    {
        try {
            $incidencia = Incidencia::find($id);

            if (! $incidencia) {
                return response()->json(['status' => 'error', 'message' => 'Incidencia no encontrada.'], 404);
            }

            if (! $this->tieneAccesoAProyecto($request, $incidencia->proyecto_id)) {
                return response()->json(['status' => 'error', 'message' => 'No tienes acceso a esta incidencia.'], 403);
            }

            $fotosActuales     = $incidencia->fotos()->count();
            $limite            = 6;
            $espacioDisponible = $limite - $fotosActuales;

            if ($fotosActuales >= $limite) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "El límite es {$limite} fotos por incidencia. Ya tiene {$fotosActuales}.",
                ], 422);
            }

            $request->validate([
                'fotos'           => ['required', 'array', 'min:1', "max:{$espacioDisponible}"],
                'fotos.*'         => ['image', 'max:5120'],
                'descripciones'   => ['nullable', 'array'],
                'descripciones.*' => ['nullable', 'string', 'max:255'],
                'latitud'         => ['nullable', 'numeric', 'between:-90,90'],
                'longitud'        => ['nullable', 'numeric', 'between:-180,180'],
            ]);

            return DB::transaction(function () use ($request, $incidencia, $fotosActuales): JsonResponse {
                $directorio     = "incidencias/{$incidencia->proyecto_id}/{$incidencia->codigo}";
                $fotosGuardadas = [];

                foreach ($request->file('fotos') as $indice => $archivo) {
                    $ruta = $archivo->store($directorio, 'public');
                    $foto = FotoIncidencia::create([
                        'incidencia_id' => $incidencia->id,
                        'ruta_imagen'   => $ruta,
                        'descripcion'   => $request->input("descripciones.{$indice}"),
                        'latitud'       => $request->input('latitud'),
                        'longitud'      => $request->input('longitud'),
                    ]);
                    $fotosGuardadas[] = [
                        'id'          => $foto->id,
                        'url'         => Storage::disk('public')->url($ruta),
                        'descripcion' => $foto->descripcion,
                    ];
                }

                $cantidadNuevas = count($fotosGuardadas);

                // Registrar en historial
                HistorialIncidencia::create([
                    'incidencia_id' => $incidencia->id,
                    'usuario_id'    => $request->user()->id,
                    'accion'        => HistorialIncidencia::ACCION_COMENTADA,
                    'descripcion'   => "Se agregaron {$cantidadNuevas} foto(s) de evidencia",
                    'metadatos'     => ['fotos_agregadas' => $cantidadNuevas],
                ]);

                return response()->json([
                    'status'  => 'success',
                    'message' => "{$cantidadNuevas} foto(s) añadida(s) correctamente.",
                    'data'    => [
                        'incidencia_id' => $incidencia->id,
                        'fotos'         => $fotosGuardadas,
                        'total_fotos'   => $fotosActuales + $cantidadNuevas,
                    ],
                ], 201);
            });

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => 'Datos inválidos.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al subir las fotos.', $e);
        }
    }

    // =========================================================================
    // COMENTARIOS — Listar y agregar
    // =========================================================================

    /**
     * Retorna los comentarios de una incidencia ordenados cronológicamente.
     */
    public function comentarios(Request $request, int $id): JsonResponse
    {
        try {
            $incidencia = Incidencia::find($id);

            if (! $incidencia) {
                return response()->json(['status' => 'error', 'message' => 'Incidencia no encontrada.'], 404);
            }

            if (! $this->tieneAccesoAProyecto($request, $incidencia->proyecto_id)) {
                return response()->json(['status' => 'error', 'message' => 'No tienes acceso a esta incidencia.'], 403);
            }

            $comentarios = ComentarioIncidencia::where('incidencia_id', $id)
                ->with('usuario:id,nombre,foto_perfil')
                ->orderBy('created_at')
                ->get()
                ->map(fn (ComentarioIncidencia $c) => [
                    'id'         => $c->id,
                    'comentario' => $c->comentario,
                    'created_at' => $c->created_at,
                    'usuario'    => $c->usuario ? [
                        'id'         => $c->usuario->id,
                        'nombre'     => $c->usuario->nombre,
                        'foto_perfil' => $c->usuario->foto_perfil,
                    ] : null,
                ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Comentarios obtenidos correctamente.',
                'data'    => $comentarios,
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener los comentarios.', $e);
        }
    }

    /**
     * Agrega un comentario a una incidencia y lo registra en el historial.
     */
    public function agregarComentario(Request $request, int $id): JsonResponse
    {
        try {
            $incidencia = Incidencia::find($id);

            if (! $incidencia) {
                return response()->json(['status' => 'error', 'message' => 'Incidencia no encontrada.'], 404);
            }

            if (! $this->tieneAccesoAProyecto($request, $incidencia->proyecto_id)) {
                return response()->json(['status' => 'error', 'message' => 'No tienes acceso a esta incidencia.'], 403);
            }

            $request->validate([
                'comentario' => ['required', 'string', 'min:1', 'max:500'],
            ], [
                'comentario.required' => 'El comentario no puede estar vacío.',
                'comentario.max'      => 'El comentario no puede superar los 500 caracteres.',
            ]);

            return DB::transaction(function () use ($request, $incidencia): JsonResponse {
                // Crear el comentario
                $comentario = ComentarioIncidencia::create([
                    'incidencia_id' => $incidencia->id,
                    'usuario_id'    => $request->user()->id,
                    'comentario'    => $request->comentario,
                ]);

                // Registrar en historial
                HistorialIncidencia::create([
                    'incidencia_id' => $incidencia->id,
                    'usuario_id'    => $request->user()->id,
                    'accion'        => HistorialIncidencia::ACCION_COMENTADA,
                    'descripcion'   => "{$request->user()->nombre} agregó un comentario",
                    'metadatos'     => null,
                ]);

                $comentario->load('usuario:id,nombre,foto_perfil');

                return response()->json([
                    'status'  => 'success',
                    'message' => 'Comentario agregado correctamente.',
                    'data'    => [
                        'id'         => $comentario->id,
                        'comentario' => $comentario->comentario,
                        'created_at' => $comentario->created_at,
                        'usuario'    => $comentario->usuario ? [
                            'id'          => $comentario->usuario->id,
                            'nombre'      => $comentario->usuario->nombre,
                            'foto_perfil' => $comentario->usuario->foto_perfil,
                        ] : null,
                    ],
                ], 201);
            });

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => 'Datos inválidos.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al agregar el comentario.', $e);
        }
    }

    // =========================================================================
    // INCIDENCIAS ASIGNADAS AL USUARIO (Cross-project)
    // =========================================================================
    public function incidenciasAsignadas(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $query = Incidencia::where('asignado_a', $user->id)
                ->with([
                    'reportante:id,nombre',
                    'proyecto:id,codigo,nombre'
                ])
                ->withCount(['fotos', 'comentarios']);

            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->filled('severidad')) {
                $query->where('severidad', $request->severidad);
            }

            $paginado = $query->orderByDesc('created_at')->paginate(10);

            $incidencias = $paginado->getCollection()->map(function (Incidencia $inc): array {
                $requiereAtencion = $inc->estado === Incidencia::ESTADO_ABIERTA
                    && Carbon::parse($inc->created_at)->diffInHours(now()) > 4;

                return [
                    'id'                    => $inc->id,
                    'codigo'                => $inc->codigo,
                    'titulo'                => $inc->titulo,
                    'categoria'             => $inc->categoria,
                    'severidad'             => $inc->severidad,
                    'estado'                => $inc->estado,
                    'ubicacion_descripcion' => $inc->ubicacion_descripcion,
                    'requiere_atencion'     => $requiereAtencion,
                    'proyecto'              => $inc->proyecto ? ['codigo' => $inc->proyecto->codigo, 'nombre' => $inc->proyecto->nombre] : null,
                    'reportante'            => $inc->reportante ? ['nombre' => $inc->reportante->nombre] : null,
                    'fotos_count'           => $inc->fotos_count,
                    'comentarios_count'     => $inc->comentarios_count,
                    'created_at'            => $inc->created_at,
                    'tiempo_relativo'       => Carbon::parse($inc->created_at)->diffForHumans(),
                ];
            });

            $paginado->setCollection($incidencias);

            return response()->json([
                'status'  => 'success',
                'message' => 'Incidencias asignadas obtenidas correctamente.',
                'data'    => $paginado,
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener incidencias asignadas.', $e);
        }
    }

    // =========================================================================
    // MÉTODOS PRIVADOS DE APOYO
    // =========================================================================

    /**
     * Genera el siguiente código de incidencia en formato INC-XXX.
     *
     * Obtiene el último código registrado, extrae el número secuencial,
     * lo incrementa en 1 y formatea con ceros a la izquierda (3 dígitos).
     * Si no existe ninguna incidencia, inicia en INC-001.
     */
    private function generarCodigo(): string
    {
        // Bloqueo pesimista para evitar condición de carrera en concurrencia
        $ultima = Incidencia::lockForUpdate()
            ->whereNotNull('codigo')
            ->orderByDesc('id')
            ->value('codigo');

        if (! $ultima) {
            return 'INC-001';
        }

        // Extraer el número del formato INC-XXX
        $numero = (int) substr($ultima, 4); // "INC-042" → 42

        return sprintf('INC-%03d', $numero + 1);
    }

    // Nota: la verificación de acceso a proyectos se gestiona mediante
    // AdminBypassTrait::tieneAccesoAProyecto(), que otorga bypass automático
    // a los roles 'administrador' y 'gerente'.

    /**
     * Genera una respuesta JSON de error estándar SIGO (500).
     */
    private function errorResponse(string $mensaje, \Exception $e): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => $mensaje,
            'errors'  => ['exception' => $e->getMessage()],
        ], 500);
    }
}
