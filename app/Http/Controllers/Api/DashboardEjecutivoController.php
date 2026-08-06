<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\AdminBypassTrait;
use App\Models\Incidencia;
use App\Models\Material;
use App\Models\Proyecto;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Controlador del Dashboard Ejecutivo para la API SIGO.
 *
 * Agrega KPIs, tendencias, distribuciones y actividad reciente de la empresa
 * activa del usuario autenticado. Restringido a administrador/gerente.
 */
class DashboardEjecutivoController extends Controller
{
    use AdminBypassTrait;

    private const MESES_ABREV = [
        1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun',
        7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
    ];

    private const ESTADOS_PROYECTO_LABEL = [
        'planeado'   => 'Planeado',
        'activo'     => 'Activo',
        'en_curso'   => 'En curso',
        'pausado'    => 'En pausa',
        'finalizado' => 'Finalizado',
        'cancelado'  => 'Cancelado',
    ];

    /**
     * GET /api/dashboard-ejecutivo
     * Endpoint combinado: kpis + tendencias + distribuciones + actividad reciente.
     */
    public function index(Request $request): JsonResponse
    {
        if ($error = $this->verificarAcceso($request)) {
            return $error;
        }

        try {
            return response()->json([
                'status'  => 'success',
                'data'    => [
                    'kpis'               => $this->calcularKpis($request),
                    'tendencias'         => $this->calcularTendencias($request),
                    'distribuciones'     => $this->calcularDistribuciones($request),
                    'actividad_reciente' => $this->calcularActividadReciente($request),
                ],
                'message' => 'Dashboard ejecutivo obtenido correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener el dashboard ejecutivo.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * GET /api/dashboard-ejecutivo/kpis
     */
    public function kpis(Request $request): JsonResponse
    {
        if ($error = $this->verificarAcceso($request)) {
            return $error;
        }

        try {
            return response()->json([
                'status'  => 'success',
                'data'    => $this->calcularKpis($request),
                'message' => 'KPIs obtenidos correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener los KPIs.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * GET /api/dashboard-ejecutivo/tendencias
     * Ventas de los últimos 6 meses (incluye el mes actual).
     */
    public function tendencias(Request $request): JsonResponse
    {
        if ($error = $this->verificarAcceso($request)) {
            return $error;
        }

        try {
            return response()->json([
                'status'  => 'success',
                'data'    => $this->calcularTendencias($request),
                'message' => 'Tendencias obtenidas correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener las tendencias.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * GET /api/dashboard-ejecutivo/distribuciones
     * Distribución de proyectos por estado.
     */
    public function distribuciones(Request $request): JsonResponse
    {
        if ($error = $this->verificarAcceso($request)) {
            return $error;
        }

        try {
            return response()->json([
                'status'  => 'success',
                'data'    => $this->calcularDistribuciones($request),
                'message' => 'Distribuciones obtenidas correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener las distribuciones.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * GET /api/dashboard-ejecutivo/actividad-reciente
     * Últimas 5 ventas y últimas 5 incidencias de la empresa.
     */
    public function actividadReciente(Request $request): JsonResponse
    {
        if ($error = $this->verificarAcceso($request)) {
            return $error;
        }

        try {
            return response()->json([
                'status'  => 'success',
                'data'    => $this->calcularActividadReciente($request),
                'message' => 'Actividad reciente obtenida correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener la actividad reciente.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    // =========================================================================
    // Helpers privados
    // =========================================================================

    /**
     * Solo administrador/gerente pueden consultar el dashboard ejecutivo.
     */
    private function verificarAcceso(Request $request): ?JsonResponse
    {
        if (! $request->user()->tieneRol(['administrador', 'gerente'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para consultar el dashboard ejecutivo.',
            ], 403);
        }

        return null;
    }

    /**
     * @return array<string, int|float>
     */
    private function calcularKpis(Request $request): array
    {
        $empresaId = $this->getEmpresaId($request);

        return [
            'total_proyectos' => Proyecto::where('empresa_id', $empresaId)->count(),

            'proyectos_activos' => Proyecto::where('empresa_id', $empresaId)
                ->whereIn('estado', ['activo', 'en_curso'])
                ->count(),

            'total_ventas_mes' => round((float) Venta::where('empresa_id', $empresaId)
                ->whereYear('fecha', now()->year)
                ->whereMonth('fecha', now()->month)
                ->sum('total'), 2),

            'total_empleados' => Usuario::where('empresa_id', $empresaId)
                ->where('activo', true)
                ->count(),

            'incidencias_pendientes' => Incidencia::whereHas('proyecto', fn ($q) => $q->where('empresa_id', $empresaId))
                ->whereIn('estado', [Incidencia::ESTADO_ABIERTA, Incidencia::ESTADO_EN_PROGRESO])
                ->count(),

            'stock_bajo' => Material::where('empresa_id', $empresaId)
                ->where('activo', true)
                ->whereColumn('stock_actual', '<=', 'stock_minimo')
                ->count(),
        ];
    }

    /**
     * Ventas por mes de los últimos 6 meses (incluye el mes actual), en
     * formato listo para gráfica de líneas/barras.
     *
     * @return array{labels: array<int,string>, datasets: array<int,array{label:string,data:array<int,float>}>}
     */
    private function calcularTendencias(Request $request): array
    {
        $empresaId = $this->getEmpresaId($request);
        $desde = now()->subMonths(5)->startOfMonth();
        $hasta = now()->endOfMonth();

        $filas = DB::table('ventas')
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->selectRaw('EXTRACT(YEAR FROM fecha)::int as anio, EXTRACT(MONTH FROM fecha)::int as mes_num, SUM(total) as total_mes')
            ->groupBy(DB::raw('EXTRACT(YEAR FROM fecha)'), DB::raw('EXTRACT(MONTH FROM fecha)'))
            ->get()
            ->keyBy(fn ($fila) => $fila->anio . '-' . $fila->mes_num);

        $labels = [];
        $data = [];
        $cursor = $desde->copy();

        for ($i = 0; $i < 6; $i++) {
            $clave = $cursor->year . '-' . $cursor->month;
            $labels[] = self::MESES_ABREV[$cursor->month];
            $data[] = $filas->has($clave) ? round((float) $filas->get($clave)->total_mes, 2) : 0.0;
            $cursor->addMonth();
        }

        return [
            'labels'   => $labels,
            'datasets' => [
                ['label' => 'Ventas', 'data' => $data],
            ],
        ];
    }

    /**
     * Distribución de proyectos por estado (solo estados con al menos un proyecto).
     *
     * @return array{labels: array<int,string>, data: array<int,int>}
     */
    private function calcularDistribuciones(Request $request): array
    {
        $empresaId = $this->getEmpresaId($request);

        $filas = Proyecto::where('empresa_id', $empresaId)
            ->select('estado', DB::raw('count(*) as total'))
            ->groupBy('estado')
            ->get();

        $labels = [];
        $data = [];

        foreach ($filas as $fila) {
            $labels[] = self::ESTADOS_PROYECTO_LABEL[$fila->estado] ?? ucfirst($fila->estado);
            $data[] = (int) $fila->total;
        }

        return [
            'labels' => $labels,
            'data'   => $data,
        ];
    }

    /**
     * Últimas 5 ventas y últimas 5 incidencias de la empresa.
     *
     * @return array{ventas: array<int,array>, incidencias: array<int,array>}
     */
    private function calcularActividadReciente(Request $request): array
    {
        $empresaId = $this->getEmpresaId($request);

        $ventas = Venta::where('empresa_id', $empresaId)
            ->with('cliente:id,razon_social')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Venta $v) => [
                'folio'   => $v->folio,
                'cliente' => $v->cliente?->razon_social,
                'total'   => (float) $v->total,
                'fecha'   => $v->fecha ? $v->fecha->format('Y-m-d') : null,
            ])
            ->values();

        $incidencias = Incidencia::whereHas('proyecto', fn ($q) => $q->where('empresa_id', $empresaId))
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Incidencia $i) => [
                'titulo' => $i->titulo,
                'estado' => $i->estado,
                'fecha'  => $i->created_at ? $i->created_at->format('Y-m-d') : null,
            ])
            ->values();

        return [
            'ventas'      => $ventas,
            'incidencias' => $incidencias,
        ];
    }
}
