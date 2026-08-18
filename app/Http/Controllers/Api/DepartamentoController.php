<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Departamento\StoreDepartamentoRequest;
use App\Http\Requests\Departamento\UpdateDepartamentoRequest;
use App\Http\Traits\AdminBypassTrait;
use App\Models\Departamento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de departamentos para la API SIGO.
 *
 * Lectura: administrador, gerente, supervisor, ingeniero.
 * Escritura (crear/editar/eliminar): solo administrador/gerente.
 */
class DepartamentoController extends Controller
{
    use AdminBypassTrait;

    /**
     * GET /api/departamentos
     */
    public function index(Request $request): JsonResponse
    {
        if ($error = $this->verificarLectura($request)) {
            return $error;
        }

        try {
            $departamentos = Departamento::where('empresa_id', $this->getEmpresaId($request))
                ->where('activo', true)
                ->with('rol:id,nombre')
                ->orderBy('nombre')
                ->get();

            return response()->json([
                'status'  => 'success',
                'data'    => $departamentos->map(fn (Departamento $d) => $this->formatearDepartamento($d))->values(),
                'message' => 'Departamentos obtenidos correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener los departamentos.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * POST /api/departamentos
     */
    public function store(StoreDepartamentoRequest $request): JsonResponse
    {
        if ($error = $this->verificarEscritura($request)) {
            return $error;
        }

        try {
            $departamento = Departamento::create([
                'empresa_id'  => $this->getEmpresaId($request),
                'nombre'      => $request->nombre,
                'descripcion' => $request->descripcion,
                'activo'      => true,
                'rol_id'      => $request->rol_id,
            ]);

            return response()->json([
                'status'  => 'success',
                'data'    => $this->formatearDepartamento($departamento),
                'message' => 'Departamento creado correctamente',
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al crear el departamento.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * PUT /api/departamentos/{id}
     */
    public function update(int $id, UpdateDepartamentoRequest $request): JsonResponse
    {
        if ($error = $this->verificarEscritura($request)) {
            return $error;
        }

        try {
            $departamento = Departamento::where('empresa_id', $this->getEmpresaId($request))->find($id);

            if (! $departamento) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Departamento no encontrado.',
                ], 404);
            }

            $departamento->update($request->only(['nombre', 'descripcion', 'activo', 'rol_id']));

            return response()->json([
                'status'  => 'success',
                'data'    => $this->formatearDepartamento($departamento),
                'message' => 'Departamento actualizado correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al actualizar el departamento.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * DELETE /api/departamentos/{id}
     * Soft delete: activo = false.
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        if ($error = $this->verificarEscritura($request)) {
            return $error;
        }

        try {
            $departamento = Departamento::where('empresa_id', $this->getEmpresaId($request))->find($id);

            if (! $departamento) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Departamento no encontrado.',
                ], 404);
            }

            if (! $departamento->activo) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'El departamento ya se encuentra desactivado.',
                ], 422);
            }

            $departamento->update(['activo' => false]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Departamento desactivado correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al desactivar el departamento.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    private function verificarLectura(Request $request): ?JsonResponse
    {
        if (! $request->user()->tieneRol(['administrador', 'gerente', 'supervisor', 'ingeniero'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para consultar departamentos.',
            ], 403);
        }

        return null;
    }

    private function verificarEscritura(Request $request): ?JsonResponse
    {
        if (! $request->user()->tieneRol(['administrador', 'gerente'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para modificar departamentos.',
            ], 403);
        }

        return null;
    }

    private function formatearDepartamento(Departamento $departamento): array
    {
        return [
            'id'          => $departamento->id,
            'nombre'      => $departamento->nombre,
            'descripcion' => $departamento->descripcion,
            'activo'      => $departamento->activo,
            'rol_id'      => $departamento->rol_id,
            'rol'         => $departamento->rol ? [
                'id'     => $departamento->rol->id,
                'nombre' => $departamento->rol->nombre,
            ] : null,
            'created_at'  => $departamento->created_at,
            'updated_at'  => $departamento->updated_at,
        ];
    }
}
