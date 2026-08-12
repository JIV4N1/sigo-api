<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Gestión de usuarios para el rol superadmin: acceso global a los usuarios
 * de todas las empresas del sistema (a diferencia de
 * App\Http\Controllers\Api\UsuarioController, que solo ve la empresa activa).
 */
class UsuarioController extends Controller
{
    /**
     * GET /api/superadmin/usuarios
     *
     * Filtros vía query string:
     * - ?empresa_id=N → solo usuarios de esa empresa
     * - ?activo=1|0   → filtra por estado
     */
    public function index(Request $request): JsonResponse
    {
        $query = Usuario::with(['rol', 'empresa']);

        if ($request->filled('empresa_id')) {
            $query->where('empresa_id', $request->query('empresa_id'));
        }

        if ($request->filled('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        $usuarios = $query->orderBy('nombre', 'asc')->get();

        return response()->json([
            'status'  => 'success',
            'message' => 'Usuarios obtenidos correctamente.',
            'data'    => $usuarios,
        ], 200);
    }

    /**
     * PATCH /api/superadmin/usuarios/{id}/rol
     *
     * Body: { "rol_id": number }
     */
    public function cambiarRol(int $id, Request $request): JsonResponse
    {
        $usuario = Usuario::find($id);

        if (! $usuario) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'rol_id' => 'required|integer|exists:roles,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error de validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $usuario->update(['rol_id' => $request->rol_id]);
        $usuario->load('rol');

        return response()->json([
            'status'  => 'success',
            'message' => 'Rol actualizado correctamente.',
            'data'    => $usuario,
        ], 200);
    }

    /**
     * PATCH /api/superadmin/usuarios/{id}/estado
     *
     * Body: { "activo": true|false }
     */
    public function cambiarEstado(int $id, Request $request): JsonResponse
    {
        $usuario = Usuario::find($id);

        if (! $usuario) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'activo' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error de validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        if ($usuario->id === $request->user()->id && ! $request->boolean('activo')) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No puedes desactivar tu propia cuenta.',
            ], 422);
        }

        $usuario->update(['activo' => $request->boolean('activo')]);

        if (! $usuario->activo) {
            $usuario->tokens()->delete();
        }

        return response()->json([
            'status'  => 'success',
            'message' => $usuario->activo ? 'Usuario activado correctamente.' : 'Usuario desactivado correctamente.',
            'data'    => $usuario,
        ], 200);
    }
}
