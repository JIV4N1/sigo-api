<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\AdminBypassTrait;
use App\Models\FotoIncidencia;
use App\Models\FotoReporte;
use App\Models\Proyecto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Galería de Evidencias: une en un solo listado las fotos de reportes
 * diarios y de incidencias de los proyectos accesibles por el usuario
 * autenticado, con URL absoluta lista para mostrarse en el frontend.
 */
class EvidenciaController extends Controller
{
    use AdminBypassTrait;

    /**
     * GET /api/evidencias
     *
     * Filtros vía query string:
     * - ?proyecto_id=N     → solo fotos de ese proyecto
     * - ?tipo=reporte|incidencia → solo fotos de ese origen
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // 1. Proyectos accesibles: admin/gerente ven toda la empresa,
            // el resto solo sus proyectos asignados (mismo criterio que
            // ReporteController::listar()).
            if ($this->isAdminOrGerente($request)) {
                $proyectosIds = Proyecto::where('empresa_id', $this->getEmpresaId($request))->pluck('id');
            } else {
                $proyectosIds = $user->proyectos()->pluck('proyectos.id');
            }

            if ($request->filled('proyecto_id')) {
                $proyectosIds = $proyectosIds->filter(fn ($id) => (int) $id === (int) $request->proyecto_id)->values();
            }

            $tipo = $request->query('tipo');

            $evidencias = collect();

            // 2. Fotos de reportes diarios
            if ($tipo !== 'incidencia') {
                $fotosReporte = FotoReporte::whereHas('reporte', function ($q) use ($proyectosIds) {
                    $q->whereIn('proyecto_id', $proyectosIds);
                })
                    ->with(['reporte:id,proyecto_id,fecha_reporte', 'reporte.proyecto:id,nombre'])
                    ->get()
                    ->map(fn (FotoReporte $foto) => [
                        'id'              => $foto->id,
                        'tipo'            => 'reporte',
                        'url'             => $foto->url_imagen,
                        'descripcion'     => $foto->descripcion,
                        'proyecto_id'     => $foto->reporte?->proyecto_id,
                        'proyecto_nombre' => $foto->reporte?->proyecto?->nombre,
                        'fecha'           => $foto->reporte?->fecha_reporte?->toDateString(),
                        'origen_id'       => $foto->reporte_id,
                    ]);

                $evidencias = $evidencias->concat($fotosReporte);
            }

            // 3. Fotos de incidencias
            if ($tipo !== 'reporte') {
                $fotosIncidencia = FotoIncidencia::whereHas('incidencia', function ($q) use ($proyectosIds) {
                    $q->whereIn('proyecto_id', $proyectosIds);
                })
                    ->with(['incidencia:id,proyecto_id,codigo,titulo,created_at', 'incidencia.proyecto:id,nombre'])
                    ->get()
                    ->map(fn (FotoIncidencia $foto) => [
                        'id'              => $foto->id,
                        'tipo'            => 'incidencia',
                        'url'             => $foto->url_imagen,
                        'descripcion'     => $foto->descripcion,
                        'proyecto_id'     => $foto->incidencia?->proyecto_id,
                        'proyecto_nombre' => $foto->incidencia?->proyecto?->nombre,
                        'fecha'           => $foto->incidencia?->created_at?->toDateString(),
                        'origen_id'       => $foto->incidencia_id,
                    ]);

                $evidencias = $evidencias->concat($fotosIncidencia);
            }

            // 4. Ordenar por fecha descendente (más recientes primero) y paginar manualmente,
            // ya que se combinan dos fuentes distintas antes de paginar.
            $evidencias = $evidencias->sortByDesc('fecha')->values();

            $perPage = 20;
            $page = (int) $request->query('page', 1);
            $total = $evidencias->count();
            $items = $evidencias->slice(($page - 1) * $perPage, $perPage)->values();

            $paginado = new LengthAwarePaginator($items, $total, $perPage, $page, [
                'path'  => $request->url(),
                'query' => $request->query(),
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Evidencias obtenidas correctamente.',
                'data'    => $paginado,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener las evidencias.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }
}
