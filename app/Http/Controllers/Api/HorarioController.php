<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Horario\StoreHorarioRequest;
use App\Http\Requests\Horario\UpdateHorarioRequest;
use App\Http\Traits\AdminBypassTrait;
use App\Models\ConfiguracionHorario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de configuración de horarios laborales por empresa.
 */
class HorarioController extends Controller
{
    use AdminBypassTrait;

    /**
     * Listar la configuración de horarios de la empresa del usuario autenticado.
     * Solo administrador/gerente.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->tieneRol(['administrador', 'gerente'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para consultar los horarios.',
            ], 403);
        }

        try {
            $horarios = ConfiguracionHorario::where('empresa_id', $this->getEmpresaId($request))
                ->orderBy('dia_semana')
                ->get()
                ->map(fn (ConfiguracionHorario $h) => $this->formatearHorario($h));

            return response()->json([
                'status'  => 'success',
                'message' => 'Horarios obtenidos correctamente.',
                'data'    => $horarios,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener los horarios.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Crear o actualizar (upsert) el horario de un día de la semana
     * para la empresa del administrador autenticado.
     */
    public function store(StoreHorarioRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->tieneRol(['administrador'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para configurar horarios.',
            ], 403);
        }

        try {
            $horario = ConfiguracionHorario::updateOrCreate(
                [
                    'empresa_id' => $this->getEmpresaId($request),
                    'dia_semana' => $request->dia_semana,
                ],
                [
                    'hora_inicio' => $request->hora_inicio,
                    'hora_fin'    => $request->hora_fin,
                    'es_laboral'  => $request->boolean('es_laboral', true),
                ]
            );

            return response()->json([
                'status'  => 'success',
                'message' => $horario->wasRecentlyCreated ? 'Horario creado correctamente.' : 'Horario actualizado correctamente.',
                'data'    => $this->formatearHorario($horario),
            ], $horario->wasRecentlyCreated ? 201 : 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al guardar el horario.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Editar un horario existente de la empresa del administrador autenticado.
     */
    public function update(int $id, UpdateHorarioRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->tieneRol(['administrador'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para editar horarios.',
            ], 403);
        }

        try {
            $horario = ConfiguracionHorario::where('empresa_id', $this->getEmpresaId($request))->find($id);

            if (! $horario) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Horario no encontrado.',
                ], 404);
            }

            $horario->update($request->only(['dia_semana', 'hora_inicio', 'hora_fin', 'es_laboral']));

            return response()->json([
                'status'  => 'success',
                'message' => 'Horario actualizado correctamente.',
                'data'    => $this->formatearHorario($horario),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al actualizar el horario.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    private function formatearHorario(ConfiguracionHorario $h): array
    {
        return [
            'id'               => $h->id,
            'dia_semana'       => $h->dia_semana,
            'dia_semana_nombre' => ConfiguracionHorario::DIAS[$h->dia_semana] ?? null,
            'hora_inicio'      => $h->hora_inicio,
            'hora_fin'         => $h->hora_fin,
            'es_laboral'       => $h->es_laboral,
        ];
    }
}
