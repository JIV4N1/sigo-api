<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\AdminBypassTrait;
use App\Models\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Controlador de reportes analíticos de ventas e inventario para la API SIGO.
 *
 * Nombrado "ReporteVentasController" (y no "ReporteController") porque ya existe
 * App\Http\Controllers\Api\ReporteController para los reportes diarios de obra
 * (ReporteDiario) — son dominios distintos y PHP no permite dos clases con el
 * mismo nombre en el mismo namespace.
 *
 * Todos los endpoints están restringidos a administrador/gerente.
 */
class ReporteVentasController extends Controller
{
    use AdminBypassTrait;

    private const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    /**
     * GET /api/reportes/ventas-por-dia
     * Parámetros opcionales: fecha_inicio, fecha_fin (default: mes actual).
     */
    public function ventasPorDia(Request $request): JsonResponse
    {
        if ($error = $this->verificarAcceso($request)) {
            return $error;
        }

        try {
            [$desde, $hasta] = $this->resolverRangoFechas($request);

            $filas = DB::table('ventas')
                ->where('empresa_id', $this->getEmpresaId($request))
                ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
                ->selectRaw('fecha, COUNT(*) as total_ventas, SUM(total) as total_ingresos, AVG(total) as promedio_venta')
                ->groupBy('fecha')
                ->orderBy('fecha', 'asc')
                ->get();

            $data = $filas->map(fn ($fila) => [
                'fecha'          => $fila->fecha,
                'total_ventas'   => (int) $fila->total_ventas,
                'total_ingresos' => round((float) $fila->total_ingresos, 2),
                'promedio_venta' => round((float) $fila->promedio_venta, 2),
            ]);

            return response()->json([
                'status'  => 'success',
                'data'    => $data,
                'message' => 'Reporte generado correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al generar el reporte de ventas por día.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * GET /api/reportes/ventas-por-mes
     * Parámetros opcionales: fecha_inicio, fecha_fin (default: mes actual).
     */
    public function ventasPorMes(Request $request): JsonResponse
    {
        if ($error = $this->verificarAcceso($request)) {
            return $error;
        }

        try {
            [$desde, $hasta] = $this->resolverRangoFechas($request);

            $filas = DB::table('ventas')
                ->where('empresa_id', $this->getEmpresaId($request))
                ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
                ->selectRaw(
                    'EXTRACT(YEAR FROM fecha)::int as anio, EXTRACT(MONTH FROM fecha)::int as mes_num, ' .
                    'COUNT(*) as total_ventas, SUM(total) as total_ingresos, AVG(total) as promedio_venta'
                )
                ->groupBy(DB::raw('EXTRACT(YEAR FROM fecha)'), DB::raw('EXTRACT(MONTH FROM fecha)'))
                ->orderBy('anio', 'asc')
                ->orderBy('mes_num', 'asc')
                ->get();

            $data = $filas->map(fn ($fila) => [
                'mes'            => (self::MESES[$fila->mes_num] ?? $fila->mes_num) . ' ' . $fila->anio,
                'total_ventas'   => (int) $fila->total_ventas,
                'total_ingresos' => round((float) $fila->total_ingresos, 2),
                'promedio_venta' => round((float) $fila->promedio_venta, 2),
            ]);

            return response()->json([
                'status'  => 'success',
                'data'    => $data,
                'message' => 'Reporte generado correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al generar el reporte de ventas por mes.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * GET /api/reportes/materiales-mas-vendidos
     * Parámetros opcionales: fecha_inicio, fecha_fin (default: mes actual), limite (default: 10).
     */
    public function materialesMasVendidos(Request $request): JsonResponse
    {
        if ($error = $this->verificarAcceso($request)) {
            return $error;
        }

        try {
            [$desde, $hasta] = $this->resolverRangoFechas($request);
            $limite = max(1, (int) $request->query('limite', 10));

            $filas = DB::table('partidas_venta as pv')
                ->join('ventas as v', 'v.id', '=', 'pv.venta_id')
                ->join('materiales as m', 'm.id', '=', 'pv.material_id')
                ->where('v.empresa_id', $this->getEmpresaId($request))
                ->whereBetween('v.fecha', [$desde->toDateString(), $hasta->toDateString()])
                ->selectRaw(
                    'm.id as material_id, m.nombre as material_nombre, m.unidad_medida, ' .
                    'SUM(pv.cantidad) as cantidad_total, SUM(pv.subtotal) as total_ingresos'
                )
                ->groupBy('m.id', 'm.nombre', 'm.unidad_medida')
                ->orderByDesc('cantidad_total')
                ->limit($limite)
                ->get();

            $data = $filas->map(fn ($fila) => [
                'material_id'     => $fila->material_id,
                'material_nombre' => $fila->material_nombre,
                'unidad_medida'   => $fila->unidad_medida,
                'cantidad_total'  => round((float) $fila->cantidad_total, 2),
                'total_ingresos'  => round((float) $fila->total_ingresos, 2),
            ]);

            return response()->json([
                'status'  => 'success',
                'data'    => $data,
                'message' => 'Reporte generado correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al generar el reporte de materiales más vendidos.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * GET /api/reportes/clientes-frecuentes
     * Parámetros opcionales: fecha_inicio, fecha_fin (default: mes actual), limite (default: 10).
     */
    public function clientesFrecuentes(Request $request): JsonResponse
    {
        if ($error = $this->verificarAcceso($request)) {
            return $error;
        }

        try {
            [$desde, $hasta] = $this->resolverRangoFechas($request);
            $limite = max(1, (int) $request->query('limite', 10));

            $filas = DB::table('ventas as v')
                ->join('clientes as c', 'c.id', '=', 'v.cliente_id')
                ->where('v.empresa_id', $this->getEmpresaId($request))
                ->whereBetween('v.fecha', [$desde->toDateString(), $hasta->toDateString()])
                ->selectRaw(
                    'c.id as cliente_id, c.razon_social as cliente_nombre, ' .
                    'COUNT(*) as total_compras, SUM(v.total) as total_gastado, AVG(v.total) as promedio_compra'
                )
                ->groupBy('c.id', 'c.razon_social')
                ->orderByDesc('total_compras')
                ->limit($limite)
                ->get();

            $data = $filas->map(fn ($fila) => [
                'cliente_id'      => $fila->cliente_id,
                'cliente_nombre'  => $fila->cliente_nombre,
                'total_compras'   => (int) $fila->total_compras,
                'total_gastado'   => round((float) $fila->total_gastado, 2),
                'promedio_compra' => round((float) $fila->promedio_compra, 2),
            ]);

            return response()->json([
                'status'  => 'success',
                'data'    => $data,
                'message' => 'Reporte generado correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al generar el reporte de clientes frecuentes.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * GET /api/reportes/inventario-actual
     * Sin parámetros. Solo materiales activos de la empresa activa del usuario.
     */
    public function inventarioActual(Request $request): JsonResponse
    {
        if ($error = $this->verificarAcceso($request)) {
            return $error;
        }

        try {
            $materiales = Material::where('empresa_id', $this->getEmpresaId($request))
                ->where('activo', true)
                ->orderBy('nombre', 'asc')
                ->get(['id', 'nombre', 'unidad_medida', 'stock_actual', 'stock_minimo', 'precio_compra', 'precio_venta']);

            $data = $materiales->map(fn (Material $m) => [
                'material_id'     => $m->id,
                'material_nombre' => $m->nombre,
                'unidad_medida'   => $m->unidad_medida,
                'stock_actual'    => (float) $m->stock_actual,
                'stock_minimo'    => (float) $m->stock_minimo,
                'precio_compra'   => (float) $m->precio_compra,
                'precio_venta'    => (float) $m->precio_venta,
            ]);

            return response()->json([
                'status'  => 'success',
                'data'    => $data,
                'message' => 'Reporte generado correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al generar el reporte de inventario actual.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * GET /api/reportes/materiales-bajo-stock
     * Sin parámetros de filtro adicionales: siempre stock_actual < stock_minimo
     * (igual que el DTO del escritorio, MaterialBajoStockDto, que no tiene "umbral").
     * Ordenado por porcentaje_stock ascendente (más crítico primero).
     */
    public function materialesBajoStock(Request $request): JsonResponse
    {
        if ($error = $this->verificarAcceso($request)) {
            return $error;
        }

        try {
            $filas = DB::table('materiales')
                ->where('empresa_id', $this->getEmpresaId($request))
                ->where('activo', true)
                ->whereColumn('stock_actual', '<', 'stock_minimo')
                ->selectRaw(
                    'id as material_id, nombre as material_nombre, unidad_medida, stock_actual, stock_minimo, ' .
                    '(stock_minimo - stock_actual) as diferencia, ' .
                    'CASE WHEN stock_minimo > 0 THEN (stock_actual / stock_minimo) * 100 ELSE 0 END as porcentaje_stock'
                )
                ->orderByRaw('CASE WHEN stock_minimo > 0 THEN (stock_actual / stock_minimo) ELSE 0 END ASC')
                ->get();

            $data = $filas->map(fn ($fila) => [
                'material_id'      => $fila->material_id,
                'material_nombre'  => $fila->material_nombre,
                'unidad_medida'    => $fila->unidad_medida,
                'stock_actual'     => (float) $fila->stock_actual,
                'stock_minimo'     => (float) $fila->stock_minimo,
                'diferencia'       => round((float) $fila->diferencia, 2),
                'porcentaje_stock' => round((float) $fila->porcentaje_stock, 2),
            ]);

            return response()->json([
                'status'  => 'success',
                'data'    => $data,
                'message' => 'Reporte generado correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al generar el reporte de materiales bajo stock.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Solo administrador/gerente pueden consultar reportes.
     * Supervisor/ingeniero no tienen acceso (deben ser redirigidos al dashboard en el frontend).
     */
    private function verificarAcceso(Request $request): ?JsonResponse
    {
        if (! $request->user()->tieneRol(['administrador', 'gerente'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para consultar reportes.',
            ], 403);
        }

        return null;
    }

    /**
     * Resuelve el rango de fechas [desde, hasta] a partir de fecha_inicio/fecha_fin
     * en el query string, con el mes actual como valor por defecto.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolverRangoFechas(Request $request): array
    {
        $desde = $request->filled('fecha_inicio')
            ? Carbon::parse($request->query('fecha_inicio'))->startOfDay()
            : Carbon::now()->startOfMonth();

        $hasta = $request->filled('fecha_fin')
            ? Carbon::parse($request->query('fecha_fin'))->endOfDay()
            : Carbon::now()->endOfMonth();

        return [$desde, $hasta];
    }
}
