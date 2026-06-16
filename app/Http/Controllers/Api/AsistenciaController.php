<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AsistenciaController extends Controller
{
    /**
     * Obtener registro de asistencia de hoy para el usuario autenticado
     */
    public function hoy(Request $request): JsonResponse
    {
        $hoy = Carbon::today();
        $asistencia = Asistencia::where('usuario_id', $request->user()->id)
            ->whereDate('fecha', $hoy)
            ->first();

        $estado = 'sin_registrar';

        if ($asistencia) {
            if ($asistencia->salida) {
                $estado = 'finalizada';
                if ($asistencia->entrada && $asistencia->salida) {
                    $minutos = $asistencia->entrada->diffInMinutes($asistencia->salida);
                    if ($asistencia->comida_inicio && $asistencia->comida_fin) {
                        $minutos_comida = $asistencia->comida_inicio->diffInMinutes($asistencia->comida_fin);
                        $minutos -= $minutos_comida;
                    }
                    $asistencia->horas_trabajadas = round(max(0, $minutos) / 60, 2);
                }
            } elseif ($asistencia->comida_inicio && !$asistencia->comida_fin) {
                $estado = 'en_comida';
            } else {
                $estado = 'en_jornada';
                if ($asistencia->entrada) {
                    $minutos = $asistencia->entrada->diffInMinutes(Carbon::now());
                    if ($asistencia->comida_inicio && $asistencia->comida_fin) {
                        $minutos_comida = $asistencia->comida_inicio->diffInMinutes($asistencia->comida_fin);
                        $minutos -= $minutos_comida;
                    } elseif ($asistencia->comida_inicio && !$asistencia->comida_fin) {
                        $minutos_comida = $asistencia->comida_inicio->diffInMinutes(Carbon::now());
                        $minutos -= $minutos_comida;
                    }
                    $asistencia->horas_trabajadas = round(max(0, $minutos) / 60, 2);
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'registro' => $asistencia,
                'estado' => $estado,
            ]
        ]);
    }

    /**
     * Registrar entrada de jornada
     */
    public function registrarEntrada(Request $request): JsonResponse
    {
        $request->validate([
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'proyecto_id' => ['nullable', 'exists:proyectos,id'],
        ]);

        $hoy = Carbon::today();
        $user = $request->user();

        // Verificar que no exista registro para hoy
        $existe = Asistencia::where('usuario_id', $user->id)
            ->whereDate('fecha', $hoy)
            ->exists();

        if ($existe) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ya registró entrada hoy',
            ], 422);
        }

        $empresa_id = null;
        if ($request->proyecto_id) {
            $proyecto = \App\Models\Proyecto::find($request->proyecto_id);
            if ($proyecto) {
                $empresa_id = $proyecto->empresa_id;
            }
        }

        $asistencia = Asistencia::create([
            'usuario_id' => $user->id,
            'proyecto_id' => $request->proyecto_id,
            'empresa_id' => $empresa_id,
            'fecha' => $hoy,
            'entrada' => Carbon::now(),
            'latitud_entrada' => $request->latitud,
            'longitud_entrada' => $request->longitud,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $asistencia,
        ], 201);
    }

    /**
     * Iniciar horario de comida
     */
    public function iniciarComida(Request $request): JsonResponse
    {
        $hoy = Carbon::today();
        $asistencia = Asistencia::where('usuario_id', $request->user()->id)
            ->whereDate('fecha', $hoy)
            ->first();

        if (!$asistencia || !$asistencia->entrada) {
            return response()->json([
                'status' => 'error',
                'message' => 'Debe registrar entrada primero',
            ], 422);
        }

        if ($asistencia->comida_inicio) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ya registró inicio de comida',
            ], 422);
        }

        $asistencia->update([
            'comida_inicio' => Carbon::now(),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $asistencia,
        ]);
    }

    /**
     * Finalizar horario de comida
     */
    public function finalizarComida(Request $request): JsonResponse
    {
        $hoy = Carbon::today();
        $asistencia = Asistencia::where('usuario_id', $request->user()->id)
            ->whereDate('fecha', $hoy)
            ->first();

        if (!$asistencia || !$asistencia->entrada) {
            return response()->json([
                'status' => 'error',
                'message' => 'Debe registrar entrada primero',
            ], 422);
        }

        if (!$asistencia->comida_inicio) {
            return response()->json([
                'status' => 'error',
                'message' => 'Debe iniciar comida primero',
            ], 422);
        }

        if ($asistencia->comida_fin) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ya registró fin de comida',
            ], 422);
        }

        $asistencia->update([
            'comida_fin' => Carbon::now(),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $asistencia,
        ]);
    }

    /**
     * Registrar salida de jornada
     */
    public function registrarSalida(Request $request): JsonResponse
    {
        $request->validate([
            'latitud' => ['nullable', 'numeric'],
            'longitud' => ['nullable', 'numeric'],
        ]);

        $hoy = Carbon::today();
        $asistencia = Asistencia::where('usuario_id', $request->user()->id)
            ->whereDate('fecha', $hoy)
            ->first();

        if (!$asistencia || !$asistencia->entrada) {
            return response()->json([
                'status' => 'error',
                'message' => 'Debe registrar entrada primero',
            ], 422);
        }

        if ($asistencia->salida) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ya registró salida hoy',
            ], 422);
        }

        if ($asistencia->comida_inicio && !$asistencia->comida_fin) {
            return response()->json([
                'status' => 'error',
                'message' => 'Debe finalizar horario de comida antes de registrar salida',
            ], 422);
        }

        $now = Carbon::now();

        $asistencia->update([
            'salida' => $now,
            'latitud_salida' => $request->latitud,
            'longitud_salida' => $request->longitud,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $asistencia,
        ]);
    }

    /**
     * Historial de asistencia
     */
    public function historial(Request $request): JsonResponse
    {
        $semana = $request->query('semana', 'actual');
        $query = Asistencia::where('usuario_id', $request->user()->id)
            ->with('proyecto:id,nombre');

        if ($semana === 'actual') {
            $inicio = Carbon::now()->startOfWeek();
            $fin = Carbon::now()->endOfWeek();
        } else {
            $inicio = Carbon::now()->subWeeks(4)->startOfWeek();
            $fin = Carbon::now()->endOfWeek();
        }

        $registros = $query->whereBetween('fecha', [$inicio, $fin])
            ->orderBy('fecha', 'desc')
            ->get();

        $registros->transform(function ($registro) {
            $horas_trabajadas = 0;
            if ($registro->entrada && $registro->salida) {
                $minutos = $registro->entrada->diffInMinutes($registro->salida);
                
                if ($registro->comida_inicio && $registro->comida_fin) {
                    $minutos_comida = $registro->comida_inicio->diffInMinutes($registro->comida_fin);
                    $minutos -= $minutos_comida;
                }
                
                $horas_trabajadas = round($minutos / 60, 2);
            }
            
            $registro->horas_trabajadas = max(0, $horas_trabajadas);
            return $registro;
        });

        return response()->json([
            'status' => 'success',
            'data' => $registros,
            'message' => 'Historial de asistencia obtenido correctamente.',
        ]);
    }
}
