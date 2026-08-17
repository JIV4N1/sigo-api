<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\AdminBypassTrait;
use App\Models\Maquinaria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Controlador para el módulo de Maquinaria.
 *
 * Gestiona el catálogo de maquinaria disponible para renta, asociado
 * a la empresa del usuario autenticado mediante Sanctum. Lectura abierta
 * a cualquier usuario autenticado; creación/edición/desactivación
 * restringidas a administrador/gerente.
 */
class MaquinariaController extends Controller
{
    use AdminBypassTrait;

    // =========================================================================
    // Métodos privados de apoyo
    // =========================================================================

    /**
     * Construye la consulta base de maquinaria para la empresa del usuario.
     * Solo maquinaria activa, ordenada por nombre.
     *
     * @param  int  $empresaId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function queryBase(int $empresaId)
    {
        return Maquinaria::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->orderBy('nombre', 'asc');
    }

    /**
     * Formatea una colección de maquinaria al formato de respuesta esperado.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $maquinaria
     * @return array
     */
    private function formatearMaquinaria($maquinaria): array
    {
        return $maquinaria->map(function (Maquinaria $item) {
            return [
                'id'           => $item->id,
                'codigo'       => $item->codigo,
                'nombre'       => $item->nombre,
                'descripcion'  => $item->descripcion,
                'unidad_renta' => $item->unidad_renta,
                'precio_hora'  => $item->precio_hora,
                'activo'       => $item->activo,
                'fecha_creacion' => $item->created_at,
            ];
        })->values()->all();
    }

    /**
     * Verifica que el usuario autenticado sea administrador o gerente.
     * Devuelve una respuesta 403 si no tiene permisos, o null si puede continuar.
     */
    private function verificarEscritura(Request $request): ?JsonResponse
    {
        if (! $request->user()->tieneRol(['administrador', 'gerente'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para modificar maquinaria.',
            ], 403);
        }

        return null;
    }

    // =========================================================================
    // Endpoints públicos
    // =========================================================================

    /**
     * GET /api/maquinaria
     *
     * Retorna toda la maquinaria activa de la empresa del usuario,
     * ordenada por nombre.
     */
    public function index(Request $request): JsonResponse
    {
        $empresaId = $this->getEmpresaId($request);

        if (! $empresaId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'El usuario no tiene una empresa asignada.',
            ], 422);
        }

        $maquinaria = $this->queryBase($empresaId)->get();

        return response()->json([
            'status'  => 'success',
            'data'    => [
                'maquinaria' => $this->formatearMaquinaria($maquinaria),
            ],
            'message' => 'Maquinaria obtenida correctamente',
        ]);
    }

    /**
     * GET /api/maquinaria/search
     *
     * Busca maquinaria por texto (nombre o código).
     * Query params:
     *   - busqueda (opcional): texto a buscar en nombre o código
     *
     * Si no se envía ningún filtro, retorna toda la maquinaria activa.
     */
    public function search(Request $request): JsonResponse
    {
        $empresaId = $this->getEmpresaId($request);

        if (! $empresaId) {
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
                  ->orWhere('codigo', 'ILIKE', $termino);
            });
        }

        $maquinaria = $query->get();

        return response()->json([
            'status'  => 'success',
            'data'    => [
                'maquinaria' => $this->formatearMaquinaria($maquinaria),
            ],
            'message' => 'Maquinaria obtenida correctamente',
        ]);
    }

    /**
     * GET /api/maquinaria/{id}
     *
     * Retorna el detalle de una maquinaria.
     * Verifica que pertenezca a la empresa del usuario autenticado.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $empresaId = $this->getEmpresaId($request);

        $maquinaria = Maquinaria::find($id);

        if (! $maquinaria) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Maquinaria no encontrada.',
            ], 404);
        }

        if ($maquinaria->empresa_id !== $empresaId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tiene permisos para consultar esta maquinaria.',
            ], 403);
        }

        return response()->json([
            'status'  => 'success',
            'data'    => [
                'maquinaria' => $this->formatearMaquinaria(collect([$maquinaria]))[0],
            ],
            'message' => 'Maquinaria obtenida correctamente',
        ]);
    }

    /**
     * POST /api/maquinaria
     *
     * Crea una nueva maquinaria en el catálogo de la empresa del usuario.
     * Solo administrador/gerente. Retorna HTTP 201.
     */
    public function store(Request $request): JsonResponse
    {
        if ($error = $this->verificarEscritura($request)) {
            return $error;
        }

        $empresaId = $this->getEmpresaId($request);

        $validated = $request->validate([
            'codigo'       => ['required', 'string', 'max:50', Rule::unique('maquinaria')->where('empresa_id', $empresaId)],
            'nombre'       => ['required', 'string', 'max:200'],
            'descripcion'  => ['nullable', 'string'],
            'unidad_renta' => ['required', 'string', Rule::in(['hora', 'día', 'semana', 'mes'])],
            'precio_hora'  => ['required', 'numeric', 'min:0'],
        ], [
            'codigo.required'       => 'El código es obligatorio.',
            'codigo.max'            => 'El código no puede superar los 50 caracteres.',
            'codigo.unique'         => 'Ya existe una maquinaria con este código.',
            'nombre.required'       => 'El nombre es obligatorio.',
            'nombre.max'            => 'El nombre no puede superar los 200 caracteres.',
            'unidad_renta.required' => 'La unidad de renta es obligatoria.',
            'unidad_renta.in'       => 'La unidad de renta debe ser: hora, día, semana o mes.',
            'precio_hora.required'  => 'El precio por hora es obligatorio.',
            'precio_hora.numeric'   => 'El precio por hora debe ser un número.',
            'precio_hora.min'       => 'El precio por hora no puede ser negativo.',
        ]);

        $maquinaria = Maquinaria::create([
            ...$validated,
            'empresa_id' => $empresaId,
            'activo'     => true,
        ]);

        return response()->json([
            'status'  => 'success',
            'data'    => [
                'maquinaria' => $this->formatearMaquinaria(collect([$maquinaria]))[0],
            ],
            'message' => 'Maquinaria creada correctamente',
        ], 201);
    }

    /**
     * PUT /api/maquinaria/{id}
     *
     * Actualiza los datos de una maquinaria existente. Solo administrador/gerente.
     * Verifica que pertenezca a la empresa del usuario autenticado.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if ($error = $this->verificarEscritura($request)) {
            return $error;
        }

        $empresaId = $this->getEmpresaId($request);

        $maquinaria = Maquinaria::where('id', $id)
            ->where('empresa_id', $empresaId)
            ->first();

        if (! $maquinaria) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Maquinaria no encontrada o no pertenece a su empresa.',
            ], 404);
        }

        $validated = $request->validate([
            'codigo'       => ['required', 'string', 'max:50', Rule::unique('maquinaria')->where('empresa_id', $empresaId)->ignore($maquinaria->id)],
            'nombre'       => ['required', 'string', 'max:200'],
            'descripcion'  => ['nullable', 'string'],
            'unidad_renta' => ['required', 'string', Rule::in(['hora', 'día', 'semana', 'mes'])],
            'precio_hora'  => ['required', 'numeric', 'min:0'],
        ], [
            'codigo.required'       => 'El código es obligatorio.',
            'codigo.max'            => 'El código no puede superar los 50 caracteres.',
            'codigo.unique'         => 'Ya existe una maquinaria con este código.',
            'nombre.required'       => 'El nombre es obligatorio.',
            'nombre.max'            => 'El nombre no puede superar los 200 caracteres.',
            'unidad_renta.required' => 'La unidad de renta es obligatoria.',
            'unidad_renta.in'       => 'La unidad de renta debe ser: hora, día, semana o mes.',
            'precio_hora.required'  => 'El precio por hora es obligatorio.',
            'precio_hora.numeric'   => 'El precio por hora debe ser un número.',
            'precio_hora.min'       => 'El precio por hora no puede ser negativo.',
        ]);

        $maquinaria->update($validated);

        return response()->json([
            'status'  => 'success',
            'data'    => [
                'maquinaria' => $this->formatearMaquinaria(collect([$maquinaria]))[0],
            ],
            'message' => 'Maquinaria actualizada correctamente',
        ]);
    }

    /**
     * PATCH /api/maquinaria/{id}/desactivar
     *
     * Desactiva una maquinaria cambiando su campo activo a false.
     * No elimina el registro de la base de datos. Solo administrador/gerente.
     */
    public function desactivar(Request $request, int $id): JsonResponse
    {
        if ($error = $this->verificarEscritura($request)) {
            return $error;
        }

        $empresaId = $this->getEmpresaId($request);

        $maquinaria = Maquinaria::where('id', $id)
            ->where('empresa_id', $empresaId)
            ->first();

        if (! $maquinaria) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Maquinaria no encontrada o no pertenece a su empresa.',
            ], 404);
        }

        if (! $maquinaria->activo) {
            return response()->json([
                'status'  => 'error',
                'message' => 'La maquinaria ya se encuentra desactivada.',
            ], 422);
        }

        $maquinaria->update(['activo' => false]);

        return response()->json([
            'status'  => 'success',
            'data'    => [
                'maquinaria' => [
                    'id'     => $maquinaria->id,
                    'nombre' => $maquinaria->nombre,
                    'activo' => $maquinaria->activo,
                ],
            ],
            'message' => 'Maquinaria desactivada correctamente',
        ]);
    }
}
