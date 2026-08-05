<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ControlHorario\ControlHorarioRequest;
use App\Http\Traits\AdminBypassTrait;
use App\Models\Asistencia;
use App\Models\ConfiguracionHorario;
use App\Models\DiaNoLaboral;
use App\Models\Usuario;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Vista administrativa de todos los registros de asistencia de la empresa,
 * con filtros por usuario, proyecto, rango de fechas y estado
 * (completo / incompleto / falta). Solo administrador/gerente.
 */
class ControlHorarioController extends Controller
{
    use AdminBypassTrait;

    private const RANGO_MAXIMO_DIAS = 366;

    public function index(ControlHorarioRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->tieneRol(['administrador', 'gerente'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para consultar el control horario.',
            ], 403);
        }

        $desde = Carbon::parse($request->desde)->startOfDay();
        $hasta = Carbon::parse($request->hasta)->endOfDay();

        if ($desde->diffInDays($hasta) > self::RANGO_MAXIMO_DIAS) {
            return response()->json([
                'status'  => 'error',
                'message' => 'El rango de fechas no puede superar los ' . self::RANGO_MAXIMO_DIAS . ' días.',
            ], 422);
        }

        try {
            $usuariosQuery = Usuario::where('empresa_id', $this->getEmpresaId($request))->where('activo', true);

            if ($request->filled('usuario_id')) {
                $usuariosQuery->where('id', $request->usuario_id);
            }

            $usuarios = $usuariosQuery->orderBy('nombre')->get(['id', 'nombre']);

            if ($usuarios->isEmpty()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Usuario no encontrado en tu empresa.',
                ], 404);
            }

            $horariosPorDia = ConfiguracionHorario::where('empresa_id', $this->getEmpresaId($request))
                ->get()
                ->keyBy('dia_semana');

            $diasNoLaborales = DiaNoLaboral::where('empresa_id', $this->getEmpresaId($request))
                ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
                ->pluck('fecha')
                ->map(fn ($fecha) => $fecha->toDateString())
                ->flip();

            $asistenciasQuery = Asistencia::whereIn('usuario_id', $usuarios->pluck('id'))
                ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
                ->with('proyecto:id,nombre');

            if ($request->filled('proyecto_id')) {
                $asistenciasQuery->where('proyecto_id', $request->proyecto_id);
            }

            $asistenciasPorClave = $asistenciasQuery->get()
                ->keyBy(fn (Asistencia $a) => $a->usuario_id . '|' . $a->fecha->toDateString());

            $estadoFiltro = $request->query('estado');
            $filas = collect();

            foreach ($usuarios as $usuario) {
                foreach (CarbonPeriod::create($desde, $hasta) as $fecha) {
                    $fechaStr = $fecha->toDateString();
                    $diaSemana = $fecha->dayOfWeekIso;

                    /** @var ConfiguracionHorario|null $config */
                    $config = $horariosPorDia->get($diaSemana);
                    $esLaboral = $config && $config->es_laboral && ! $diasNoLaborales->has($fechaStr);

                    /** @var Asistencia|null $registro */
                    $registro = $asistenciasPorClave->get($usuario->id . '|' . $fechaStr);

                    if (! $registro) {
                        // Sin registro en un día no laboral: no aporta información, se omite.
                        if (! $esLaboral) {
                            continue;
                        }
                        $estado = 'falta';
                    } else {
                        $estado = $registro->salida ? 'completo' : 'incompleto';
                    }

                    if ($estadoFiltro && $estado !== $estadoFiltro) {
                        continue;
                    }

                    $filas->push([
                        'usuario'          => ['id' => $usuario->id, 'nombre' => $usuario->nombre],
                        'proyecto'         => $registro?->proyecto ? [
                            'id'     => $registro->proyecto->id,
                            'nombre' => $registro->proyecto->nombre,
                        ] : null,
                        'fecha'            => $fechaStr,
                        'entrada'          => $registro?->entrada?->format('H:i'),
                        'comida_inicio'    => $registro?->comida_inicio?->format('H:i'),
                        'comida_fin'       => $registro?->comida_fin?->format('H:i'),
                        'salida'           => $registro?->salida?->format('H:i'),
                        'horas_trabajadas' => $registro ? $registro->horasTrabajadas() : 0.0,
                        'estado'           => $estado,
                    ]);
                }
            }

            $filas = $filas->sortByDesc('fecha')->values();

            $perPage = $request->integer('per_page') ?: 15;
            $page = LengthAwarePaginator::resolveCurrentPage();

            $paginado = new LengthAwarePaginator(
                $filas->forPage($page, $perPage)->values(),
                $filas->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Control horario obtenido correctamente.',
                'data'    => $paginado,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener el control horario.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }
}
