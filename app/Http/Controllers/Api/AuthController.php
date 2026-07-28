<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CambiarEmpresaRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Traits\AdminBypassTrait;
use App\Models\Empresa;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Controlador de autenticación del sistema SIGO.
 *
 * Gestiona el inicio de sesión, cierre de sesión, la obtención
 * del perfil del usuario autenticado y el cambio de empresa activa
 * (para administradores multi-empresa) mediante tokens de Sanctum.
 */
class AuthController extends Controller
{
    use AdminBypassTrait;

    // =========================================================================
    // LOGIN
    // =========================================================================

    /**
     * Autentica a un usuario y retorna un token de acceso.
     *
     * - Valida las credenciales con LoginRequest (email y password).
     * - Verifica que el usuario exista y que su contraseña sea correcta.
     * - Verifica que el usuario esté activo en el sistema.
     * - Crea un token Sanctum y actualiza el último acceso.
     *
     * @param  LoginRequest  $request  Petición con credenciales ya validadas.
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            // 1. Buscar al usuario por email e incluir la relación con su rol
            $usuario = Usuario::with('rol')->where('email', $request->email)->first();

            // 2. Validar existencia y contraseña
            if (! $usuario || ! Hash::check($request->password, $usuario->password)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Credenciales inválidas.',
                ], 401);
            }

            // 3. Verificar que el usuario esté activo
            if (! $usuario->activo) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Usuario desactivado.',
                ], 403);
            }

            // 4. Revocar tokens anteriores (opcional: sesión única por dispositivo)
            // $usuario->tokens()->delete();

            // 5. Crear el token de acceso para la aplicación móvil SIGO
            $token = $usuario->createToken('sigo-mobile')->plainTextToken;

            // 6. Registrar el último acceso del usuario
            $usuario->update(['ultimo_acceso' => now()]);

            // 7. Construir la respuesta con los datos del usuario y su rol
            return response()->json([
                'status'  => 'success',
                'message' => 'Inicio de sesión exitoso.',
                'data'    => [
                    'token'   => $token,
                    'usuario' => [
                        'id'          => $usuario->id,
                        'nombre'      => $usuario->nombre,
                        'email'       => $usuario->email,
                        'telefono'    => $usuario->telefono,
                        'foto_perfil' => $usuario->foto_perfil,
                        'rol'         => $usuario->rol ? [
                            'id'     => $usuario->rol->id,
                            'nombre' => $usuario->rol->nombre,
                        ] : null,
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            // Captura cualquier error inesperado y retorna 500
            return response()->json([
                'status'  => 'error',
                'message' => 'Error interno del servidor.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    // =========================================================================
    // LOGOUT
    // =========================================================================

    /**
     * Cierra la sesión del usuario autenticado.
     *
     * Revoca únicamente el token que se utilizó en esta petición,
     * lo que permite al usuario tener múltiples sesiones activas
     * en distintos dispositivos.
     *
     * @param  Request  $request  Petición autenticada con Sanctum.
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            // Revocar el token actual con el que se autenticó esta petición
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Sesión cerrada correctamente.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al cerrar sesión.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    // =========================================================================
    // ME (PERFIL AUTENTICADO)
    // =========================================================================

    /**
     * Retorna el perfil completo del usuario autenticado.
     *
     * Incluye:
     * - Datos personales: id, nombre, email, teléfono, foto de perfil.
     * - Rol asignado: id y nombre.
     * - Empresa activa y, si es administrador, las empresas disponibles para cambiar.
     * - Proyectos asignados: id, código, nombre, ubicación, avance y estado.
     *
     * @param  Request  $request  Petición autenticada con Sanctum.
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        try {
            // Cargar relaciones del usuario autenticado con sus proyectos y rol
            /** @var Usuario $usuario */
            $usuario = $request->user()->load(['rol', 'proyectos']);

            $empresaActualId = $this->getEmpresaId($request);
            $empresaActual = $empresaActualId ? Empresa::find($empresaActualId, ['id', 'nombre']) : null;

            $datos = [
                'id'          => $usuario->id,
                'nombre'      => $usuario->nombre,
                'email'       => $usuario->email,
                'telefono'    => $usuario->telefono,
                'foto_perfil' => $usuario->foto_perfil,

                // Rol del usuario (puede ser null si no tiene rol asignado)
                'rol' => $usuario->rol ? [
                    'id'     => $usuario->rol->id,
                    'nombre' => $usuario->rol->nombre,
                ] : null,

                // Empresa actualmente seleccionada (empresa_activa_id o empresa_id fija)
                'empresa_actual' => $empresaActual ? [
                    'id'     => $empresaActual->id,
                    'nombre' => $empresaActual->nombre,
                ] : null,

                // Proyectos asignados al usuario
                'proyectos' => $usuario->proyectos->map(fn ($proyecto) => [
                    'id'        => $proyecto->id,
                    'codigo'    => $proyecto->codigo,
                    'nombre'    => $proyecto->nombre,
                    'ubicacion' => $proyecto->ubicacion,
                    'avance'    => $proyecto->avance,
                    'estado'    => $proyecto->estado,
                ])->values(),
            ];

            // Empresas disponibles solo se incluye para administradores multi-empresa
            if ($usuario->tieneRol(['administrador'])) {
                $datos['empresas_disponibles'] = $this->empresasDisponibles($usuario);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Perfil obtenido correctamente.',
                'data'    => $datos,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener el perfil.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    // =========================================================================
    // CAMBIAR EMPRESA (ADMINISTRADORES MULTI-EMPRESA)
    // =========================================================================

    /**
     * Cambia la empresa activa del usuario autenticado.
     *
     * - Administradores pueden cambiar a cualquier empresa a la que tengan
     *   acceso (su empresa_id fija, si la tiene, o cualquiera registrada en
     *   la tabla pivote empresa_usuario).
     * - El resto de los roles solo pueden "cambiar" a su propia empresa_id.
     *
     * @param  CambiarEmpresaRequest  $request  Petición ya validada (empresa_id).
     * @return JsonResponse
     */
    public function cambiarEmpresa(CambiarEmpresaRequest $request): JsonResponse
    {
        try {
            /** @var Usuario $usuario */
            $usuario = $request->user();
            $empresaId = (int) $request->empresa_id;

            $tieneAcceso = $usuario->tieneRol(['administrador'])
                ? $empresaId === $usuario->empresa_id || $usuario->empresas()->where('empresas.id', $empresaId)->exists()
                : $empresaId === $usuario->empresa_id;

            if (! $tieneAcceso) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No tienes acceso a esa empresa.',
                ], 403);
            }

            $usuario->update(['empresa_activa_id' => $empresaId]);

            $empresa = Empresa::find($empresaId);

            return response()->json([
                'status'  => 'success',
                'message' => 'Empresa cambiada correctamente',
                'data'    => [
                    'empresa' => [
                        'id'           => $empresa->id,
                        'nombre'       => $empresa->nombre,
                        'razon_social' => $empresa->razon_social,
                        'rfc'          => $empresa->rfc,
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al cambiar de empresa.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Lista de empresas a las que un administrador tiene acceso:
     * su empresa_id fija (si la tiene) más las registradas en empresa_usuario.
     *
     * @return array<int, array{id: int, nombre: string}>
     */
    private function empresasDisponibles(Usuario $usuario): array
    {
        $empresas = collect();

        if ($usuario->empresa_id) {
            $propia = Empresa::find($usuario->empresa_id, ['id', 'nombre']);
            if ($propia) {
                $empresas->push($propia);
            }
        }

        $empresas = $empresas->merge($usuario->empresas()->get(['empresas.id', 'empresas.nombre']))
            ->unique('id')
            ->values();

        return $empresas->map(fn (Empresa $e) => ['id' => $e->id, 'nombre' => $e->nombre])->all();
    }
}
