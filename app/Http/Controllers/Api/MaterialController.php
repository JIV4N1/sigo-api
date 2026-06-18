<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para el módulo de Materiales.
 *
 * Gestiona el catálogo de materiales e insumos de construcción
 * asociados a la empresa del usuario autenticado mediante Sanctum.
 */
class MaterialController extends Controller
{
    // =========================================================================
    // Métodos privados de apoyo
    // =========================================================================

    /**
     * Construye la consulta base de materiales para la empresa del usuario.
     * Incluye la relación con proveedor (id, nombre) y solo materiales activos.
     *
     * @param  int  $empresaId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function queryBase(int $empresaId)
    {
        return Material::with('proveedor:id,nombre')
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->orderBy('nombre', 'asc');
    }

    /**
     * Formatea una colección de materiales al formato de respuesta esperado.
     * Mapea created_at → fecha_creacion y extrae solo los campos requeridos.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $materiales
     * @return array
     */
    private function formatearMateriales($materiales): array
    {
        return $materiales->map(function (Material $material) {
            return [
                'id'            => $material->id,
                'codigo'        => $material->codigo,
                'nombre'        => $material->nombre,
                'descripcion'   => $material->descripcion,
                'unidad_medida' => $material->unidad_medida,
                'precio_compra' => $material->precio_compra,
                'precio_venta'  => $material->precio_venta,
                'stock_actual'  => $material->stock_actual,
                'stock_minimo'  => $material->stock_minimo,
                'proveedor_id'  => $material->proveedor_id,
                'proveedor'     => $material->proveedor
                    ? ['id' => $material->proveedor->id, 'nombre' => $material->proveedor->nombre]
                    : null,
                'activo'        => $material->activo,
                'fecha_creacion' => $material->created_at,
            ];
        })->values()->all();
    }

    // =========================================================================
    // Endpoints públicos
    // =========================================================================

    /**
     * GET /api/materiales
     *
     * Retorna todos los materiales activos de la empresa del usuario,
     * con la relación proveedor incluida, ordenados por nombre.
     */
    public function index(Request $request): JsonResponse
    {
        $empresaId = $request->user()->empresa_id;

        $materiales = $this->queryBase($empresaId)->get();

        return response()->json([
            'status'  => 'success',
            'data'    => [
                'materiales' => $this->formatearMateriales($materiales),
            ],
            'message' => 'Materiales obtenidos correctamente',
        ]);
    }

    /**
     * GET /api/materiales/search
     *
     * Busca materiales por texto (nombre o código) y/o filtra por proveedor.
     * Query params:
     *   - busqueda  (opcional): texto a buscar en nombre o código
     *   - proveedor_id (opcional): ID del proveedor para filtrar
     *
     * Si no se envía ningún filtro, retorna todos los materiales activos.
     */
    public function search(Request $request): JsonResponse
    {
        $empresaId   = $request->user()->empresa_id;
        $busqueda    = $request->query('busqueda');
        $proveedorId = $request->query('proveedor_id');

        $query = $this->queryBase($empresaId);

        // Filtrar por texto en nombre o código (búsqueda parcial)
        if ($busqueda) {
            $termino = '%' . $busqueda . '%';
            $query->where(function ($q) use ($termino) {
                $q->where('nombre', 'ILIKE', $termino)
                  ->orWhere('codigo', 'ILIKE', $termino);
            });
        }

        // Filtrar por proveedor si se proporciona
        if ($proveedorId) {
            $query->where('proveedor_id', $proveedorId);
        }

        $materiales = $query->get();

        return response()->json([
            'status'  => 'success',
            'data'    => [
                'materiales' => $this->formatearMateriales($materiales),
            ],
            'message' => 'Materiales obtenidos correctamente',
        ]);
    }

    /**
     * POST /api/materiales
     *
     * Crea un nuevo material en el catálogo de la empresa del usuario.
     * Retorna HTTP 201 con los datos básicos del material creado.
     */
    public function store(Request $request): JsonResponse
    {
        // Validar los datos de entrada con mensajes en español
        $validated = $request->validate([
            'codigo'        => ['required', 'string', 'max:50'],
            'nombre'        => ['required', 'string', 'max:200'],
            'descripcion'   => ['nullable', 'string'],
            'unidad_medida' => ['required', 'string'],
            'precio_compra' => ['required', 'numeric', 'min:0'],
            'precio_venta'  => ['required', 'numeric', 'min:0'],
            'stock_actual'  => ['required', 'numeric', 'min:0'],
            'stock_minimo'  => ['required', 'numeric', 'min:0'],
            'proveedor_id'  => ['required', 'exists:proveedores,id'],
        ], [
            // Mensajes de validación en español
            'codigo.required'        => 'El código del material es obligatorio.',
            'codigo.max'             => 'El código no puede superar los 50 caracteres.',
            'nombre.required'        => 'El nombre del material es obligatorio.',
            'nombre.max'             => 'El nombre no puede superar los 200 caracteres.',
            'unidad_medida.required' => 'La unidad de medida es obligatoria.',
            'precio_compra.required' => 'El precio de compra es obligatorio.',
            'precio_compra.numeric'  => 'El precio de compra debe ser un número.',
            'precio_compra.min'      => 'El precio de compra no puede ser negativo.',
            'precio_venta.required'  => 'El precio de venta es obligatorio.',
            'precio_venta.numeric'   => 'El precio de venta debe ser un número.',
            'precio_venta.min'       => 'El precio de venta no puede ser negativo.',
            'stock_actual.required'  => 'El stock actual es obligatorio.',
            'stock_actual.numeric'   => 'El stock actual debe ser un número.',
            'stock_actual.min'       => 'El stock actual no puede ser negativo.',
            'stock_minimo.required'  => 'El stock mínimo es obligatorio.',
            'stock_minimo.numeric'   => 'El stock mínimo debe ser un número.',
            'stock_minimo.min'       => 'El stock mínimo no puede ser negativo.',
            'proveedor_id.required'  => 'Debe seleccionar un proveedor.',
            'proveedor_id.exists'    => 'El proveedor seleccionado no existe.',
        ]);

        // Crear el material asignando empresa y estado activo
        $material = Material::create([
            ...$validated,
            'empresa_id' => $request->user()->empresa_id,
            'activo'     => true,
        ]);

        return response()->json([
            'status'  => 'success',
            'data'    => [
                'material' => [
                    'id'         => $material->id,
                    'codigo'     => $material->codigo,
                    'nombre'     => $material->nombre,
                    'activo'     => $material->activo,
                    'empresa_id' => $material->empresa_id,
                    'created_at' => $material->created_at,
                ],
            ],
            'message' => 'Material creado correctamente',
        ], 201);
    }

    /**
     * PUT /api/materiales/{id}
     *
     * Actualiza los datos de un material existente.
     * Verifica que el material pertenezca a la empresa del usuario autenticado.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $empresaId = $request->user()->empresa_id;

        // Verificar que el material existe y pertenece a la empresa del usuario
        $material = Material::where('id', $empresaId === null ? $id : $id)
            ->where('empresa_id', $empresaId)
            ->first();

        if (! $material) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Material no encontrado o no pertenece a su empresa.',
            ], 404);
        }

        // Validar los datos de entrada con mensajes en español
        $validated = $request->validate([
            'codigo'        => ['required', 'string', 'max:50'],
            'nombre'        => ['required', 'string', 'max:200'],
            'descripcion'   => ['nullable', 'string'],
            'unidad_medida' => ['required', 'string'],
            'precio_compra' => ['required', 'numeric', 'min:0'],
            'precio_venta'  => ['required', 'numeric', 'min:0'],
            'stock_actual'  => ['required', 'numeric', 'min:0'],
            'stock_minimo'  => ['required', 'numeric', 'min:0'],
            'proveedor_id'  => ['required', 'exists:proveedores,id'],
        ], [
            // Mensajes de validación en español
            'codigo.required'        => 'El código del material es obligatorio.',
            'codigo.max'             => 'El código no puede superar los 50 caracteres.',
            'nombre.required'        => 'El nombre del material es obligatorio.',
            'nombre.max'             => 'El nombre no puede superar los 200 caracteres.',
            'unidad_medida.required' => 'La unidad de medida es obligatoria.',
            'precio_compra.required' => 'El precio de compra es obligatorio.',
            'precio_compra.numeric'  => 'El precio de compra debe ser un número.',
            'precio_compra.min'      => 'El precio de compra no puede ser negativo.',
            'precio_venta.required'  => 'El precio de venta es obligatorio.',
            'precio_venta.numeric'   => 'El precio de venta debe ser un número.',
            'precio_venta.min'       => 'El precio de venta no puede ser negativo.',
            'stock_actual.required'  => 'El stock actual es obligatorio.',
            'stock_actual.numeric'   => 'El stock actual debe ser un número.',
            'stock_actual.min'       => 'El stock actual no puede ser negativo.',
            'stock_minimo.required'  => 'El stock mínimo es obligatorio.',
            'stock_minimo.numeric'   => 'El stock mínimo debe ser un número.',
            'stock_minimo.min'       => 'El stock mínimo no puede ser negativo.',
            'proveedor_id.required'  => 'Debe seleccionar un proveedor.',
            'proveedor_id.exists'    => 'El proveedor seleccionado no existe.',
        ]);

        // Actualizar el material con los datos validados
        $material->update($validated);

        return response()->json([
            'status'  => 'success',
            'data'    => [
                'material' => $this->formatearMateriales(
                    collect([$material->fresh('proveedor')])
                )[0],
            ],
            'message' => 'Material actualizado correctamente',
        ]);
    }

    /**
     * PATCH /api/materiales/{id}/desactivar
     *
     * Desactiva un material cambiando su campo activo a false.
     * No elimina el registro de la base de datos (soft delete lógico).
     * Verifica que el material pertenezca a la empresa del usuario autenticado.
     */
    public function desactivar(Request $request, int $id): JsonResponse
    {
        $empresaId = $request->user()->empresa_id;

        // Verificar que el material existe y pertenece a la empresa del usuario
        $material = Material::where('id', $id)
            ->where('empresa_id', $empresaId)
            ->first();

        if (! $material) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Material no encontrado o no pertenece a su empresa.',
            ], 404);
        }

        // Verificar que el material no esté ya desactivado
        if (! $material->activo) {
            return response()->json([
                'status'  => 'error',
                'message' => 'El material ya se encuentra desactivado.',
            ], 422);
        }

        // Desactivar el material (sin eliminar el registro)
        $material->update(['activo' => false]);

        return response()->json([
            'status'  => 'success',
            'data'    => [
                'material' => [
                    'id'     => $material->id,
                    'nombre' => $material->nombre,
                    'activo' => $material->activo,
                ],
            ],
            'message' => 'Material desactivado correctamente',
        ]);
    }
}
