<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Empresa\StoreEmpresaRequest;
use App\Http\Requests\Empresa\UpdateConfiguracionEmpresaRequest;
use App\Http\Requests\Empresa\UploadLogoEmpresaRequest;
use App\Http\Traits\AdminBypassTrait;
use App\Models\Empresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Controlador de empresas para la API SIGO.
 */
class EmpresaController extends Controller
{
    use AdminBypassTrait;

    /**
     * Crear una nueva empresa.
     *
     * Solo administradores pueden crear empresas. Al crearla, el administrador
     * queda asignado automáticamente en la tabla pivote empresa_usuario (rol
     * "admin_empresa") y la nueva empresa se establece como su empresa activa.
     */
    public function store(StoreEmpresaRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->tieneRol(['administrador'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para crear empresas.',
            ], 403);
        }

        try {
            $empresa = DB::transaction(function () use ($request, $user) {
                // 1. Crear la empresa, activa por defecto
                $empresa = Empresa::create([
                    'nombre'       => $request->nombre,
                    'razon_social' => $request->razon_social,
                    'rfc'          => $request->rfc,
                    'direccion'    => $request->direccion,
                    'telefono'     => $request->telefono,
                    'email'        => $request->email,
                    'activo'       => true,
                ]);

                // 2. Asignar al administrador creador como admin_empresa en la tabla pivote
                $user->empresas()->attach($empresa->id, [
                    'rol_en_empresa' => 'admin_empresa',
                    'asignado_el'    => now(),
                ]);

                // 3. Dejar la nueva empresa como la empresa activa del administrador
                $user->update(['empresa_activa_id' => $empresa->id]);

                return $empresa;
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Empresa creada correctamente',
                'data'    => [
                    'empresa' => [
                        'id'           => $empresa->id,
                        'nombre'       => $empresa->nombre,
                        'razon_social' => $empresa->razon_social,
                        'rfc'          => $empresa->rfc,
                        'direccion'    => $empresa->direccion,
                        'telefono'     => $empresa->telefono,
                        'email'        => $empresa->email,
                        'activo'       => $empresa->activo,
                        'created_at'   => $empresa->created_at,
                    ],
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al crear la empresa.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * GET /api/empresa/configuracion
     *
     * Retorna la configuración de la empresa activa del usuario autenticado
     * (equivalente a ConfiguracionEmpresa del escritorio, respaldado por la
     * tabla `empresas` para no duplicar datos entre dos entidades).
     * Solo administrador/gerente.
     */
    public function configuracion(Request $request): JsonResponse
    {
        if (! $request->user()->tieneRol(['administrador', 'gerente'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para consultar la configuración de la empresa.',
            ], 403);
        }

        try {
            $empresa = Empresa::find($this->getEmpresaId($request));

            if (! $empresa) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No se encontró la empresa activa.',
                ], 404);
            }

            return response()->json([
                'status'  => 'success',
                'data'    => $this->formatearConfiguracion($empresa),
                'message' => 'Configuración obtenida correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener la configuración de la empresa.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * PUT /api/empresa/configuracion
     *
     * Actualiza nombre, RFC, dirección, teléfono y correo de la empresa
     * activa del usuario autenticado. Solo administrador.
     */
    public function actualizarConfiguracion(UpdateConfiguracionEmpresaRequest $request): JsonResponse
    {
        if (! $request->user()->tieneRol(['administrador'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para editar la configuración de la empresa.',
            ], 403);
        }

        try {
            $empresa = Empresa::find($this->getEmpresaId($request));

            if (! $empresa) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No se encontró la empresa activa.',
                ], 404);
            }

            $empresa->update([
                'nombre'    => $request->nombre_empresa,
                'rfc'       => $request->rfc,
                'direccion' => $request->direccion,
                'telefono'  => $request->telefono,
                'email'     => $request->correo,
            ]);

            return response()->json([
                'status'  => 'success',
                'data'    => $this->formatearConfiguracion($empresa),
                'message' => 'Configuración actualizada correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al actualizar la configuración de la empresa.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * POST /api/empresa/logo
     *
     * Sube (o reemplaza) el logo de la empresa activa del usuario autenticado.
     * Solo administrador.
     */
    public function subirLogo(UploadLogoEmpresaRequest $request): JsonResponse
    {
        if (! $request->user()->tieneRol(['administrador'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para actualizar el logo de la empresa.',
            ], 403);
        }

        try {
            $empresa = Empresa::find($this->getEmpresaId($request));

            if (! $empresa) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No se encontró la empresa activa.',
                ], 404);
            }

            // Eliminar el logo anterior si existía, antes de guardar el nuevo
            if ($empresa->logo_path) {
                $rutaAnterior = str_replace('/storage/', '', $empresa->logo_path);
                Storage::disk('public')->delete($rutaAnterior);
            }

            $path = $request->file('logo')->store('empresas/logos', 'public');
            $empresa->update(['logo_path' => '/storage/' . $path]);

            return response()->json([
                'status'  => 'success',
                'data'    => $this->formatearConfiguracion($empresa),
                'message' => 'Logo actualizado correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al actualizar el logo de la empresa.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Da forma al arreglo de configuración de empresa, con los nombres de
     * campo alineados al escritorio C# (nombre_empresa, correo).
     */
    private function formatearConfiguracion(Empresa $empresa): array
    {
        return [
            'id'             => $empresa->id,
            'nombre_empresa' => $empresa->nombre,
            'rfc'            => $empresa->rfc,
            'direccion'      => $empresa->direccion,
            'telefono'       => $empresa->telefono,
            'correo'         => $empresa->email,
            'logo_path'      => $empresa->logo_path,
        ];
    }
}
