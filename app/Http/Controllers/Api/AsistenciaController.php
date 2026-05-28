<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Asistencia\RegistrarEntradaRequest;
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
            } elseif ($asistencia->comida_inicio && !$asistencia->comida_fin) {
                $estado = 'en_comida';
            } else {
                $estado = 'en_jornada';
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $asistencia,
            'estado' => $estado,
            'message' => 'Asistencia de hoy obtenida correctamente.',
        ]);
    }

    /**
     * Registrar entrada de jornada
     */
    public function registrarEntrada(RegistrarEntradaRequest $request): JsonResponse
    {
        $hoy = Carbon::today();
        $user = $request->user();

        // Verificar que no exista registro para hoy
        $existe = Asistencia::where('usuario_id', $user->id)
            ->whereDate('fecha', $hoy)
            ->exists();

        if ($existe) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ya existe un registro de asistencia para el día de hoy.',
            ], 422);
        }

        // Verificar si omitió salida ayer
        $ayer = Carbon::yesterday();
        $asistenciaAyer = Asistencia::where('usuario_id', $user->id)
            ->whereDate('fecha', $ayer)
            ->first();

        $warning = null;
        if ($asistenciaAyer && !$asistenciaAyer->salida) {
            $warning = 'No registró salida el día anterior.';
        }

        $asistencia = Asistencia::create([
            'usuario_id' => $user->id,
            'proyecto_id' => $request->proyecto_id,
            'fecha' => $hoy,
            'entrada' => Carbon::now(),
            'latitud_entrada' => $request->latitud,
            'longitud_entrada' => $request->longitud,
        ]);

        $response = [
            'status' => 'success',
            'data' => $asistencia,
            'message' => 'Entrada registrada correctamente.',
        ];

        if ($warning) {
            $response['warning'] = $warning;
        }

        return response()->json($response, 201);
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
                'message' => 'Debe registrar su entrada primero.',
            ], 422);
        }

        if ($asistencia->comida_inicio) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ya inició su horario de comida.',
            ], 422);
        }

        $asistencia->update([
            'comida_inicio' => Carbon::now(),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $asistencia,
            'message' => 'Inicio de comida registrado.',
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

        if (!$asistencia || !$asistencia->comida_inicio) {
            return response()->json([
                'status' => 'error',
                'message' => 'No ha iniciado su horario de comida.',
            ], 422);
        }

        if ($asistencia->comida_fin) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ya finalizó su horario de comida.',
            ], 422);
        }

        $asistencia->update([
            'comida_fin' => Carbon::now(),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $asistencia,
            'message' => 'Fin de comida registrado.',
        ]);
    }

    /**
     * Registrar salida de jornada
     */
    public function registrarSalida(Request $request): JsonResponse
    {
        $request->validate([
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $hoy = Carbon::today();
        $asistencia = Asistencia::where('usuario_id', $request->user()->id)
            ->whereDate('fecha', $hoy)
            ->first();

        if (!$asistencia || !$asistencia->entrada) {
            return response()->json([
                'status' => 'error',
                'message' => 'Debe registrar su entrada primero.',
            ], 422);
        }

        if ($asistencia->salida) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ya registró su salida el día de hoy.',
            ], 422);
        }

        if ($asistencia->comida_inicio && !$asistencia->comida_fin) {
            return response()->json([
                'status' => 'error',
                'message' => 'Debe finalizar horario de comida.',
            ], 422);
        }

        $now = Carbon::now();
        if ($now->lessThanOrEqualTo($asistencia->entrada)) {
            return response()->json([
                'status' => 'error',
                'message' => 'La hora de salida no puede ser igual o anterior a la hora de entrada.',
            ], 422);
        }

        $asistencia->update([
            'salida' => $now,
            'latitud_salida' => $request->latitud,
            'longitud_salida' => $request->longitud,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $asistencia,
            'message' => 'Salida registrada correctamente.',
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
