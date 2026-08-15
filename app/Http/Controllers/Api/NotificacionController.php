<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Controlador de notificaciones del sistema SIGO.
 *
 * Cada usuario solo puede consultar y gestionar sus propias notificaciones;
 * la creación se realiza internamente vía Notificacion::crear() desde otros
 * módulos (incidencias, reportes, etc.), no a través de un endpoint público.
 */
class NotificacionController extends Controller
{
    /**
     * GET /api/notificaciones
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'tipo'  => ['nullable', 'string', Rule::in(Notificacion::TIPOS)],
                'leida' => ['nullable', 'boolean'],
            ]);

            $query = Notificacion::where('usuario_id', $request->user()->id)
                ->orderByDesc('created_at');

            if ($request->filled('tipo')) {
                $query->where('tipo', $request->tipo);
            }

            if ($request->leida !== null) {
                $query->where('leida', filter_var($request->leida, FILTER_VALIDATE_BOOLEAN));
            }

            $notificaciones = $query->paginate(20);

            return response()->json([
                'status'  => 'success',
                'data'    => $notificaciones,
                'message' => 'Notificaciones obtenidas correctamente',
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => 'Datos inválidos.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las notificaciones.', $e);
        }
    }

    /**
     * GET /api/notificaciones/no-leidas
     */
    public function noLeidas(Request $request): JsonResponse
    {
        try {
            $total = Notificacion::where('usuario_id', $request->user()->id)
                ->where('leida', false)
                ->count();

            return response()->json([
                'status'  => 'success',
                'data'    => ['total' => $total],
                'message' => 'Notificaciones no leídas obtenidas correctamente',
            ], 200);

        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las notificaciones no leídas.', $e);
        }
    }

    /**
     * PATCH /api/notificaciones/{id}/leer
     */
    public function marcarLeida(Request $request, int $id): JsonResponse
    {
        try {
            $notificacion = Notificacion::where('usuario_id', $request->user()->id)->find($id);

            if (! $notificacion) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Notificación no encontrada.',
                ], 404);
            }

            $notificacion->update(['leida' => true]);

            return response()->json([
                'status'  => 'success',
                'data'    => $notificacion,
                'message' => 'Notificación marcada como leída',
            ], 200);

        } catch (\Exception $e) {
            return $this->errorResponse('Error al marcar la notificación como leída.', $e);
        }
    }

    /**
     * PATCH /api/notificaciones/leer-todas
     */
    public function marcarTodasLeidas(Request $request): JsonResponse
    {
        try {
            $actualizadas = Notificacion::where('usuario_id', $request->user()->id)
                ->where('leida', false)
                ->update(['leida' => true]);

            return response()->json([
                'status'  => 'success',
                'data'    => ['actualizadas' => $actualizadas],
                'message' => "{$actualizadas} notificaciones marcadas como leídas",
            ], 200);

        } catch (\Exception $e) {
            return $this->errorResponse('Error al marcar las notificaciones como leídas.', $e);
        }
    }

    /**
     * Genera una respuesta JSON de error estándar SIGO (500).
     */
    private function errorResponse(string $mensaje, \Exception $e): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => $mensaje,
            'errors'  => ['exception' => $e->getMessage()],
        ], 500);
    }
}
