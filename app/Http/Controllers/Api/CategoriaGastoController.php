<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoriaGasto\StoreCategoriaGastoRequest;
use App\Http\Requests\CategoriaGasto\UpdateCategoriaGastoRequest;
use App\Http\Traits\AdminBypassTrait;
use App\Models\CategoriaGasto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de categorías de gasto para la API SIGO.
 *
 * Lectura: administrador, gerente, supervisor, ingeniero.
 * Escritura (crear/editar/eliminar): solo administrador/gerente.
 */
class CategoriaGastoController extends Controller
{
    use AdminBypassTrait;

    /**
     * GET /api/gastos-obra/categorias
     */
    public function index(Request $request): JsonResponse
    {
        if ($error = $this->verificarLectura($request)) {
            return $error;
        }

        try {
            $categorias = CategoriaGasto::where('empresa_id', $this->getEmpresaId($request))
                ->where('activo', true)
                ->orderBy('nombre')
                ->get();

            return response()->json([
                'status'  => 'success',
                'data'    => $categorias->map(fn (CategoriaGasto $c) => $this->formatearCategoria($c))->values(),
                'message' => 'Categorías obtenidas correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener las categorías.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * POST /api/gastos-obra/categorias
     */
    public function store(StoreCategoriaGastoRequest $request): JsonResponse
    {
        if ($error = $this->verificarEscritura($request)) {
            return $error;
        }

        try {
            $categoria = CategoriaGasto::create([
                'empresa_id'  => $this->getEmpresaId($request),
                'nombre'      => $request->nombre,
                'descripcion' => $request->descripcion,
                'activo'      => true,
            ]);

            return response()->json([
                'status'  => 'success',
                'data'    => $this->formatearCategoria($categoria),
                'message' => 'Categoría creada correctamente',
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al crear la categoría.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * PUT /api/gastos-obra/categorias/{id}
     */
    public function update(int $id, UpdateCategoriaGastoRequest $request): JsonResponse
    {
        if ($error = $this->verificarEscritura($request)) {
            return $error;
        }

        try {
            $categoria = CategoriaGasto::where('empresa_id', $this->getEmpresaId($request))->find($id);

            if (! $categoria) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Categoría no encontrada.',
                ], 404);
            }

            $categoria->update($request->only(['nombre', 'descripcion', 'activo']));

            return response()->json([
                'status'  => 'success',
                'data'    => $this->formatearCategoria($categoria),
                'message' => 'Categoría actualizada correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al actualizar la categoría.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * DELETE /api/gastos-obra/categorias/{id}
     * Soft delete: activo = false.
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        if ($error = $this->verificarEscritura($request)) {
            return $error;
        }

        try {
            $categoria = CategoriaGasto::where('empresa_id', $this->getEmpresaId($request))->find($id);

            if (! $categoria) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Categoría no encontrada.',
                ], 404);
            }

            if (! $categoria->activo) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'La categoría ya se encuentra desactivada.',
                ], 422);
            }

            $categoria->update(['activo' => false]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Categoría desactivada correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al desactivar la categoría.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    private function verificarLectura(Request $request): ?JsonResponse
    {
        if (! $request->user()->tieneRol(['administrador', 'gerente', 'supervisor', 'ingeniero'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para consultar categorías de gasto.',
            ], 403);
        }

        return null;
    }

    private function verificarEscritura(Request $request): ?JsonResponse
    {
        if (! $request->user()->tieneRol(['administrador', 'gerente'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para modificar categorías de gasto.',
            ], 403);
        }

        return null;
    }

    private function formatearCategoria(CategoriaGasto $categoria): array
    {
        return [
            'id'          => $categoria->id,
            'nombre'      => $categoria->nombre,
            'descripcion' => $categoria->descripcion,
            'activo'      => $categoria->activo,
            'created_at'  => $categoria->created_at,
            'updated_at'  => $categoria->updated_at,
        ];
    }
}
