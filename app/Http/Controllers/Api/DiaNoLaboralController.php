<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DiaNoLaboral\StoreDiaNoLaboralRequest;
use App\Http\Traits\AdminBypassTrait;
use App\Models\DiaNoLaboral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador del calendario de días no laborales (festivos/vacaciones/descanso) por empresa.
 */
class DiaNoLaboralController extends Controller
{
    use AdminBypassTrait;

    /**
     * Listar días no laborales de la empresa del usuario autenticado.
     * Filtros opcionales: ?mes=7&anio=2026. Solo administrador/gerente.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->tieneRol(['administrador', 'gerente'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para consultar los días no laborales.',
            ], 403);
        }

        try {
            $query = DiaNoLaboral::where('empresa_id', $this->getEmpresaId($request));

            if ($request->filled('anio')) {
                $query->whereYear('fecha', $request->query('anio'));
            }

            if ($request->filled('mes')) {
                $query->whereMonth('fecha', $request->query('mes'));
            }

            $dias = $query->orderBy('fecha')->get();

            return response()->json([
                'status'  => 'success',
                'message' => 'Días no laborales obtenidos correctamente.',
                'data'    => $dias,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener los días no laborales.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Crear un día no laboral para la empresa del administrador autenticado.
     */
    public function store(StoreDiaNoLaboralRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->tieneRol(['administrador'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para crear días no laborales.',
            ], 403);
        }

        try {
            $dia = DiaNoLaboral::create([
                'empresa_id'  => $this->getEmpresaId($request),
                'fecha'       => $request->fecha,
                'descripcion' => $request->descripcion,
                'tipo'        => $request->tipo,
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Día no laboral creado correctamente.',
                'data'    => $dia,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al crear el día no laboral.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Eliminar un día no laboral de la empresa del administrador autenticado.
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->tieneRol(['administrador'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para eliminar días no laborales.',
            ], 403);
        }

        try {
            $dia = DiaNoLaboral::where('empresa_id', $this->getEmpresaId($request))->find($id);

            if (! $dia) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Día no laboral no encontrado.',
                ], 404);
            }

            $dia->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Día no laboral eliminado correctamente.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al eliminar el día no laboral.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }
}
