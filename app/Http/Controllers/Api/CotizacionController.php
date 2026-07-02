<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cotizacion;
use App\Models\Material;
use App\Models\MovimientoInventario;
use App\Models\PartidaCotizacion;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CotizacionController extends Controller
{
    /**
     * GET /api/cotizaciones
     */
    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $cotizaciones = Cotizacion::with(['cliente:id,razon_social', 'usuario:id,name as nombre'])
            ->where('empresa_id', $empresaId)
            ->orderBy('created_at', 'desc')
            ->get([
                'id', 'folio', 'cliente_id', 'usuario_id', 'fecha', 'subtotal', 'iva', 'total', 'estado', 'observaciones', 'created_at'
            ]);

        $cotizacionesData = $cotizaciones->map(function ($cotizacion) {
            return [
                'id' => $cotizacion->id,
                'folio' => $cotizacion->folio,
                'cliente' => $cotizacion->cliente ? [
                    'id' => $cotizacion->cliente->id,
                    'nombre' => $cotizacion->cliente->razon_social
                ] : null,
                'usuario' => $cotizacion->usuario ? $cotizacion->usuario->nombre : null,
                'fecha' => $cotizacion->fecha ? $cotizacion->fecha->format('Y-m-d') : null,
                'subtotal' => (float) $cotizacion->subtotal,
                'iva' => (float) $cotizacion->iva,
                'total' => (float) $cotizacion->total,
                'estado' => $cotizacion->estado,
                'observaciones' => $cotizacion->observaciones,
                'created_at' => $cotizacion->created_at,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'cotizaciones' => $cotizacionesData
            ],
            'message' => 'Cotizaciones obtenidas correctamente'
        ]);
    }

    /**
     * GET /api/cotizaciones/search?busqueda=
     */
    public function search(Request $request)
    {
        $empresaId = $request->user()->empresa_id;
        $busqueda = $request->query('busqueda');

        $query = Cotizacion::with(['cliente:id,razon_social', 'usuario:id,name as nombre'])
            ->where('empresa_id', $empresaId);

        if ($busqueda) {
            $query->where(function ($q) use ($busqueda) {
                $q->where('folio', 'ILIKE', "%{$busqueda}%")
                  ->orWhereHas('cliente', function ($qCliente) use ($busqueda) {
                      $qCliente->where('razon_social', 'ILIKE', "%{$busqueda}%");
                  });
            });
        }

        $cotizaciones = $query->orderBy('created_at', 'desc')->get([
            'id', 'folio', 'cliente_id', 'usuario_id', 'fecha', 'subtotal', 'iva', 'total', 'estado', 'observaciones', 'created_at'
        ]);

        $cotizacionesData = $cotizaciones->map(function ($cotizacion) {
            return [
                'id' => $cotizacion->id,
                'folio' => $cotizacion->folio,
                'cliente' => $cotizacion->cliente ? [
                    'id' => $cotizacion->cliente->id,
                    'nombre' => $cotizacion->cliente->razon_social
                ] : null,
                'usuario' => $cotizacion->usuario ? $cotizacion->usuario->nombre : null,
                'fecha' => $cotizacion->fecha ? $cotizacion->fecha->format('Y-m-d') : null,
                'subtotal' => (float) $cotizacion->subtotal,
                'iva' => (float) $cotizacion->iva,
                'total' => (float) $cotizacion->total,
                'estado' => $cotizacion->estado,
                'observaciones' => $cotizacion->observaciones,
                'created_at' => $cotizacion->created_at,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'cotizaciones' => $cotizacionesData
            ],
            'message' => 'Búsqueda realizada correctamente'
        ]);
    }

    /**
     * GET /api/cotizaciones/generar-folio
     */
    public function generarFolio(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        // Obtener el último ID o último folio de la empresa para generar el siguiente
        $ultimoId = Cotizacion::max('id') ?? 0;
        $siguienteId = $ultimoId + 1;
        $folio = 'COT-' . str_pad($siguienteId, 3, '0', STR_PAD_LEFT);

        return response()->json([
            'status' => 'success',
            'data' => [
                'folio' => $folio
            ],
            'message' => 'Folio generado correctamente'
        ]);
    }

    /**
     * POST /api/cotizaciones
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'fecha' => 'required|date',
            'subtotal' => 'required|numeric|min:0',
            'iva' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'estado' => 'required|in:Pendiente,Aprobada,Rechazada,ConvertidaAVenta',
            'observaciones' => 'nullable|string',
            'detalles' => 'required|array|min:1',
            'detalles.*.material_id' => 'required|exists:materiales,id',
            'detalles.*.cantidad' => 'required|numeric|min:0.01',
            'detalles.*.precio_unitario' => 'required|numeric|min:0',
            'detalles.*.subtotal' => 'required|numeric|min:0',
        ]);

        $user = $request->user();
        $empresaId = $user->empresa_id;

        try {
            $cotizacionResult = DB::transaction(function () use ($validated, $user, $empresaId, $request) {
                $folio = $request->input('folio');
                if (!$folio) {
                    $ultimoId = Cotizacion::max('id') ?? 0;
                    $siguienteId = $ultimoId + 1;
                    $folio = 'COT-' . str_pad($siguienteId, 3, '0', STR_PAD_LEFT);
                }

                $cotizacion = Cotizacion::create([
                    'folio' => $folio,
                    'cliente_id' => $validated['cliente_id'],
                    'usuario_id' => $user->id,
                    'empresa_id' => $empresaId,
                    'fecha' => $validated['fecha'],
                    'subtotal' => $validated['subtotal'],
                    'iva' => $validated['iva'],
                    'total' => $validated['total'],
                    'estado' => $validated['estado'],
                    'observaciones' => $validated['observaciones'] ?? null,
                ]);

                $partidas = [];
                foreach ($validated['detalles'] as $detalle) {
                    $partidas[] = new PartidaCotizacion([
                        'material_id' => $detalle['material_id'],
                        'cantidad' => $detalle['cantidad'],
                        'precio_unitario' => $detalle['precio_unitario'],
                        'subtotal' => $detalle['subtotal'],
                    ]);
                }
                $cotizacion->partidas()->saveMany($partidas);

                return $cotizacion;
            });

            $cotizacionResult->load(['cliente', 'partidas.material']);

            $detallesData = $cotizacionResult->partidas->map(function ($partida) {
                return [
                    'material_id' => $partida->material_id,
                    'cantidad' => (float) $partida->cantidad,
                    'precio_unitario' => (float) $partida->precio_unitario,
                    'subtotal' => (float) $partida->subtotal,
                    'material' => $partida->material ? $partida->material->nombre : null
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'cotizacion' => [
                        'id' => $cotizacionResult->id,
                        'folio' => $cotizacionResult->folio,
                        'cliente' => $cotizacionResult->cliente ? [
                            'id' => $cotizacionResult->cliente->id,
                            'nombre' => $cotizacionResult->cliente->razon_social
                        ] : null,
                        'fecha' => $cotizacionResult->fecha ? $cotizacionResult->fecha->format('Y-m-d') : null,
                        'subtotal' => (float) $cotizacionResult->subtotal,
                        'iva' => (float) $cotizacionResult->iva,
                        'total' => (float) $cotizacionResult->total,
                        'estado' => $cotizacionResult->estado,
                        'detalles' => $detallesData
                    ]
                ],
                'message' => 'Cotización creada correctamente'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al crear la cotización',
            ], 500);
        }
    }

    /**
     * GET /api/cotizaciones/{id}
     */
    public function show(Request $request, $id)
    {
        $empresaId = $request->user()->empresa_id;
        $cotizacion = Cotizacion::with(['cliente', 'partidas.material'])->find($id);

        if (!$cotizacion || $cotizacion->empresa_id !== $empresaId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cotización no encontrada o acceso denegado'
            ], 403);
        }

        $detallesData = $cotizacion->partidas->map(function ($partida) {
            return [
                'material_id' => $partida->material_id,
                'cantidad' => (float) $partida->cantidad,
                'precio_unitario' => (float) $partida->precio_unitario,
                'subtotal' => (float) $partida->subtotal,
                'material' => $partida->material ? $partida->material->nombre : null
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'cotizacion' => [
                    'id' => $cotizacion->id,
                    'folio' => $cotizacion->folio,
                    'cliente' => $cotizacion->cliente ? [
                        'id' => $cotizacion->cliente->id,
                        'nombre' => $cotizacion->cliente->razon_social
                    ] : null,
                    'fecha' => $cotizacion->fecha ? $cotizacion->fecha->format('Y-m-d') : null,
                    'subtotal' => (float) $cotizacion->subtotal,
                    'iva' => (float) $cotizacion->iva,
                    'total' => (float) $cotizacion->total,
                    'estado' => $cotizacion->estado,
                    'observaciones' => $cotizacion->observaciones,
                    'detalles' => $detallesData
                ]
            ],
            'message' => 'Cotización obtenida correctamente'
        ]);
    }

    /**
     * POST /api/cotizaciones/{id}/convertir-venta
     */
    public function convertirAVenta(Request $request, $id)
    {
        $empresaId = $request->user()->empresa_id;
        $user = $request->user();

        $cotizacion = Cotizacion::with('partidas')->find($id);

        if (!$cotizacion || $cotizacion->empresa_id !== $empresaId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cotización no encontrada o acceso denegado'
            ], 403);
        }

        if ($cotizacion->estado === 'ConvertidaAVenta') {
            return response()->json([
                'status' => 'error',
                'message' => 'La cotización ya fue convertida a venta'
            ], 422);
        }

        // Verificar stock suficiente
        foreach ($cotizacion->partidas as $partida) {
            $material = Material::find($partida->material_id);
            if (!$material || $material->stock_actual < $partida->cantidad) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Stock insuficiente para el material ID: {$partida->material_id}. Requerido: {$partida->cantidad}, Disponible: " . ($material ? $material->stock_actual : 0)
                ], 422);
            }
        }

        try {
            $venta = DB::transaction(function () use ($cotizacion, $user, $empresaId) {
                // Generar folio de venta basado en el último
                $ultimoIdVenta = Venta::max('id') ?? 0;
                $siguienteIdVenta = $ultimoIdVenta + 1;
                $folioVenta = 'VENTA-' . str_pad($siguienteIdVenta, 3, '0', STR_PAD_LEFT);

                // Crear Venta
                $venta = Venta::create([
                    'folio' => $folioVenta,
                    'cliente_id' => $cotizacion->cliente_id,
                    'usuario_id' => $user->id,
                    'cotizacion_id' => $cotizacion->id,
                    'fecha' => now()->format('Y-m-d'),
                    'subtotal' => $cotizacion->subtotal,
                    'iva' => $cotizacion->iva,
                    'total' => $cotizacion->total,
                    'metodo_pago' => 'Por Definir',
                    'estado' => 'Completada',
                    'observaciones' => 'Generada a partir de cotización ' . $cotizacion->folio,
                    'empresa_id' => $empresaId,
                ]);

                // Copiar partidas y descontar stock
                foreach ($cotizacion->partidas as $partida) {
                    $venta->partidas()->create([
                        'material_id' => $partida->material_id,
                        'cantidad' => $partida->cantidad,
                        'precio_unitario' => $partida->precio_unitario,
                        'subtotal' => $partida->subtotal,
                    ]);

                    $material = Material::lockForUpdate()->find($partida->material_id);
                    $stockAnterior = $material->stock_actual;
                    $stockNuevo = $stockAnterior - $partida->cantidad;

                    $material->stock_actual = $stockNuevo;
                    $material->save();

                    // Crear movimiento de inventario
                    MovimientoInventario::create([
                        'material_id' => $material->id,
                        'tipo_movimiento' => 'Salida',
                        'cantidad' => $partida->cantidad,
                        'stock_anterior' => $stockAnterior,
                        'stock_nuevo' => $stockNuevo,
                        'motivo' => 'Venta a partir de cotización ' . $cotizacion->folio,
                        'usuario_id' => $user->id,
                    ]);
                }

                // Cambiar estado cotización
                $cotizacion->estado = 'ConvertidaAVenta';
                $cotizacion->save();

                return $venta;
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'venta' => [
                        'id' => $venta->id,
                        'folio' => $venta->folio,
                        'total' => (float) $venta->total,
                    ]
                ],
                'message' => 'Cotización convertida a venta exitosamente'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al convertir la cotización a venta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/cotizaciones/{id}/pdf
     */
    public function datosPdf(Request $request, $id)
    {
        try {
            $empresaId = $request->user()->empresa_id;
            
            // Usando Modelos de Empresa si existe, si no, se devuelve lo básico
            $cotizacion = Cotizacion::with(['cliente', 'partidas.material'])->find($id);

            if (!$cotizacion || $cotizacion->empresa_id !== $empresaId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cotización no encontrada o acceso denegado'
                ], 404);
            }

            $empresa = \App\Models\Empresa::find($empresaId);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'empresa' => $empresa ? $empresa->toArray() : null,
                    'cotizacion' => [
                        'id' => $cotizacion->id,
                        'folio' => $cotizacion->folio,
                        'fecha' => $cotizacion->fecha ? $cotizacion->fecha->format('Y-m-d') : null,
                        'subtotal' => (float) $cotizacion->subtotal,
                        'iva' => (float) $cotizacion->iva,
                        'total' => (float) $cotizacion->total,
                        'estado' => $cotizacion->estado,
                        'observaciones' => $cotizacion->observaciones,
                    ],
                    'cliente' => $cotizacion->cliente ? $cotizacion->cliente->toArray() : null,
                    'detalles' => $cotizacion->partidas->map(function ($partida) {
                        return [
                            'cantidad' => (float) $partida->cantidad,
                            'precio_unitario' => (float) $partida->precio_unitario,
                            'subtotal' => (float) $partida->subtotal,
                            'material' => $partida->material ? $partida->material->nombre : null,
                            'unidad_medida' => $partida->material ? $partida->material->unidad_medida : null,
                            'codigo' => $partida->material ? $partida->material->codigo : null,
                        ];
                    })
                ],
                'message' => 'Datos para PDF obtenidos correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno al obtener datos para PDF: ' . $e->getMessage()
            ], 500);
        }
    }
}
