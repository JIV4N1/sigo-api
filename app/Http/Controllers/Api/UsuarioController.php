<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Usuario\StoreUsuarioRequest;
use App\Http\Requests\Usuario\UpdateUsuarioRequest;
use App\Http\Traits\AdminBypassTrait;
use App\Models\Role;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Controlador de usuarios para la API SIGO.
 *
 * Gestiona el CRUD de usuarios de la empresa activa del administrador autenticado.
 * Todas las acciones de escritura (crear/editar/desactivar) y el listado
 * están restringidas al rol "administrador".
 */
class UsuarioController extends Controller
{
    use AdminBypassTrait;

    /**
     * Listar usuarios pertenecientes a la empresa del usuario autenticado.
     *
     * Filtros disponibles vía query string:
     * - ?rol=supervisor | ?rol=1        → filtra por rol
     * - ?activo=0                       → solo usuarios desactivados
     * - ?activo=todos                   → sin filtro de estado (activos e inactivos)
     * - (sin parámetro activo)          → solo usuarios activos (comportamiento por defecto)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->tieneRol(['administrador'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para listar usuarios.',
            ], 403);
        }

        try {
            $query = Usuario::where('empresa_id', $this->getEmpresaId($request))->with(['rol', 'departamento']);

            // El rol superadmin es invisible para cualquiera que no lo sea.
            if (! $user->esSuperadmin()) {
                $query->whereDoesntHave('rol', function ($q) {
                    $q->where('nombre', 'superadmin');
                });
            }

            $activoParam = $request->query('activo');
            if ($activoParam === null) {
                $query->where('activo', true);
            } elseif (strtolower((string) $activoParam) !== 'todos') {
                $query->where('activo', $request->boolean('activo'));
            }

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
                ->map(fn (Usuario $u) => $this->formatearUsuario($u));

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

    /**
     * Crear un nuevo usuario dentro de la empresa activa del administrador autenticado.
     *
     * Si el nuevo usuario es de rol "administrador", no se le asigna una
     * empresa_id fija: los administradores acceden a sus empresas mediante
     * la tabla pivote empresa_usuario y el selector de empresa activa
     * (ver AuthController::cambiarEmpresa). El resto de los roles sí quedan
     * fijos a la empresa activa de quien los crea.
     */
    public function store(StoreUsuarioRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->tieneRol(['administrador'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para crear usuarios.',
            ], 403);
        }

        try {
            $rol = Role::find($request->rol_id);
            $esAdministrador = $rol && $rol->nombre === 'administrador';

            $usuario = Usuario::create([
                'empresa_id'       => $esAdministrador ? null : $this->getEmpresaId($request),
                'nombre'           => $request->nombre,
                'email'            => $request->email,
                'password'         => Hash::make($request->password),
                'telefono'         => $request->telefono,
                'rol_id'           => $request->rol_id,
                'departamento_id'  => $esAdministrador ? null : $request->departamento_id,
                'activo'           => $request->boolean('activo', true),
                'aprobado'         => true,
                'rechazado'        => false,
            ]);

            $usuario->load(['rol', 'departamento']);

            return response()->json([
                'status'  => 'success',
                'message' => 'Usuario creado correctamente.',
                'data'    => $this->formatearUsuario($usuario),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al crear el usuario.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Mostrar el detalle de un usuario de la empresa del administrador autenticado.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->tieneRol(['administrador'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para consultar usuarios.',
            ], 403);
        }

        try {
            $usuario = Usuario::where('empresa_id', $this->getEmpresaId($request))
                ->with(['rol', 'departamento'])
                ->find($id);

            if (! $usuario) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Usuario no encontrado.',
                ], 404);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Usuario obtenido correctamente.',
                'data'    => $this->formatearUsuario($usuario),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener el usuario.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Actualizar un usuario existente de la empresa del administrador autenticado.
     */
    public function update(int $id, UpdateUsuarioRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->tieneRol(['administrador'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para editar usuarios.',
            ], 403);
        }

        try {
            $usuario = Usuario::where('empresa_id', $this->getEmpresaId($request))->find($id);

            if (! $usuario) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Usuario no encontrado.',
                ], 404);
            }

            $datos = $request->only(['nombre', 'email', 'telefono', 'rol_id', 'activo', 'departamento_id']);

            if ($request->filled('password')) {
                $datos['password'] = Hash::make($request->password);
            }

            $usuario->update($datos);
            $usuario->load(['rol', 'departamento']);

            return response()->json([
                'status'  => 'success',
                'message' => 'Usuario actualizado correctamente.',
                'data'    => $this->formatearUsuario($usuario),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al actualizar el usuario.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Desactivar (soft delete) un usuario de la empresa del administrador autenticado.
     * No elimina el registro; establece activo = false y revoca sus tokens de acceso.
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->tieneRol(['administrador'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para desactivar usuarios.',
            ], 403);
        }

        try {
            $usuario = Usuario::where('empresa_id', $this->getEmpresaId($request))->find($id);

            if (! $usuario) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Usuario no encontrado.',
                ], 404);
            }

            if ($usuario->id === $user->id) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No puedes desactivar tu propia cuenta.',
                ], 422);
            }

            $usuario->update(['activo' => false]);
            $usuario->tokens()->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Usuario desactivado correctamente.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al desactivar el usuario.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Da forma al arreglo de salida de un usuario (sin exponer password).
     */
    private function formatearUsuario(Usuario $u): array
    {
        return [
            'id'            => $u->id,
            'nombre'        => $u->nombre,
            'email'         => $u->email,
            'telefono'      => $u->telefono,
            'foto_perfil'   => $u->foto_perfil,
            'activo'        => $u->activo,
            'ultimo_acceso' => $u->ultimo_acceso,
            'rol'           => $u->rol ? [
                'id'     => $u->rol->id,
                'nombre' => $u->rol->nombre,
            ] : null,
            'departamento_id' => $u->departamento_id,
            'departamento'    => $u->departamento ? [
                'id'          => $u->departamento->id,
                'nombre'      => $u->departamento->nombre,
                'descripcion' => $u->departamento->descripcion,
            ] : null,
        ];
    }
}
