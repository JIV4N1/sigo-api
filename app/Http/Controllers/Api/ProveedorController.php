<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proveedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para el módulo de Proveedores.
 *
 * Gestiona el catálogo de proveedores asociados a la empresa
 * del usuario autenticado mediante Sanctum.
 */
class ProveedorController extends Controller
{
    // =========================================================================
    // Métodos privados de apoyo
    // =========================================================================

    /**
     * Construye la consulta base de proveedores para la empresa.
     */
    private function queryBase(int $empresaId)
    {
        return Proveedor::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->orderBy('nombre', 'asc');
    }

    /**
     * Formatea la colección de proveedores para la respuesta.
     */
    private function formatearProveedores($proveedores): array
    {
        return $proveedores->map(function (Proveedor $proveedor) {
            return [
                'id'             => $proveedor->id,
                'nombre'         => $proveedor->nombre,
                'contacto'       => $proveedor->contacto,
                'telefono'       => $proveedor->telefono,
                'email'          => $proveedor->email,
                'direccion'      => $proveedor->direccion,
                'activo'         => $proveedor->activo,
                'empresa_id'     => $proveedor->empresa_id,
                'fecha_creacion' => $proveedor->created_at,
            ];
        })->values()->all();
    }

    // =========================================================================
    // Endpoints públicos
    // =========================================================================

    /**
     * GET /api/proveedores
     *
     * Retorna todos los proveedores activos de la empresa del usuario,
     * ordenados por nombre ascendente.
     */
    public function index(Request $request): JsonResponse
    {
        $empresaId = $request->user()->empresa_id;

        if (!$empresaId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'El usuario no tiene una empresa asignada.',
            ], 422);
        }

        $proveedores = $this->queryBase($empresaId)->get();

        return response()->json([
            'status'  => 'success',
            'data'    => [
                'proveedores' => $this->formatearProveedores($proveedores),
            ],
            'message' => 'Proveedores obtenidos correctamente',
        ]);
    }

    /**
     * GET /api/proveedores/search
     *
     * Busca proveedores por texto (nombre o contacto).
     */
    public function search(Request $request): JsonResponse
    {
        $empresaId = $request->user()->empresa_id;

        if (!$empresaId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'El usuario no tiene una empresa asignada.',
            ], 422);
        }

        $busqueda = $request->query('busqueda');
        $query = $this->queryBase($empresaId);

        if ($busqueda) {
            $termino = '%' . $busqueda . '%';
            $query->where(function ($q) use ($termino) {
                $q->where('nombre', 'ILIKE', $termino)
                  ->orWhere('contacto', 'ILIKE', $termino);
            });
        }

        $proveedores = $query->get();

        return response()->json([
            'status'  => 'success',
            'data'    => [
                'proveedores' => $this->formatearProveedores($proveedores),
            ],
            'message' => 'Proveedores obtenidos correctamente',
        ]);
    }

    /**
     * POST /api/proveedores
     *
     * Crea un nuevo proveedor en el catálogo de la empresa.
     */
    public function store(Request $request): JsonResponse
    {
        $empresaId = $request->user()->empresa_id;

        if (!$empresaId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'El usuario no tiene una empresa asignada.',
            ], 422);
        }

        $validated = $request->validate([
            'nombre'    => ['required', 'string', 'max:200'],
            'contacto'  => ['nullable', 'string', 'max:100'],
            'telefono'  => ['nullable', 'string', 'max:20'],
            'email'     => ['nullable', 'email'],
            'direccion' => ['nullable', 'string', 'max:300'],
        ], [
            'nombre.required'   => 'El nombre del proveedor es obligatorio.',
            'nombre.max'        => 'El nombre no puede superar los 200 caracteres.',
            'contacto.max'      => 'El contacto no puede superar los 100 caracteres.',
            'telefono.max'      => 'El teléfono no puede superar los 20 caracteres.',
            'email.email'       => 'El formato del correo electrónico no es válido.',
            'direccion.max'     => 'La dirección no puede superar los 300 caracteres.',
        ]);

        $proveedor = Proveedor::create([
            ...$validated,
            'empresa_id' => $empresaId,
            'activo'     => true,
        ]);

        return response()->json([
            'status'  => 'success',
            'data'    => [
                'proveedor' => [
                    'id'             => $proveedor->id,
                    'nombre'         => $proveedor->nombre,
                    'contacto'       => $proveedor->contacto,
                    'telefono'       => $proveedor->telefono,
                    'email'          => $proveedor->email,
                    'direccion'      => $proveedor->direccion,
                    'activo'         => $proveedor->activo,
                    'empresa_id'     => $proveedor->empresa_id,
                    'fecha_creacion' => $proveedor->created_at,
                ],
            ],
            'message' => 'Proveedor creado correctamente',
        ], 201);
    }

    /**
     * PUT /api/proveedores/{id}
     *
     * Actualiza los datos de un proveedor.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $empresaId = $request->user()->empresa_id;

        if (!$empresaId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'El usuario no tiene una empresa asignada.',
            ], 422);
        }

        $proveedor = Proveedor::where('id', $id)->first();

        if (!$proveedor) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Proveedor no encontrado.',
            ], 404);
        }

        if ($proveedor->empresa_id !== $empresaId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tiene permisos para modificar este proveedor.',
            ], 403);
        }

        $validated = $request->validate([
            'nombre'    => ['required', 'string', 'max:200'],
            'contacto'  => ['nullable', 'string', 'max:100'],
            'telefono'  => ['nullable', 'string', 'max:20'],
            'email'     => ['nullable', 'email'],
            'direccion' => ['nullable', 'string', 'max:300'],
        ], [
            'nombre.required'   => 'El nombre del proveedor es obligatorio.',
            'nombre.max'        => 'El nombre no puede superar los 200 caracteres.',
            'contacto.max'      => 'El contacto no puede superar los 100 caracteres.',
            'telefono.max'      => 'El teléfono no puede superar los 20 caracteres.',
            'email.email'       => 'El formato del correo electrónico no es válido.',
            'direccion.max'     => 'La dirección no puede superar los 300 caracteres.',
        ]);

        $proveedor->update($validated);

        return response()->json([
            'status'  => 'success',
            'data'    => [
                'proveedor' => $this->formatearProveedores(collect([$proveedor]))[0],
            ],
            'message' => 'Proveedor actualizado correctamente',
        ]);
    }

    /**
     * PATCH /api/proveedores/{id}/desactivar
     *
     * Desactiva un proveedor (soft delete lógico).
     */
    public function desactivar(Request $request, int $id): JsonResponse
    {
        $empresaId = $request->user()->empresa_id;

        if (!$empresaId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'El usuario no tiene una empresa asignada.',
            ], 422);
        }

        $proveedor = Proveedor::where('id', $id)->first();

        if (!$proveedor) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Proveedor no encontrado.',
            ], 404);
        }

        if ($proveedor->empresa_id !== $empresaId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tiene permisos para modificar este proveedor.',
            ], 403);
        }

        $proveedor->update(['activo' => false]);

        return response()->json([
            'status'  => 'success',
            'data'    => [
                'proveedor' => [
                    'id'     => $proveedor->id,
                    'nombre' => $proveedor->nombre,
                    'activo' => $proveedor->activo,
                ],
            ],
            'message' => 'Proveedor desactivado correctamente',
        ]);
    }
}
