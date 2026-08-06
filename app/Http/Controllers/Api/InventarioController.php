<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventario\StoreMovimientoRequest;
use App\Http\Traits\AdminBypassTrait;
use App\Models\Material;
use App\Models\MovimientoInventario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventarioController extends Controller
{
    use AdminBypassTrait;

    /**
     * GET /api/inventario
     */
    public function index(Request $request)
    {
        $empresaId = $this->getEmpresaId($request);

        $materiales = Material::with('proveedor:id,nombre')
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get([
                'id', 'codigo', 'nombre', 'unidad_medida', 'stock_actual', 
                'stock_minimo', 'precio_compra', 'precio_venta', 'proveedor_id'
            ]);

        // Mapear para estructura de respuesta (se espera el proveedor con id y nombre)
        $materialesData = $materiales->map(function ($material) {
            return [
                'id' => $material->id,
                'codigo' => $material->codigo,
                'nombre' => $material->nombre,
                'unidad_medida' => $material->unidad_medida,
                'stock_actual' => $material->stock_actual,
                'stock_minimo' => $material->stock_minimo,
                'precio_compra' => $material->precio_compra,
                'precio_venta' => $material->precio_venta,
                'proveedor' => $material->proveedor ? [
                    'id' => $material->proveedor->id,
                    'nombre' => $material->proveedor->nombre,
                ] : null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'materiales' => $materialesData
            ],
            'message' => 'Inventario obtenido correctamente'
        ]);
    }

    /**
     * GET /api/inventario/bajo-stock
     */
    public function bajoStock(Request $request)
    {
        $empresaId = $this->getEmpresaId($request);

        $materiales = Material::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->whereColumn('stock_actual', '<=', 'stock_minimo')
            ->orderBy('nombre')
            ->get([
                'id', 'codigo', 'nombre', 'unidad_medida', 'stock_actual', 'stock_minimo'
            ]);

        $materialesData = $materiales->map(function ($material) {
            return [
                'id' => $material->id,
                'codigo' => $material->codigo,
                'nombre' => $material->nombre,
                'unidad_medida' => $material->unidad_medida,
                'stock_actual' => $material->stock_actual,
                'stock_minimo' => $material->stock_minimo,
                'alerta' => "Stock bajo: {$material->stock_actual} de {$material->stock_minimo} mínimo"
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'materiales' => $materialesData
            ],
            'message' => 'Materiales con bajo stock obtenidos correctamente'
        ]);
    }

    /**
     * GET /api/inventario/historial
     */
    public function historial(Request $request)
    {
        $empresaId = $this->getEmpresaId($request);
        $materialId = $request->query('material_id');

        $query = MovimientoInventario::with(['material', 'usuario'])
            ->whereHas('material', function ($q) use ($empresaId) {
                $q->where('empresa_id', $empresaId);
            });

        if ($materialId) {
            $query->where('material_id', $materialId);
        }

        $movimientos = $query->orderBy('created_at', 'desc')->get();

        $movimientosData = $movimientos->map(function ($movimiento) {
            return [
                'id' => $movimiento->id,
                'material_id' => $movimiento->material_id,
                'material' => $movimiento->material ? $movimiento->material->nombre : 'Desconocido',
                'tipo_movimiento' => $movimiento->tipo_movimiento,
                'cantidad' => $movimiento->cantidad,
                'stock_anterior' => $movimiento->stock_anterior,
                'stock_nuevo' => $movimiento->stock_nuevo,
                'motivo' => $movimiento->motivo,
                'usuario' => $movimiento->usuario ? $movimiento->usuario->nombre : 'Desconocido',
                'fecha' => $movimiento->created_at,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'movimientos' => $movimientosData
            ],
            'message' => 'Historial obtenido correctamente'
        ]);
    }

    /**
     * POST /api/inventario/movimiento
     */
    public function registrarMovimiento(StoreMovimientoRequest $request)
    {
        $validated = $request->validated();

        $user = $request->user();
        $material = Material::findOrFail($validated['material_id']);

        if ($material->empresa_id !== $this->getEmpresaId($request)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Acceso denegado a este material'
            ], 403);
        }

        $cantidad = (float) $validated['cantidad'];
        $tipoMovimiento = $validated['tipo_movimiento'];
        $stockAnterior = $material->stock_actual;

        if ($tipoMovimiento === 'salida' && $stockAnterior < $cantidad) {
            return response()->json([
                'status' => 'error',
                'message' => "Stock insuficiente. Stock actual: {$stockAnterior}, intenta retirar: {$cantidad}"
            ], 422);
        }

        $stockNuevo = match ($tipoMovimiento) {
            'entrada' => $stockAnterior + $cantidad,
            'salida' => $stockAnterior - $cantidad,
            'ajuste' => $cantidad,
        };

        try {
            $movimiento = DB::transaction(function () use ($material, $tipoMovimiento, $cantidad, $stockAnterior, $stockNuevo, $validated, $user) {
                // Actualizar stock
                $material->stock_actual = $stockNuevo;
                $material->save();

                // Crear movimiento
                return MovimientoInventario::create([
                    'material_id' => $material->id,
                    'tipo_movimiento' => $tipoMovimiento,
                    'cantidad' => $cantidad,
                    'stock_anterior' => $stockAnterior,
                    'stock_nuevo' => $stockNuevo,
                    'motivo' => $validated['motivo'] ?? null,
                    'usuario_id' => $user->id,
                ]);
            });

            // Recargar relaciones para la respuesta
            $movimiento->load(['material', 'usuario']);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'movimiento' => [
                        'id' => $movimiento->id,
                        'material_id' => $movimiento->material_id,
                        'material' => $movimiento->material->nombre,
                        'tipo_movimiento' => $movimiento->tipo_movimiento,
                        'cantidad' => $movimiento->cantidad,
                        'stock_anterior' => $movimiento->stock_anterior,
                        'stock_nuevo' => $movimiento->stock_nuevo,
                        'motivo' => $movimiento->motivo,
                        'usuario' => $movimiento->usuario->nombre,
                        'fecha' => $movimiento->created_at,
                    ]
                ],
                'message' => 'Movimiento registrado correctamente'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al registrar el movimiento'
            ], 500);
        }
    }
}
