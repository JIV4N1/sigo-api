<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\AdminBypassTrait;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para el módulo de Clientes.
 *
 * Gestiona el catálogo de clientes (razones sociales) asociados a la empresa
 * del usuario autenticado mediante Sanctum.
 *
 * Nota de mapeo de campos (C# ↔ BD):
 *   nombre       → razon_social
 *   contacto     → nombre_contacto
 *   correo       → email
 *   fecha_creacion → created_at
 */
class ClienteController extends Controller
{
    use AdminBypassTrait;

    // =========================================================================
    // Métodos privados de apoyo
    // =========================================================================

    /**
     * Construye la consulta base de clientes para la empresa indicada.
     * Solo retorna registros activos, ordenados por nombre (razon_social).
     *
     * @param  int  $empresaId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function queryBase(int $empresaId)
    {
        return Cliente::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->orderBy('razon_social', 'asc');
    }

    /**
     * Formatea una colección de clientes al formato JSON esperado por la app C#.
     * Aplica el mapeo de nombres de campo: razon_social → nombre, etc.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $clientes
     * @return array
     */
    private function formatearClientes($clientes): array
    {
        return $clientes->map(function (Cliente $cliente) {
            return [
                'id'             => $cliente->id,
                'nombre'         => $cliente->razon_social,   // C#: Nombre
                'rfc'            => $cliente->rfc,
                'contacto'       => $cliente->nombre_contacto, // C#: Contacto
                'telefono'       => $cliente->telefono,
                'correo'         => $cliente->email,           // C#: Correo
                'direccion'      => $cliente->direccion,
                'activo'         => $cliente->activo,
                'empresa_id'     => $cliente->empresa_id,
                'fecha_creacion' => $cliente->created_at,      // C#: FechaCreacion
            ];
        })->values()->all();
    }

    /**
     * Reglas de validación compartidas entre store y update.
     *
     * @return array<string, array>
     */
    private function reglasValidacion(): array
    {
        return [
            'nombre'    => ['required', 'string', 'max:200'],
            'rfc'       => ['nullable', 'string', 'max:20'],
            'contacto'  => ['nullable', 'string', 'max:100'],
            'telefono'  => ['nullable', 'string', 'max:20'],
            'correo'    => ['nullable', 'email'],
            'direccion' => ['nullable', 'string', 'max:300'],
        ];
    }

    /**
     * Mensajes de validación en español.
     *
     * @return array<string, string>
     */
    private function mensajesValidacion(): array
    {
        return [
            'nombre.required' => 'La razón social del cliente es obligatoria.',
            'nombre.max'      => 'La razón social no puede superar los 200 caracteres.',
            'rfc.max'         => 'El RFC no puede superar los 20 caracteres.',
            'contacto.max'    => 'El nombre del contacto no puede superar los 100 caracteres.',
            'telefono.max'    => 'El teléfono no puede superar los 20 caracteres.',
            'correo.email'    => 'El formato del correo electrónico no es válido.',
            'direccion.max'   => 'La dirección no puede superar los 300 caracteres.',
        ];
    }

    /**
     * Verifica que el usuario tenga empresa asignada y retorna su empresa_id.
     * Retorna null si no tiene empresa, junto con la respuesta de error lista para enviar.
     *
     * @param  Request  $request
     * @param  int|null  &$empresaId  [output]
     * @return JsonResponse|null  Respuesta de error, o null si todo está bien
     */
    private function obtenerEmpresaId(Request $request, ?int &$empresaId): ?JsonResponse
    {
        $empresaId = $this->getEmpresaId($request);

        if (! $empresaId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'El usuario no tiene una empresa asignada.',
            ], 422);
        }

        return null;
    }

    // =========================================================================
    // Endpoints públicos
    // =========================================================================

    /**
     * GET /api/clientes
     *
     * Retorna todos los clientes activos de la empresa del usuario autenticado,
     * ordenados por razón social ascendente.
     */
    public function index(Request $request): JsonResponse
    {
        if ($error = $this->obtenerEmpresaId($request, $empresaId)) {
            return $error;
        }

        $clientes = $this->queryBase($empresaId)->get();

        return response()->json([
            'status'  => 'success',
            'data'    => [
                'clientes' => $this->formatearClientes($clientes),
            ],
            'message' => 'Clientes obtenidos correctamente',
        ]);
    }

    /**
     * GET /api/clientes/search?busqueda=texto
     *
     * Busca clientes por razón social o RFC (búsqueda parcial, insensible a mayúsculas).
     * Si no se envía el parámetro busqueda, retorna todos los clientes activos.
     */
    public function search(Request $request): JsonResponse
    {
        if ($error = $this->obtenerEmpresaId($request, $empresaId)) {
            return $error;
        }

        $busqueda = $request->query('busqueda');
        $query    = $this->queryBase($empresaId);

        // Aplicar filtro de texto solo si se proporcionó
        if ($busqueda) {
            $termino = '%' . $busqueda . '%';
            $query->where(function ($q) use ($termino) {
                $q->where('razon_social', 'ILIKE', $termino)
                  ->orWhere('rfc', 'ILIKE', $termino);
            });
        }

        $clientes = $query->get();

        return response()->json([
            'status'  => 'success',
            'data'    => [
                'clientes' => $this->formatearClientes($clientes),
            ],
            'message' => 'Clientes obtenidos correctamente',
        ]);
    }

    /**
     * POST /api/clientes
     *
     * Crea un nuevo cliente en el catálogo de la empresa del usuario.
     * Asigna automáticamente el empresa_id del usuario y activo = true.
     * Retorna HTTP 201 con los datos del cliente creado.
     */
    public function store(Request $request): JsonResponse
    {
        if ($error = $this->obtenerEmpresaId($request, $empresaId)) {
            return $error;
        }

        $validated = $request->validate(
            $this->reglasValidacion(),
            $this->mensajesValidacion()
        );

        // Mapear los nombres del request (C# → BD)
        $cliente = Cliente::create([
            'razon_social'    => $validated['nombre'],
            'rfc'             => $validated['rfc'] ?? null,
            'nombre_contacto' => $validated['contacto'] ?? null,
            'telefono'        => $validated['telefono'] ?? null,
            'email'           => $validated['correo'] ?? null,
            'direccion'       => $validated['direccion'] ?? null,
            'empresa_id'      => $empresaId,
            'activo'          => true,
        ]);

        return response()->json([
            'status'  => 'success',
            'data'    => [
                'cliente' => $this->formatearClientes(collect([$cliente]))[0],
            ],
            'message' => 'Cliente creado correctamente',
        ], 201);
    }

    /**
     * PUT /api/clientes/{id}
     *
     * Actualiza los datos de un cliente existente.
     * Verifica que el cliente pertenezca a la empresa del usuario autenticado.
     * Retorna 403 si el cliente pertenece a otra empresa.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if ($error = $this->obtenerEmpresaId($request, $empresaId)) {
            return $error;
        }

        // Buscar el cliente independientemente del estado activo
        $cliente = Cliente::find($id);

        if (! $cliente) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Cliente no encontrado.',
            ], 404);
        }

        // Verificar que el cliente pertenezca a la empresa del usuario
        if ($cliente->empresa_id !== $empresaId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tiene permisos para modificar este cliente.',
            ], 403);
        }

        $validated = $request->validate(
            $this->reglasValidacion(),
            $this->mensajesValidacion()
        );

        // Mapear los nombres del request (C# → BD) y actualizar
        $cliente->update([
            'razon_social'    => $validated['nombre'],
            'rfc'             => $validated['rfc'] ?? null,
            'nombre_contacto' => $validated['contacto'] ?? null,
            'telefono'        => $validated['telefono'] ?? null,
            'email'           => $validated['correo'] ?? null,
            'direccion'       => $validated['direccion'] ?? null,
        ]);

        return response()->json([
            'status'  => 'success',
            'data'    => [
                'cliente' => $this->formatearClientes(collect([$cliente->fresh()]))[0],
            ],
            'message' => 'Cliente actualizado correctamente',
        ]);
    }

    /**
     * PATCH /api/clientes/{id}/desactivar
     *
     * Desactiva un cliente cambiando activo = false (soft delete lógico).
     * No elimina el registro de la base de datos para preservar la integridad
     * referencial con los proyectos asociados al cliente.
     * Retorna 403 si el cliente pertenece a otra empresa.
     */
    public function desactivar(Request $request, int $id): JsonResponse
    {
        if ($error = $this->obtenerEmpresaId($request, $empresaId)) {
            return $error;
        }

        $cliente = Cliente::find($id);

        if (! $cliente) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Cliente no encontrado.',
            ], 404);
        }

        // Verificar que el cliente pertenezca a la empresa del usuario
        if ($cliente->empresa_id !== $empresaId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tiene permisos para modificar este cliente.',
            ], 403);
        }

        // Verificar que no esté ya desactivado
        if (! $cliente->activo) {
            return response()->json([
                'status'  => 'error',
                'message' => 'El cliente ya se encuentra desactivado.',
            ], 422);
        }

        // Desactivar el cliente sin eliminar el registro
        $cliente->update(['activo' => false]);

        return response()->json([
            'status'  => 'success',
            'data'    => [
                'cliente' => [
                    'id'     => $cliente->id,
                    'nombre' => $cliente->razon_social,
                    'activo' => $cliente->activo,
                ],
            ],
            'message' => 'Cliente desactivado correctamente',
        ]);
    }
}
