<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GastoObra\StoreGastoObraRequest;
use App\Http\Requests\GastoObra\UpdateGastoObraRequest;
use App\Http\Traits\AdminBypassTrait;
use App\Models\GastoObra;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Controlador de gastos de obra para la API SIGO.
 *
 * Lectura (index/search/show/resumen): administrador, gerente, supervisor, ingeniero.
 * Escritura (store/update/destroy): solo administrador/gerente.
 */
class GastoObraController extends Controller
{
    use AdminBypassTrait;

    /**
     * GET /api/gastos-obra
     * Filtros: ?proyecto_id=, ?categoria_id=, ?desde=, ?hasta=, ?search=
     */
    public function index(Request $request): JsonResponse
    {
        if ($error = $this->verificarLectura($request)) {
            return $error;
        }

        try {
            $paginado = $this->construirQuery($request)
                ->orderByDesc('fecha')
                ->paginate(15);

            $paginado->setCollection(
                $paginado->getCollection()->map(fn (GastoObra $g) => $this->formatearGasto($g))
            );

            return response()->json([
                'status'  => 'success',
                'data'    => $paginado,
                'message' => 'Gastos obtenidos correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener los gastos.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * GET /api/gastos-obra/search
     * Mismos filtros que index(); pensado para búsqueda por ?search= en
     * proyecto.nombre o descripcion.
     */
    public function search(Request $request): JsonResponse
    {
        if ($error = $this->verificarLectura($request)) {
            return $error;
        }

        try {
            $paginado = $this->construirQuery($request)
                ->orderByDesc('fecha')
                ->paginate(15);

            $paginado->setCollection(
                $paginado->getCollection()->map(fn (GastoObra $g) => $this->formatearGasto($g))
            );

            return response()->json([
                'status'  => 'success',
                'data'    => $paginado,
                'message' => 'Búsqueda realizada correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al buscar gastos.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * GET /api/gastos-obra/{id}
     */
    public function show(int $id, Request $request): JsonResponse
    {
        if ($error = $this->verificarLectura($request)) {
            return $error;
        }

        try {
            $gasto = GastoObra::with(['proyecto', 'categoria', 'proveedor', 'usuario'])->find($id);

            if (! $gasto) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Gasto no encontrado.',
                ], 404);
            }

            if ($gasto->empresa_id !== $this->getEmpresaId($request)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No tiene permisos para consultar este gasto.',
                ], 403);
            }

            return response()->json([
                'status'  => 'success',
                'data'    => $this->formatearGasto($gasto),
                'message' => 'Gasto obtenido correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener el gasto.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * POST /api/gastos-obra
     * usuario_id siempre se toma del usuario autenticado, nunca del body.
     */
    public function store(StoreGastoObraRequest $request): JsonResponse
    {
        if ($error = $this->verificarCreacionEdicion($request)) {
            return $error;
        }

        try {
            $comprobantePath = null;
            if ($request->hasFile('comprobante')) {
                $path = $request->file('comprobante')->store('gastos-obra', 'public');
                $comprobantePath = '/storage/' . $path;
            }

            $gasto = GastoObra::create([
                'empresa_id'       => $this->getEmpresaId($request),
                'proyecto_id'      => $request->proyecto_id,
                'categoria_id'     => $request->categoria_id,
                'proveedor_id'     => $request->proveedor_id,
                'monto'            => $request->monto,
                'fecha'            => $request->fecha,
                'descripcion'      => $request->descripcion,
                'comprobante_path' => $comprobantePath,
                'usuario_id'       => $request->user()->id,
                'activo'           => true,
            ]);

            $gasto->load(['proyecto', 'categoria', 'proveedor', 'usuario']);

            return response()->json([
                'status'  => 'success',
                'data'    => $this->formatearGasto($gasto),
                'message' => 'Gasto creado correctamente',
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al crear el gasto.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * PUT /api/gastos-obra/{id}
     * Si se envía un nuevo comprobante, reemplaza (y borra) el anterior.
     */
    public function update(int $id, UpdateGastoObraRequest $request): JsonResponse
    {
        if ($error = $this->verificarCreacionEdicion($request)) {
            return $error;
        }

        try {
            $gasto = GastoObra::where('empresa_id', $this->getEmpresaId($request))->find($id);

            if (! $gasto) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Gasto no encontrado.',
                ], 404);
            }

            // proyecto_id es opcional en la edición: si no viene en el request,
            // la validación del Form Request no lo revisa. Verificamos aquí el
            // proyecto ACTUAL del gasto para que supervisor/ingeniero no puedan
            // editar gastos de proyectos donde no están asignados simplemente
            // omitiendo el campo.
            if (! $this->tieneAccesoAProyecto($request, $gasto->proyecto_id)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No tienes acceso a este proyecto.',
                ], 403);
            }

            $datos = $request->only(['proyecto_id', 'categoria_id', 'proveedor_id', 'monto', 'fecha', 'descripcion']);

            if ($request->hasFile('comprobante')) {
                if ($gasto->comprobante_path) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $gasto->comprobante_path));
                }

                $path = $request->file('comprobante')->store('gastos-obra', 'public');
                $datos['comprobante_path'] = '/storage/' . $path;
            }

            $gasto->update($datos);
            $gasto->load(['proyecto', 'categoria', 'proveedor', 'usuario']);

            return response()->json([
                'status'  => 'success',
                'data'    => $this->formatearGasto($gasto),
                'message' => 'Gasto actualizado correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al actualizar el gasto.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * DELETE /api/gastos-obra/{id}
     * Soft delete: activo = false.
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        if ($error = $this->verificarEscritura($request)) {
            return $error;
        }

        try {
            $gasto = GastoObra::where('empresa_id', $this->getEmpresaId($request))->find($id);

            if (! $gasto) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Gasto no encontrado.',
                ], 404);
            }

            if (! $gasto->activo) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'El gasto ya se encuentra desactivado.',
                ], 422);
            }

            $gasto->update(['activo' => false]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Gasto desactivado correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al desactivar el gasto.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * GET /api/gastos-obra/resumen
     * Filtros opcionales: ?proyecto_id=, ?desde=, ?hasta=
     */
    public function resumen(Request $request): JsonResponse
    {
        if ($error = $this->verificarLectura($request)) {
            return $error;
        }

        try {
            $base = DB::table('gastos_obra')
                ->where('gastos_obra.empresa_id', $this->getEmpresaId($request))
                ->where('gastos_obra.activo', true);

            if ($request->filled('proyecto_id')) {
                $base->where('gastos_obra.proyecto_id', $request->proyecto_id);
            }

            if ($request->filled('desde')) {
                $base->whereDate('gastos_obra.fecha', '>=', $request->query('desde'));
            }

            if ($request->filled('hasta')) {
                $base->whereDate('gastos_obra.fecha', '<=', $request->query('hasta'));
            }

            $porCategoria = (clone $base)
                ->join('categorias_gasto as c', 'c.id', '=', 'gastos_obra.categoria_id')
                ->selectRaw('c.nombre as categoria, SUM(gastos_obra.monto) as total')
                ->groupBy('c.nombre')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($fila) => ['categoria' => $fila->categoria, 'total' => round((float) $fila->total, 2)])
                ->values();

            $porProyecto = (clone $base)
                ->join('proyectos as p', 'p.id', '=', 'gastos_obra.proyecto_id')
                ->selectRaw('p.nombre as proyecto, SUM(gastos_obra.monto) as total')
                ->groupBy('p.nombre')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($fila) => ['proyecto' => $fila->proyecto, 'total' => round((float) $fila->total, 2)])
                ->values();

            $totalGeneral = round((float) (clone $base)->sum('gastos_obra.monto'), 2);

            return response()->json([
                'status'  => 'success',
                'data'    => [
                    'por_categoria' => $porCategoria,
                    'por_proyecto'  => $porProyecto,
                    'total_general' => $totalGeneral,
                ],
                'message' => 'Resumen obtenido correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener el resumen.',
                'errors'  => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Construye la consulta base de gastos con todos los filtros comunes
     * a index()/search(): proyecto_id, categoria_id, desde, hasta, search.
     */
    private function construirQuery(Request $request): Builder
    {
        $query = GastoObra::where('empresa_id', $this->getEmpresaId($request))
            ->where('activo', true)
            ->with(['proyecto:id,nombre', 'categoria:id,nombre', 'proveedor:id,nombre', 'usuario:id,nombre']);

        if ($request->filled('proyecto_id')) {
            $query->where('proyecto_id', $request->proyecto_id);
        }

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->categoria_id);
        }

        if ($request->filled('desde')) {
            $query->whereDate('fecha', '>=', $request->query('desde'));
        }

        if ($request->filled('hasta')) {
            $query->whereDate('fecha', '<=', $request->query('hasta'));
        }

        if ($request->filled('search')) {
            $termino = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($termino) {
                $q->where('descripcion', 'ILIKE', $termino)
                  ->orWhereHas('proyecto', function ($qProyecto) use ($termino) {
                      $qProyecto->where('nombre', 'ILIKE', $termino);
                  });
            });
        }

        return $query;
    }

    private function verificarLectura(Request $request): ?JsonResponse
    {
        if (! $request->user()->tieneRol(['administrador', 'gerente', 'supervisor', 'ingeniero'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para consultar gastos de obra.',
            ], 403);
        }

        return null;
    }

    /**
     * Guard para crear/editar gastos: administrador, gerente, supervisor e
     * ingeniero pueden entrar aquí. El filtro real para supervisor/ingeniero
     * (solo proyectos donde están asignados) lo aplica la validación de
     * proyecto_id en Store/UpdateGastoObraRequest, más el chequeo del
     * proyecto actual dentro de update().
     */
    private function verificarCreacionEdicion(Request $request): ?JsonResponse
    {
        if (! $request->user()->tieneRol(['administrador', 'gerente', 'supervisor', 'ingeniero'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para registrar gastos de obra.',
            ], 403);
        }

        return null;
    }

    private function verificarEscritura(Request $request): ?JsonResponse
    {
        if (! $request->user()->tieneRol(['administrador', 'gerente'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos para modificar gastos de obra.',
            ], 403);
        }

        return null;
    }

    private function formatearGasto(GastoObra $gasto): array
    {
        return [
            'id'              => $gasto->id,
            'proyecto_id'     => $gasto->proyecto_id,
            'proyecto'        => $gasto->proyecto ? [
                'id'     => $gasto->proyecto->id,
                'nombre' => $gasto->proyecto->nombre,
            ] : null,
            'categoria_id'    => $gasto->categoria_id,
            'categoria'       => $gasto->categoria ? [
                'id'     => $gasto->categoria->id,
                'nombre' => $gasto->categoria->nombre,
            ] : null,
            'proveedor_id'    => $gasto->proveedor_id,
            'proveedor'       => $gasto->proveedor ? [
                'id'     => $gasto->proveedor->id,
                'nombre' => $gasto->proveedor->nombre,
            ] : null,
            'monto'           => (float) $gasto->monto,
            'fecha'           => $gasto->fecha ? $gasto->fecha->format('Y-m-d') : null,
            'descripcion'     => $gasto->descripcion,
            'comprobante_url' => $gasto->comprobante_path,
            'usuario'         => $gasto->usuario ? [
                'id'     => $gasto->usuario->id,
                'nombre' => $gasto->usuario->nombre,
            ] : null,
            'created_at'      => $gasto->created_at,
            'updated_at'      => $gasto->updated_at,
        ];
    }
}
