<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Gestión de solicitudes de registro pendientes de aprobación por el
 * superadmin: acceso global, sin acotarse a ninguna empresa.
 */
class SolicitudesController extends Controller
{
    /**
     * GET /api/superadmin/solicitudes
     */
    public function index(Request $request): JsonResponse
    {
        $solicitudes = Usuario::where('aprobado', false)
            ->where('rechazado', false)
            ->with(['empresa:id,nombre', 'departamento:id,nombre'])
            ->orderBy('fecha_solicitud', 'asc')
            ->get();

        return response()->json([
            'status'  => 'success',
            'message' => 'Solicitudes obtenidas correctamente.',
            'data'    => $solicitudes,
        ], 200);
    }

    /**
     * PATCH /api/superadmin/solicitudes/{id}/aprobar
     *
     * El rol se asigna a partir del rol configurado en el departamento
     * elegido por el solicitante (ver Departamento::rol_id). Si el
     * departamento no tiene un rol configurado, se bloquea la aprobación.
     */
    public function aprobar(Request $request, int $id): JsonResponse
    {
        $usuario = Usuario::where('aprobado', false)
            ->where('rechazado', false)
            ->with('departamento')
            ->find($id);

        if (! $usuario) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Solicitud no encontrada.',
            ], 404);
        }

        $departamento = $usuario->departamento;

        if (! $departamento || ! $departamento->rol_id) {
            $nombreDepartamento = $departamento->nombre ?? 'del usuario';

            return response()->json([
                'status'  => 'error',
                'message' => "El departamento '{$nombreDepartamento}' no tiene un rol predeterminado configurado. Configúralo antes de aprobar.",
            ], 422);
        }

        $usuario->update([
            'rol_id'           => $departamento->rol_id,
            'aprobado'         => true,
            'aprobado_por'     => $request->user()->id,
            'fecha_aprobacion' => now(),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Usuario aprobado correctamente.',
        ], 200);
    }

    /**
     * PATCH /api/superadmin/solicitudes/{id}/rechazar
     */
    public function rechazar(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'motivo' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error de validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $usuario = Usuario::where('aprobado', false)
            ->where('rechazado', false)
            ->find($id);

        if (! $usuario) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Solicitud no encontrada.',
            ], 404);
        }

        $usuario->update([
            'rechazado'      => true,
            'motivo_rechazo' => $request->input('motivo') ?? 'Solicitud rechazada por el administrador.',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Solicitud rechazada correctamente.',
        ], 200);
    }
}
