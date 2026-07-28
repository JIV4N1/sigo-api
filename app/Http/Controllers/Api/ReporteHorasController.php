<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReporteHoras\ReporteHorasRequest;
use App\Http\Traits\AdminBypassTrait;
use App\Models\Asistencia;
use App\Models\ConfiguracionHorario;
use App\Models\DiaNoLaboral;
use App\Models\Usuario;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Reporte de horas trabajadas por usuario en un rango de fechas.
 *
 * Administrador/gerente pueden consultar cualquier usuario de su empresa
 * (o todos si no se especifica usuario_id). El resto de los roles solo
 * pueden consultar sus propias horas, sin importar el usuario_id enviado.
 */
class ReporteHorasController extends Controller
{
    use AdminBypassTrait;

    private const RANGO_MAXIMO_DIAS = 366;

    public function index(ReporteHorasRequest $request): JsonResponse
    {
        $user = $request->user();
        $esAdminOGerente = $user->tieneRol(['administrador', 'gerente']);

        $desde = Carbon::parse($request->desde)->startOfDay();
        $hasta = Carbon::parse($request->hasta)->endOfDay();

        if ($desde->diffInDays($hasta) > self::RANGO_MAXIMO_DIAS) {
            return response()->json([
                'status'  => 'error',
                'message' => 'El rango de fechas no puede superar los ' . self::RANGO_MAXIMO_DIAS . ' días.',
            ], 422);
        }

        try {
            if ($esAdminOGerente) {
                $usuariosQuery = Usuario::where('empresa_id', $this->getEmpresaId($request))->where('activo', true);

                if ($request->filled('usuario_id')) {
                    $usuariosQuery->where('id', $request->usuario_id);
                }
            } else {
                $usuariosQuery = Usuario::where('id', $user->id);
            }

            $usuarios = $usuariosQuery->orderBy('nombre')->get(['id', 'nombre', 'email']);

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

            $data = $usuarios->map(fn (Usuario $u) => $this->construirReporteUsuario(
                $u,
                $desde,
                $hasta,
                $horariosPorDia,
                $diasNoLaborales,
                $request->integer('proyecto_id') ?: null
            ))->values();

            return response()->json([
                'status'  => 'success',
                'message' => 'Reporte de horas obtenido correctamente.',
                'data'    => $data,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al generar el reporte de horas.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Construye el desglose diario y los totales de un usuario para el rango dado.
     *
     * @param  Collection<int, ConfiguracionHorario>  $horariosPorDia
     * @param  Collection<string, int>  $diasNoLaborales
     */
    private function construirReporteUsuario(
        Usuario $usuario,
        Carbon $desde,
        Carbon $hasta,
        Collection $horariosPorDia,
        Collection $diasNoLaborales,
        ?int $proyectoId
    ): array {
        $asistenciasQuery = Asistencia::where('usuario_id', $usuario->id)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()]);

        if ($proyectoId) {
            $asistenciasQuery->where('proyecto_id', $proyectoId);
        }

        $asistencias = $asistenciasQuery->get()->keyBy(fn (Asistencia $a) => $a->fecha->toDateString());

        $dias = [];
        $totalTrabajadas = 0.0;
        $totalExtra = 0.0;
        $diasTrabajados = 0;
        $diasFalta = 0;

        foreach (CarbonPeriod::create($desde, $hasta) as $fecha) {
            $fechaStr = $fecha->toDateString();
            $diaSemana = $fecha->dayOfWeekIso;

            /** @var ConfiguracionHorario|null $config */
            $config = $horariosPorDia->get($diaSemana);
            $esLaboral = $config && $config->es_laboral && ! $diasNoLaborales->has($fechaStr);

            $horasProgramadas = 0.0;
            if ($esLaboral && $config->hora_inicio && $config->hora_fin) {
                $horasProgramadas = round(
                    Carbon::parse($config->hora_inicio)->diffInMinutes(Carbon::parse($config->hora_fin)) / 60,
                    2
                );
            }

            /** @var Asistencia|null $registro */
            $registro = $asistencias->get($fechaStr);
            $horasTrabajadas = $registro ? $registro->horasTrabajadas() : 0.0;
            $horasExtra = round(max(0, $horasTrabajadas - $horasProgramadas), 2);

            $estado = match (true) {
                $registro && $registro->entrada && $registro->salida => 'completo',
                $registro && $registro->entrada && ! $registro->salida => 'incompleto',
                $esLaboral => 'falta',
                default => 'no_laboral',
            };

            if ($horasTrabajadas > 0) {
                $diasTrabajados++;
            }
            if ($estado === 'falta') {
                $diasFalta++;
            }

            $totalTrabajadas += $horasTrabajadas;
            $totalExtra += $horasExtra;

            $dias[] = [
                'fecha'             => $fechaStr,
                'dia_semana'        => ConfiguracionHorario::DIAS[$diaSemana] ?? null,
                'entrada'           => $registro?->entrada?->format('H:i'),
                'salida'            => $registro?->salida?->format('H:i'),
                'horas_trabajadas'  => $horasTrabajadas,
                'horas_programadas' => $horasProgramadas,
                'horas_extra'       => $horasExtra,
                'estado'            => $estado,
            ];
        }

        return [
            'usuario' => [
                'id'     => $usuario->id,
                'nombre' => $usuario->nombre,
                'email'  => $usuario->email,
            ],
            'resumen' => [
                'horas_trabajadas' => round($totalTrabajadas, 2),
                'horas_extra'      => round($totalExtra, 2),
                'dias_trabajados'  => $diasTrabajados,
                'dias_falta'       => $diasFalta,
            ],
            'dias' => $dias,
        ];
    }
}
