<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Venta\StoreVentaRequest;
use App\Http\Traits\AdminBypassTrait;
use App\Models\Material;
use App\Models\MovimientoInventario;
use App\Models\Venta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador de ventas para la API SIGO.
 *
 * Gestiona la consulta de ventas (generadas por conversión de cotización
 * o de forma directa) y la creación de ventas directas con descuento
 * automático de inventario.
 */
class VentaController extends Controller
{
    use AdminBypassTrait;

    private const IVA_TASA = 0.16;

    /**
     * Listar las ventas de la empresa activa del usuario autenticado.
     * Filtros: ?cliente_id=, ?desde=, ?hasta= (sobre fecha), ?estado=
     * Solo administrador/gerente.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->tieneRol(['administrador', 'gerente'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para consultar las ventas.',
            ], 403);
        }

        try {
            $query = Venta::where('empresa_id', $this->getEmpresaId($request))
                ->with([
                    'cliente:id,razon_social',
                    'usuario:id,nombre',
                    'cotizacion:id,folio',
                    'partidas.material:id,nombre,codigo,unidad_medida',
                ]);

            if ($request->filled('cliente_id')) {
                $query->where('cliente_id', $request->cliente_id);
            }

            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->filled('desde')) {
                $query->whereDate('fecha', '>=', $request->query('desde'));
            }

            if ($request->filled('hasta')) {
                $query->whereDate('fecha', '<=', $request->query('hasta'));
            }

            $paginado = $query->orderBy('fecha', 'desc')->paginate(15);

            $paginado->setCollection(
                $paginado->getCollection()->map(fn (Venta $v) => $this->formatearVenta($v, true))
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Ventas obtenidas correctamente.',
                'data'    => $paginado,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener las ventas.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Mostrar el detalle de una venta de la empresa activa del usuario autenticado.
     * Solo administrador/gerente.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->tieneRol(['administrador', 'gerente'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para consultar esta venta.',
            ], 403);
        }

        try {
            $venta = Venta::with(['cliente', 'usuario:id,nombre', 'cotizacion:id,folio', 'partidas.material'])->find($id);

            if (! $venta) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Venta no encontrada.',
                ], 404);
            }

            if ($venta->empresa_id !== $this->getEmpresaId($request)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No tiene permisos para ver esta venta.',
                ], 403);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Venta obtenida correctamente.',
                'data'    => $this->formatearVenta($venta, true),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener la venta.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Buscar ventas por folio o por cliente (razón social o RFC).
     * Query param: ?term= . Solo administrador/gerente.
     */
    public function search(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->tieneRol(['administrador', 'gerente'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para buscar ventas.',
            ], 403);
        }

        try {
            $term = $request->query('term');

            $query = Venta::where('empresa_id', $this->getEmpresaId($request))
                ->with([
                    'cliente:id,razon_social',
                    'usuario:id,nombre',
                    'cotizacion:id,folio',
                    'partidas.material:id,nombre,codigo,unidad_medida',
                ]);

            if ($term) {
                $query->where(function ($q) use ($term) {
                    $q->where('folio', 'ILIKE', "%{$term}%")
                      ->orWhereHas('cliente', function ($qCliente) use ($term) {
                          $qCliente->where('razon_social', 'ILIKE', "%{$term}%")
                                   ->orWhere('rfc', 'ILIKE', "%{$term}%");
                      });
                });
            }

            $paginado = $query->orderBy('fecha', 'desc')->paginate(15);

            $paginado->setCollection(
                $paginado->getCollection()->map(fn (Venta $v) => $this->formatearVenta($v, true))
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Búsqueda realizada correctamente.',
                'data'    => $paginado,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al buscar ventas.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Retorna el siguiente folio disponible para una venta (formato VEN-000001).
     * Solo administrador/gerente.
     */
    public function generarFolio(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->tieneRol(['administrador', 'gerente'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para generar un folio de venta.',
            ], 403);
        }

        $folio = 'VEN-' . str_pad(Venta::count() + 1, 6, '0', STR_PAD_LEFT);

        return response()->json([
            'status'  => 'success',
            'message' => 'Folio generado correctamente.',
            'data'    => [
                'folio' => $folio,
            ],
        ], 200);
    }

    /**
     * Crear una venta directa (sin pasar por una cotización), descontando
     * inventario automáticamente. Solo administrador.
     */
    public function store(StoreVentaRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->tieneRol(['administrador'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para registrar ventas.',
            ], 403);
        }

        $empresaId = $this->getEmpresaId($request);

        // Verificar stock suficiente antes de iniciar la transacción
        foreach ($request->detalles as $detalle) {
            $material = Material::find($detalle['material_id']);
            if (! $material || $material->stock_actual < $detalle['cantidad']) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "Stock insuficiente para el material ID: {$detalle['material_id']}. Requerido: {$detalle['cantidad']}, Disponible: " . ($material ? $material->stock_actual : 0),
                ], 422);
            }
        }

        try {
            $venta = DB::transaction(function () use ($request, $user, $empresaId) {
                // Calcular subtotal, iva (16%) y total a partir de los detalles
                $subtotal = collect($request->detalles)->sum(
                    fn ($d) => $d['cantidad'] * $d['precio_unitario']
                );
                $iva = round($subtotal * self::IVA_TASA, 2);
                $total = round($subtotal + $iva, 2);

                // Generar folio de venta (formato VEN-000001, igual que CotizacionController::convertirAVenta)
                $folio = 'VEN-' . str_pad(Venta::count() + 1, 6, '0', STR_PAD_LEFT);

                $venta = Venta::create([
                    'folio'         => $folio,
                    'cliente_id'    => $request->cliente_id,
                    'usuario_id'    => $user->id,
                    'cotizacion_id' => null,
                    'fecha'         => $request->fecha,
                    'subtotal'      => round($subtotal, 2),
                    'iva'           => $iva,
                    'total'         => $total,
                    'metodo_pago'   => $request->metodo_pago,
                    'estado'        => 'Completada',
                    'observaciones' => $request->observaciones,
                    'empresa_id'    => $empresaId,
                ]);

                foreach ($request->detalles as $detalle) {
                    $venta->partidas()->create([
                        'material_id'     => $detalle['material_id'],
                        'cantidad'        => $detalle['cantidad'],
                        'precio_unitario' => $detalle['precio_unitario'],
                        'subtotal'        => $detalle['cantidad'] * $detalle['precio_unitario'],
                    ]);

                    // Descontar stock con bloqueo para evitar condiciones de carrera
                    $material = Material::lockForUpdate()->find($detalle['material_id']);
                    $stockAnterior = $material->stock_actual;
                    $stockNuevo = $stockAnterior - $detalle['cantidad'];

                    $material->stock_actual = $stockNuevo;
                    $material->save();

                    MovimientoInventario::create([
                        'material_id'     => $material->id,
                        'tipo_movimiento' => 'Salida',
                        'cantidad'        => $detalle['cantidad'],
                        'stock_anterior'  => $stockAnterior,
                        'stock_nuevo'     => $stockNuevo,
                        'motivo'          => "Venta directa Folio: {$folio}",
                        'usuario_id'      => $user->id,
                    ]);
                }

                return $venta;
            });

            $venta->load(['cliente', 'usuario:id,nombre', 'partidas.material']);

            return response()->json([
                'status'  => 'success',
                'message' => 'Venta registrada correctamente.',
                'data'    => $this->formatearVenta($venta, true),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al registrar la venta.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Da forma al arreglo de salida de una venta.
     * Con $conDetalle=true incluye los detalles con su material.
     */
    private function formatearVenta(Venta $venta, bool $conDetalle = false): array
    {
        $data = [
            'id'            => $venta->id,
            'folio'         => $venta->folio,
            'cliente'       => $venta->cliente ? [
                'id'     => $venta->cliente->id,
                'nombre' => $venta->cliente->razon_social,
            ] : null,
            'usuario'       => $venta->usuario ? [
                'id'     => $venta->usuario->id,
                'nombre' => $venta->usuario->nombre,
            ] : null,
            'cotizacion'    => $venta->cotizacion ? [
                'id'    => $venta->cotizacion->id,
                'folio' => $venta->cotizacion->folio,
            ] : null,
            'fecha'         => $venta->fecha ? $venta->fecha->format('Y-m-d') : null,
            'subtotal'      => (float) $venta->subtotal,
            'iva'           => (float) $venta->iva,
            'total'         => (float) $venta->total,
            'metodo_pago'   => $venta->metodo_pago,
            'estado'        => $venta->estado,
            'observaciones' => $venta->observaciones,
            'created_at'    => $venta->created_at,
        ];

        if ($conDetalle) {
            $data['detalles'] = $venta->partidas->map(fn ($partida) => [
                'material_id'     => $partida->material_id,
                'cantidad'        => (float) $partida->cantidad,
                'precio_unitario' => (float) $partida->precio_unitario,
                'subtotal'        => (float) $partida->subtotal,
                'material'        => $partida->material ? [
                    'id'            => $partida->material->id,
                    'nombre'        => $partida->material->nombre,
                    'codigo'        => $partida->material->codigo,
                    'unidad_medida' => $partida->material->unidad_medida,
                ] : null,
            ])->values();
        }

        return $data;
    }
}
