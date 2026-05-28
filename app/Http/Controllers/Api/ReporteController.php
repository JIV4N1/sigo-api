<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporte\StoreReporteRequest;
use App\Models\FotoReporte;
use App\Models\Proyecto;
use App\Models\ReporteDiario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Controlador de reportes diarios del sistema SIGO.
 *
 * Gestiona la creación, consulta y carga de fotos para los reportes
 * de avance de obra. Todos los endpoints requieren autenticación Sanctum
 * y verifican que el usuario tenga acceso al proyecto relacionado.
 */
class ReporteController extends Controller
{
    // =========================================================================
    // INDEX — Listado paginado de reportes de un proyecto
    // =========================================================================

    /**
     * Lista paginada de reportes diarios de un proyecto específico.
     *
     * Verifica que el usuario autenticado esté asignado al proyecto.
     * Soporta los siguientes filtros opcionales (query params):
     * - ?desde=2026-05-01    → fecha inicio del rango
     * - ?hasta=2026-05-19    → fecha fin del rango
     * - ?categoria=estructural → filtrar por categoría
     * - ?validado=true        → solo reportes validados (o false para no validados)
     *
     * @param  Request  $request  Petición autenticada.
     * @param  int      $id       ID del proyecto.
     * @return JsonResponse
     */
    public function index(Request $request, int $id): JsonResponse
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

            // 2. Verificar que el usuario tenga acceso al proyecto
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

            // 3. Construir la consulta con eager loading para evitar N+1.
            //    Se usa withCount para las fotos y se carga la foto principal.
            $query = ReporteDiario::where('proyecto_id', $id)
                ->with([
                    // Solo el nombre del usuario que elaboró el reporte
                    'usuario:id,nombre',
                    // Foto principal para miniatura en el listado
                    'fotoPrincipal:id,reporte_id,ruta_imagen,es_principal',
                ])
                ->withCount('fotos'); // fotos_count

            // 4. Filtro por rango de fechas
            if ($request->filled('desde')) {
                $query->whereDate('fecha_reporte', '>=', $request->desde);
            }
            if ($request->filled('hasta')) {
                $query->whereDate('fecha_reporte', '<=', $request->hasta);
            }

            // 5. Filtro por categoría
            if ($request->filled('categoria')) {
                $query->where('categoria', $request->categoria);
            }

            // 6. Filtro por estado de validación (cast a booleano desde string)
            if ($request->filled('validado')) {
                $esValidado = filter_var($request->validado, FILTER_VALIDATE_BOOLEAN);
                $query->where('validado', $esValidado);
            }

            // 7. Ordenar por fecha del reporte descendente (más recientes primero)
            $query->orderByDesc('fecha_reporte')->orderByDesc('created_at');

            // 8. Paginar: 15 reportes por página
            $paginado = $query->paginate(15);

            // 9. Transformar cada reporte al formato de respuesta esperado
            $reportes = $paginado->getCollection()->map(function (ReporteDiario $reporte): array {
                // Construir URL pública de la foto principal si existe
                $fotoPrincipal = $reporte->fotoPrincipal
                    ? Storage::disk('public')->url($reporte->fotoPrincipal->ruta_imagen)
                    : null;

                return [
                    'id'            => $reporte->id,
                    'fecha_reporte' => $reporte->fecha_reporte,
                    'turno'         => $reporte->turno,
                    'categoria'     => $reporte->categoria,
                    'avance'        => $reporte->avance,
                    'descripcion'   => $reporte->descripcion,
                    'validado'      => $reporte->validado,
                    'sincronizado'  => $reporte->sincronizado,
                    'created_at'    => $reporte->created_at,

                    // Usuario que elaboró el reporte
                    'usuario' => $reporte->usuario
                        ? ['id' => $reporte->usuario->id, 'nombre' => $reporte->usuario->nombre]
                        : null,

                    // Información de fotos adjuntas
                    'fotos_count'   => $reporte->fotos_count,
                    'foto_principal' => $fotoPrincipal,
                ];
            });

            $paginado->setCollection($reportes);

            return response()->json([
                'status'  => 'success',
                'message' => 'Reportes obtenidos correctamente.',
                'data'    => $paginado,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener los reportes.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    // =========================================================================
    // STORE — Crear nuevo reporte diario con fotos opcionales
    // =========================================================================

    /**
     * Crea un nuevo reporte diario de avance de obra.
     *
     * La validación es delegada a StoreReporteRequest. Si se incluyen fotos,
     * se guardan en storage/app/public/reportes/{proyecto_id}/{fecha}/ y se
     * registran en la tabla fotos_reporte. El avance del proyecto se actualiza
     * con el valor del nuevo reporte.
     *
     * Todo se ejecuta dentro de una transacción de base de datos para garantizar
     * la integridad: si el guardado de cualquier foto falla, se hace rollback.
     *
     * @param  StoreReporteRequest  $request  Petición con datos ya validados.
     * @return JsonResponse
     */
    public function store(StoreReporteRequest $request): JsonResponse
    {
        // Envolver todo en una transacción para mantener integridad de datos
        return DB::transaction(function () use ($request): JsonResponse {
            try {
                // 1. Verificar que el usuario autenticado tenga acceso al proyecto
                $tieneAcceso = $request->user()
                    ->proyectos()
                    ->where('proyectos.id', $request->proyecto_id)
                    ->exists();

                if (! $tieneAcceso) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => 'No tienes acceso a este proyecto.',
                    ], 403);
                }

                // 2. Crear el reporte diario en la base de datos
                $reporte = ReporteDiario::create([
                    'proyecto_id'   => $request->proyecto_id,
                    'usuario_id'    => $request->user()->id,
                    'fecha_reporte' => $request->fecha_reporte,
                    'turno'         => $request->turno,
                    'categoria'     => $request->categoria,
                    'avance'        => $request->avance,
                    'descripcion'   => $request->descripcion,
                    'validado'      => false,
                    'sincronizado'  => true,
                ]);

                // 3. Procesar y guardar las fotos adjuntas (si se enviaron)
                $fotosGuardadas = [];

                if ($request->hasFile('fotos')) {
                    // Directorio de destino: reportes/{proyecto_id}/{fecha}/
                    $directorio = sprintf(
                        'reportes/%d/%s',
                        $request->proyecto_id,
                        Carbon::parse($request->fecha_reporte)->format('Y-m-d')
                    );

                    foreach ($request->file('fotos') as $indice => $archivo) {
                        // Guardar el archivo en el disco público
                        $rutaRelativa = $archivo->store($directorio, 'public');

                        // La primera foto del lote se marca como principal
                        $esPrincipal = ($indice === 0) && ! $reporte->fotos()->exists();

                        // Crear registro en fotos_reporte
                        $foto = FotoReporte::create([
                            'reporte_id'   => $reporte->id,
                            'ruta_imagen'  => $rutaRelativa,
                            'es_principal' => $esPrincipal,
                            'tomada_el'    => now(),
                        ]);

                        $fotosGuardadas[] = [
                            'id'          => $foto->id,
                            'url'         => Storage::disk('public')->url($rutaRelativa),
                            'es_principal' => $foto->es_principal,
                        ];
                    }
                }

                // 4. Actualizar el avance global del proyecto con el valor del reporte
                Proyecto::where('id', $request->proyecto_id)
                    ->update(['avance' => $request->avance]);

                // 5. Cargar el reporte con su autor para la respuesta
                $reporte->load('usuario:id,nombre');

                return response()->json([
                    'status'  => 'success',
                    'message' => 'Reporte creado correctamente.',
                    'data'    => [
                        'id'            => $reporte->id,
                        'proyecto_id'   => $reporte->proyecto_id,
                        'fecha_reporte' => $reporte->fecha_reporte,
                        'turno'         => $reporte->turno,
                        'categoria'     => $reporte->categoria,
                        'avance'        => $reporte->avance,
                        'descripcion'   => $reporte->descripcion,
                        'validado'      => $reporte->validado,
                        'sincronizado'  => $reporte->sincronizado,
                        'created_at'    => $reporte->created_at,
                        'usuario'       => $reporte->usuario
                            ? ['id' => $reporte->usuario->id, 'nombre' => $reporte->usuario->nombre]
                            : null,
                        'fotos'         => $fotosGuardadas,
                        'fotos_count'   => count($fotosGuardadas),
                    ],
                ], 201);

            } catch (\Exception $e) {
                // DB::transaction() hará rollback automáticamente al lanzar excepción
                throw $e;
            }
        });
    }

    // =========================================================================
    // SHOW — Detalle completo de un reporte
    // =========================================================================

    /**
     * Retorna el detalle completo de un reporte diario, incluyendo todas
     * sus fotos, los datos del autor y del validador (si fue validado).
     *
     * Verifica que el usuario autenticado tenga acceso al proyecto al que
     * pertenece el reporte antes de devolver la información.
     *
     * @param  Request  $request  Petición autenticada.
     * @param  int      $id       ID del reporte.
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            // 1. Buscar el reporte con sus relaciones necesarias
            $reporte = ReporteDiario::with([
                'usuario:id,nombre,email,telefono',
                'validador:id,nombre,email',
                'fotos',
            ])->find($id);

            if (! $reporte) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Reporte no encontrado.',
                ], 404);
            }

            // 2. Verificar acceso: el usuario debe pertenecer al proyecto del reporte
            $tieneAcceso = $request->user()
                ->proyectos()
                ->where('proyectos.id', $reporte->proyecto_id)
                ->exists();

            if (! $tieneAcceso) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No tienes acceso a este reporte.',
                ], 403);
            }

            // 3. Construir el listado de fotos con URLs públicas absolutas
            $fotos = $reporte->fotos->map(fn (FotoReporte $foto) => [
                'id'          => $foto->id,
                'url'         => Storage::disk('public')->url($foto->ruta_imagen),
                'descripcion' => $foto->descripcion,
                'categoria'   => $foto->categoria,
                'es_principal' => $foto->es_principal,
                'latitud'     => $foto->latitud,
                'longitud'    => $foto->longitud,
                'tomada_el'   => $foto->tomada_el,
            ])->values();

            return response()->json([
                'status'  => 'success',
                'message' => 'Reporte obtenido correctamente.',
                'data'    => [
                    'id'                => $reporte->id,
                    'proyecto_id'       => $reporte->proyecto_id,
                    'fecha_reporte'     => $reporte->fecha_reporte,
                    'turno'             => $reporte->turno,
                    'categoria'         => $reporte->categoria,
                    'avance'            => $reporte->avance,
                    'descripcion'       => $reporte->descripcion,
                    'condiciones_climaticas' => $reporte->condiciones_climaticas,
                    'personal_presente' => $reporte->personal_presente,
                    'validado'          => $reporte->validado,
                    'validado_el'       => $reporte->validado_el,
                    'notas_validacion'  => $reporte->notas_validacion,
                    'sincronizado'      => $reporte->sincronizado,
                    'created_at'        => $reporte->created_at,
                    'updated_at'        => $reporte->updated_at,

                    // Autor del reporte
                    'usuario' => $reporte->usuario ? [
                        'id'       => $reporte->usuario->id,
                        'nombre'   => $reporte->usuario->nombre,
                        'email'    => $reporte->usuario->email,
                        'telefono' => $reporte->usuario->telefono,
                    ] : null,

                    // Validador (null si el reporte aún no fue validado)
                    'validador' => $reporte->validador ? [
                        'id'     => $reporte->validador->id,
                        'nombre' => $reporte->validador->nombre,
                        'email'  => $reporte->validador->email,
                    ] : null,

                    // Fotos con URLs absolutas
                    'fotos'       => $fotos,
                    'fotos_count' => $fotos->count(),
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener el reporte.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    // =========================================================================
    // SUBIR FOTOS — Añadir fotos a un reporte existente
    // =========================================================================

    /**
     * Añade fotos adicionales a un reporte diario existente.
     *
     * Límite global de 6 fotos por reporte. Si el reporte ya tiene fotos,
     * solo se admitirán las que quepan sin superar el límite.
     * Cada foto puede incluir una categoría, descripción y coordenadas GPS.
     *
     * @param  Request  $request  Petición con archivos de imagen.
     * @param  int      $id       ID del reporte al que se adjuntan las fotos.
     * @return JsonResponse
     */
    public function subirFotos(Request $request, int $id): JsonResponse
    {
        try {
            // 1. Buscar el reporte
            $reporte = ReporteDiario::find($id);

            if (! $reporte) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Reporte no encontrado.',
                ], 404);
            }

            // 2. Verificar que el usuario tenga acceso al proyecto del reporte
            $tieneAcceso = $request->user()
                ->proyectos()
                ->where('proyectos.id', $reporte->proyecto_id)
                ->exists();

            if (! $tieneAcceso) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No tienes acceso a este reporte.',
                ], 403);
            }

            // 3. Verificar que no se supere el límite de 6 fotos por reporte
            $fotosActuales = $reporte->fotos()->count();
            $limite        = 6;

            if ($fotosActuales >= $limite) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "El reporte ya tiene {$fotosActuales} fotos. El límite máximo es {$limite}.",
                ], 422);
            }

            // 4. Validar los archivos y metadatos enviados
            $espacioDisponible = $limite - $fotosActuales;

            $request->validate([
                'fotos'          => ['required', 'array', 'min:1', "max:{$espacioDisponible}"],
                'fotos.*'        => ['image', 'max:5120'], // 5 MB máximo por imagen
                'categorias'     => ['nullable', 'array'],
                'categorias.*'   => ['nullable', 'string', 'max:100'],
                'descripciones'  => ['nullable', 'array'],
                'descripciones.*' => ['nullable', 'string', 'max:255'],
                'latitud'        => ['nullable', 'numeric', 'between:-90,90'],
                'longitud'       => ['nullable', 'numeric', 'between:-180,180'],
            ], [
                'fotos.required'  => 'Debes enviar al menos una foto.',
                'fotos.max'       => "Solo puedes subir {$espacioDisponible} foto(s) más (límite: {$limite} por reporte).",
                'fotos.*.image'   => 'Cada archivo debe ser una imagen válida.',
                'fotos.*.max'     => 'Cada foto no puede pesar más de 5 MB.',
                'latitud.between' => 'La latitud debe estar entre -90 y 90.',
                'longitud.between' => 'La longitud debe estar entre -180 y 180.',
            ]);

            // 5. Directorio de almacenamiento del reporte
            $directorio = sprintf(
                'reportes/%d/%s',
                $reporte->proyecto_id,
                Carbon::parse($reporte->fecha_reporte)->format('Y-m-d')
            );

            // 6. Procesar y guardar cada foto
            $fotosGuardadas = [];

            foreach ($request->file('fotos') as $indice => $archivo) {
                // Guardar el archivo en el disco público y obtener la ruta relativa
                $rutaRelativa = $archivo->store($directorio, 'public');

                // Si el reporte no tiene fotos y es la primera del lote → es principal
                $esPrincipal = ($indice === 0) && ($fotosActuales === 0);

                $foto = FotoReporte::create([
                    'reporte_id'   => $reporte->id,
                    'ruta_imagen'  => $rutaRelativa,
                    'descripcion'  => $request->input("descripciones.{$indice}"),
                    'categoria'    => $request->input("categorias.{$indice}"),
                    'es_principal' => $esPrincipal,
                    'latitud'      => $request->input('latitud'),
                    'longitud'     => $request->input('longitud'),
                    'tomada_el'    => now(),
                ]);

                $fotosGuardadas[] = [
                    'id'           => $foto->id,
                    'url'          => Storage::disk('public')->url($rutaRelativa),
                    'descripcion'  => $foto->descripcion,
                    'categoria'    => $foto->categoria,
                    'es_principal' => $foto->es_principal,
                    'latitud'      => $foto->latitud,
                    'longitud'     => $foto->longitud,
                    'tomada_el'    => $foto->tomada_el,
                ];
            }

            return response()->json([
                'status'  => 'success',
                'message' => count($fotosGuardadas) . ' foto(s) añadida(s) correctamente.',
                'data'    => [
                    'reporte_id'   => $reporte->id,
                    'fotos'        => $fotosGuardadas,
                    'total_fotos'  => $fotosActuales + count($fotosGuardadas),
                ],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Captura específica de errores de validación para formato estándar SIGO
            return response()->json([
                'status'  => 'error',
                'message' => 'Los datos proporcionados no son válidos.',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al subir las fotos.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }
}
