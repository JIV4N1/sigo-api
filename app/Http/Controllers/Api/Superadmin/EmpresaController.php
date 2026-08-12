<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Role;
use App\Models\Usuario;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Gestión de empresas para el rol superadmin: control total sobre todas
 * las empresas del sistema, sin acotarse a la empresa activa del usuario
 * autenticado (a diferencia de App\Http\Controllers\Api\EmpresaController).
 */
class EmpresaController extends Controller
{
    /**
     * GET /api/superadmin/empresas
     *
     * Filtros vía query string:
     * - ?activo=1|0 → filtra por estado
     * - ?buscar=texto → busca por nombre o razón social
     */
    public function index(Request $request): JsonResponse
    {
        $query = Empresa::withCount('usuarios');

        if ($request->filled('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        if ($request->filled('buscar')) {
            $buscar = $request->query('buscar');
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                  ->orWhere('razon_social', 'like', "%{$buscar}%");
            });
        }

        $empresas = $query->orderBy('nombre', 'asc')->get();

        return response()->json([
            'status'  => 'success',
            'message' => 'Empresas obtenidas correctamente.',
            'data'    => $empresas,
        ], 200);
    }

    /**
     * GET /api/superadmin/empresas/{id}
     */
    public function show(int $id): JsonResponse
    {
        $empresa = Empresa::withCount('usuarios')->find($id);

        if (! $empresa) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Empresa no encontrada.',
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Empresa obtenida correctamente.',
            'data'    => $empresa,
        ], 200);
    }

    /**
     * PUT /api/superadmin/empresas/{id}
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $empresa = Empresa::find($id);

        if (! $empresa) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Empresa no encontrada.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre'       => 'sometimes|required|string|max:200',
            'razon_social' => 'sometimes|required|string|max:200',
            'rfc'          => 'nullable|string|max:20',
            'direccion'    => 'nullable|string|max:300',
            'telefono'     => 'nullable|string|max:20',
            'email'        => 'nullable|email|max:100',
        ]);

        if ($validator->fails()) {
            return $this->respuestaValidacion($validator);
        }

        $empresa->update($validator->validated());

        return response()->json([
            'status'  => 'success',
            'message' => 'Empresa actualizada correctamente.',
            'data'    => $empresa,
        ], 200);
    }

    /**
     * PATCH /api/superadmin/empresas/{id}/estado
     *
     * Activa o desactiva una empresa. Body: { "activo": true|false }
     */
    public function cambiarEstado(int $id, Request $request): JsonResponse
    {
        $empresa = Empresa::find($id);

        if (! $empresa) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Empresa no encontrada.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'activo' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return $this->respuestaValidacion($validator);
        }

        $empresa->update(['activo' => $request->boolean('activo')]);

        return response()->json([
            'status'  => 'success',
            'message' => $empresa->activo ? 'Empresa activada correctamente.' : 'Empresa desactivada correctamente.',
            'data'    => $empresa,
        ], 200);
    }

    /**
     * GET /api/superadmin/empresas/{id}/usuarios
     */
    public function usuarios(int $id, Request $request): JsonResponse
    {
        $empresa = Empresa::find($id);

        if (! $empresa) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Empresa no encontrada.',
            ], 404);
        }

        // Usuarios fijos de la empresa (empresa_id) más los admins multi-empresa
        // asignados vía el pivote empresa_usuario.
        $usuariosFijosQuery = Usuario::where('empresa_id', $empresa->id)->with('rol');
        $usuariosPivoteQuery = $empresa->usuarios()->with('rol');

        // El rol superadmin es invisible para cualquiera que no lo sea.
        if (! $request->user()->esSuperadmin()) {
            $usuariosFijosQuery->whereDoesntHave('rol', function ($q) {
                $q->where('nombre', 'superadmin');
            });
            $usuariosPivoteQuery->whereDoesntHave('rol', function ($q) {
                $q->where('nombre', 'superadmin');
            });
        }

        $usuarios = $usuariosFijosQuery->get()->concat($usuariosPivoteQuery->get())->unique('id')->values();

        return response()->json([
            'status'  => 'success',
            'message' => 'Usuarios de la empresa obtenidos correctamente.',
            'data'    => $usuarios,
        ], 200);
    }

    /**
     * POST /api/superadmin/empresas/{id}/admin
     *
     * Asigna a un usuario existente como administrador (admin_empresa) de
     * la empresa, vía la tabla pivote empresa_usuario.
     * Body: { "usuario_id": number }
     */
    public function asignarAdmin(int $id, Request $request): JsonResponse
    {
        $empresa = Empresa::find($id);

        if (! $empresa) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Empresa no encontrada.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'usuario_id' => 'required|integer|exists:usuarios,id',
        ]);

        if ($validator->fails()) {
            return $this->respuestaValidacion($validator);
        }

        $usuario = Usuario::findOrFail($request->usuario_id);
        $rolAdministrador = Role::where('nombre', 'administrador')->first();

        DB::transaction(function () use ($empresa, $usuario, $rolAdministrador) {
            $empresa->usuarios()->syncWithoutDetaching([
                $usuario->id => [
                    'rol_en_empresa' => 'admin_empresa',
                    'asignado_el'    => now(),
                ],
            ]);

            if ($rolAdministrador && $usuario->rol_id !== $rolAdministrador->id) {
                $usuario->update(['rol_id' => $rolAdministrador->id]);
            }
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Administrador asignado correctamente a la empresa.',
            'data'    => $empresa->usuarios()->with('rol')->get(),
        ], 200);
    }

    private function respuestaValidacion(ValidatorContract $validator): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => 'Error de validación',
            'errors'  => $validator->errors(),
        ], 422);
    }
}
