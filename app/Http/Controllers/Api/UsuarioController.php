<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador de usuarios para la API SIGO.
 */
class UsuarioController extends Controller
{
    /**
     * Listar usuarios pertenecientes a la empresa del usuario autenticado.
     * Permite filtrar por rol vía query parameter ?rol=supervisor o ?rol=1.
     *
     * Responde con: id, nombre, email, rol (id, nombre)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $query = Usuario::where('empresa_id', $user->empresa_id)
                ->where('activo', true)
                ->with('rol');

            // Filtrar por rol (?rol=supervisor o ?rol=1 o ?rol=ingeniero)
            if ($request->filled('rol')) {
                $rolParam = $request->query('rol');
                $query->whereHas('rol', function ($q) use ($rolParam) {
                    if (is_numeric($rolParam)) {
                        $q->where('id', $rolParam);
                    } else {
                        $q->where(DB::raw('LOWER(nombre)'), strtolower($rolParam));
                    }
                });
            }

            $usuarios = $query->orderBy('nombre', 'asc')
                ->get()
                ->map(fn (Usuario $u) => [
                    'id'     => $u->id,
                    'nombre' => $u->nombre,
                    'email'  => $u->email,
                    'rol'    => $u->rol ? [
                        'id'     => $u->rol->id,
                        'nombre' => $u->rol->nombre,
                    ] : null,
                ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Usuarios obtenidos correctamente.',
                'data'    => $usuarios,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener los usuarios.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }
}
