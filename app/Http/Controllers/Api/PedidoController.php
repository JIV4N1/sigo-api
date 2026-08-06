<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pedido\CambiarEstadoPedidoRequest;
use App\Http\Requests\Pedido\StorePedidoRequest;
use App\Http\Requests\Pedido\UpdatePedidoRequest;
use App\Http\Traits\AdminBypassTrait;
use App\Models\Pedido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador de pedidos de venta para la API SIGO.
 *
 * Un pedido es un registro de seguimiento de una solicitud de compra de un
 * cliente: NO valida ni descuenta stock, y es independiente de Cotización
 * y Venta (sin relación entre ellos por ahora).
 *
 * Lectura (index/show/search): administrador, gerente, supervisor, ingeniero.
 * Escritura (store/update/cambiarEstado/destroy): solo administrador/gerente.
 */
class PedidoController extends Controller
{
    use AdminBypassTrait;

    private const IVA_TASA = 0.16;

    /**
     * Listar los pedidos de la empresa activa del usuario autenticado.
     * Filtros: ?cliente_id=, ?estado=, ?desde=, ?hasta= (sobre fecha_pedido).
     */
    public function index(Request $request): JsonResponse
    {
        if ($error = $this->verificarLectura($request)) {
            return $error;
        }

        try {
            $query = Pedido::where('empresa_id', $this->getEmpresaId($request))
                ->where('activo', true)
                ->with(['cliente:id,razon_social', 'usuario:id,nombre']);

            if ($request->filled('cliente_id')) {
                $query->where('cliente_id', $request->cliente_id);
            }

            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->filled('desde')) {
                $query->whereDate('fecha_pedido', '>=', $request->query('desde'));
            }

            if ($request->filled('hasta')) {
                $query->whereDate('fecha_pedido', '<=', $request->query('hasta'));
            }

            $paginado = $query->orderBy('fecha_pedido', 'desc')->paginate(15);

            $paginado->setCollection(
                $paginado->getCollection()->map(fn (Pedido $p) => $this->formatearPedido($p))
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Pedidos obtenidos correctamente.',
                'data'    => $paginado,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener los pedidos.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Buscar pedidos por folio o por cliente (razón social o RFC).
     * Query param: ?term=
     */
    public function search(Request $request): JsonResponse
    {
        if ($error = $this->verificarLectura($request)) {
            return $error;
        }

        try {
            $term = $request->query('term');

            $query = Pedido::where('empresa_id', $this->getEmpresaId($request))
                ->where('activo', true)
                ->with(['cliente:id,razon_social', 'usuario:id,nombre']);

            if ($term) {
                $query->where(function ($q) use ($term) {
                    $q->where('folio', 'ILIKE', "%{$term}%")
                      ->orWhereHas('cliente', function ($qCliente) use ($term) {
                          $qCliente->where('razon_social', 'ILIKE', "%{$term}%")
                                   ->orWhere('rfc', 'ILIKE', "%{$term}%");
                      });
                });
            }

            $paginado = $query->orderBy('fecha_pedido', 'desc')->paginate(15);

            $paginado->setCollection(
                $paginado->getCollection()->map(fn (Pedido $p) => $this->formatearPedido($p))
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Búsqueda realizada correctamente.',
                'data'    => $paginado,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al buscar pedidos.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Mostrar el detalle de un pedido de la empresa activa del usuario autenticado.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        if ($error = $this->verificarLectura($request)) {
            return $error;
        }

        try {
            $pedido = Pedido::with(['cliente', 'usuario:id,nombre', 'detalles.material'])->find($id);

            if (! $pedido) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Pedido no encontrado.',
                ], 404);
            }

            if ($pedido->empresa_id !== $this->getEmpresaId($request)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No tiene permisos para ver este pedido.',
                ], 403);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Pedido obtenido correctamente.',
                'data'    => $this->formatearPedido($pedido, true),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener el pedido.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Crear un nuevo pedido de venta con sus detalles.
     * No valida ni descuenta stock (es solo un registro de seguimiento).
     */
    public function store(StorePedidoRequest $request): JsonResponse
    {
        if ($error = $this->verificarEscritura($request)) {
            return $error;
        }

        $user = $request->user();
        $empresaId = $this->getEmpresaId($request);

        try {
            $pedido = DB::transaction(function () use ($request, $user, $empresaId) {
                // Calcular subtotal, iva (16%) y total a partir de los detalles
                $subtotal = collect($request->detalles)->sum(
                    fn ($d) => $d['cantidad'] * $d['precio_unitario']
                );
                $iva = round($subtotal * self::IVA_TASA, 2);
                $total = round($subtotal + $iva, 2);

                // Generar folio de pedido (formato PED-000001)
                $folio = 'PED-' . str_pad(Pedido::count() + 1, 6, '0', STR_PAD_LEFT);

                $pedido = Pedido::create([
                    'empresa_id'     => $empresaId,
                    'folio'          => $folio,
                    'cliente_id'     => $request->cliente_id,
                    'usuario_id'     => $user->id,
                    'fecha_pedido'   => $request->filled('fecha_pedido') ? $request->fecha_pedido : now()->toDateString(),
                    'fecha_entrega'  => $request->fecha_entrega,
                    'estado'         => 'pendiente',
                    'subtotal'       => round($subtotal, 2),
                    'iva'            => $iva,
                    'total'          => $total,
                    'observaciones'  => $request->observaciones,
                    'activo'         => true,
                ]);

                foreach ($request->detalles as $detalle) {
                    $pedido->detalles()->create([
                        'material_id'     => $detalle['material_id'],
                        'cantidad'        => $detalle['cantidad'],
                        'precio_unitario' => $detalle['precio_unitario'],
                        'subtotal'        => $detalle['cantidad'] * $detalle['precio_unitario'],
                    ]);
                }

                return $pedido;
            });

            $pedido->load(['cliente', 'usuario:id,nombre', 'detalles.material']);

            return response()->json([
                'status'  => 'success',
                'message' => 'Pedido creado correctamente.',
                'data'    => $this->formatearPedido($pedido, true),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al crear el pedido.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Actualizar un pedido existente. Si se envían "detalles", se reemplazan
     * todas las partidas anteriores y se recalculan los totales.
     */
    public function update(int $id, UpdatePedidoRequest $request): JsonResponse
    {
        if ($error = $this->verificarEscritura($request)) {
            return $error;
        }

        try {
            $pedido = Pedido::where('empresa_id', $this->getEmpresaId($request))->find($id);

            if (! $pedido) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Pedido no encontrado.',
                ], 404);
            }

            DB::transaction(function () use ($request, $pedido) {
                $datos = $request->only(['cliente_id', 'fecha_pedido', 'fecha_entrega', 'observaciones']);

                if ($request->filled('detalles')) {
                    $subtotal = collect($request->detalles)->sum(
                        fn ($d) => $d['cantidad'] * $d['precio_unitario']
                    );
                    $iva = round($subtotal * self::IVA_TASA, 2);
                    $total = round($subtotal + $iva, 2);

                    $datos['subtotal'] = round($subtotal, 2);
                    $datos['iva'] = $iva;
                    $datos['total'] = $total;

                    $pedido->detalles()->delete();

                    foreach ($request->detalles as $detalle) {
                        $pedido->detalles()->create([
                            'material_id'     => $detalle['material_id'],
                            'cantidad'        => $detalle['cantidad'],
                            'precio_unitario' => $detalle['precio_unitario'],
                            'subtotal'        => $detalle['cantidad'] * $detalle['precio_unitario'],
                        ]);
                    }
                }

                $pedido->update($datos);
            });

            $pedido->load(['cliente', 'usuario:id,nombre', 'detalles.material']);

            return response()->json([
                'status'  => 'success',
                'message' => 'Pedido actualizado correctamente.',
                'data'    => $this->formatearPedido($pedido, true),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al actualizar el pedido.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * PATCH /api/pedidos/{id}/estado
     * Cambia el estado del pedido. Sin efectos en inventario.
     */
    public function cambiarEstado(int $id, CambiarEstadoPedidoRequest $request): JsonResponse
    {
        if ($error = $this->verificarEscritura($request)) {
            return $error;
        }

        try {
            $pedido = Pedido::where('empresa_id', $this->getEmpresaId($request))->find($id);

            if (! $pedido) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Pedido no encontrado.',
                ], 404);
            }

            $pedido->update(['estado' => $request->estado]);
            $pedido->load(['cliente', 'usuario:id,nombre', 'detalles.material']);

            return response()->json([
                'status'  => 'success',
                'message' => 'Estado del pedido actualizado correctamente.',
                'data'    => $this->formatearPedido($pedido, true),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al cambiar el estado del pedido.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * DELETE /api/pedidos/{id}
     * Desactiva el pedido (soft delete: activo = false), sin eliminar el registro.
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        if ($error = $this->verificarEscritura($request)) {
            return $error;
        }

        try {
            $pedido = Pedido::where('empresa_id', $this->getEmpresaId($request))->find($id);

            if (! $pedido) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Pedido no encontrado.',
                ], 404);
            }

            if (! $pedido->activo) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'El pedido ya se encuentra desactivado.',
                ], 422);
            }

            $pedido->update(['activo' => false]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Pedido desactivado correctamente.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al desactivar el pedido.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Lectura: administrador, gerente, supervisor, ingeniero.
     */
    private function verificarLectura(Request $request): ?JsonResponse
    {
        if (! $request->user()->tieneRol(['administrador', 'gerente', 'supervisor', 'ingeniero'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para consultar pedidos.',
            ], 403);
        }

        return null;
    }

    /**
     * Escritura: solo administrador/gerente.
     */
    private function verificarEscritura(Request $request): ?JsonResponse
    {
        if (! $request->user()->tieneRol(['administrador', 'gerente'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para modificar pedidos.',
            ], 403);
        }

        return null;
    }

    /**
     * Da forma al arreglo de salida de un pedido.
     * Con $conDetalle=true incluye los detalles con su material.
     */
    private function formatearPedido(Pedido $pedido, bool $conDetalle = false): array
    {
        $data = [
            'id'             => $pedido->id,
            'folio'          => $pedido->folio,
            'cliente'        => $pedido->cliente ? [
                'id'     => $pedido->cliente->id,
                'nombre' => $pedido->cliente->razon_social,
            ] : null,
            'usuario'        => $pedido->usuario ? [
                'id'     => $pedido->usuario->id,
                'nombre' => $pedido->usuario->nombre,
            ] : null,
            'fecha_pedido'   => $pedido->fecha_pedido ? $pedido->fecha_pedido->format('Y-m-d') : null,
            'fecha_entrega'  => $pedido->fecha_entrega ? $pedido->fecha_entrega->format('Y-m-d') : null,
            'estado'         => $pedido->estado,
            'subtotal'       => (float) $pedido->subtotal,
            'iva'            => (float) $pedido->iva,
            'total'          => (float) $pedido->total,
            'observaciones'  => $pedido->observaciones,
            'activo'         => $pedido->activo,
            'created_at'     => $pedido->created_at,
            'updated_at'     => $pedido->updated_at,
        ];

        if ($conDetalle) {
            $data['detalles'] = $pedido->detalles->map(fn ($detalle) => [
                'material_id'     => $detalle->material_id,
                'cantidad'        => (float) $detalle->cantidad,
                'precio_unitario' => (float) $detalle->precio_unitario,
                'subtotal'        => (float) $detalle->subtotal,
                'material'        => $detalle->material ? [
                    'id'            => $detalle->material->id,
                    'nombre'        => $detalle->material->nombre,
                    'codigo'        => $detalle->material->codigo,
                    'unidad_medida' => $detalle->material->unidad_medida,
                ] : null,
            ])->values();
        }

        return $data;
    }
}
