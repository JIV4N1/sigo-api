<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Proyecto;
use App\Models\Role;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Http\JsonResponse;

/**
 * Estadísticas globales para el rol superadmin, agregadas entre todas las
 * empresas del sistema (a diferencia de DashboardEjecutivoController, que
 * siempre está acotado a una sola empresa vía AdminBypassTrait::getEmpresaId()).
 */
class EstadisticaController extends Controller
{
    /**
     * GET /api/superadmin/estadisticas
     */
    public function index(): JsonResponse
    {
        $empresasActivas = Empresa::where('activo', true)->count();
        $empresasInactivas = Empresa::where('activo', false)->count();

        $usuariosPorRol = Role::withCount('usuarios')
            ->get()
            ->map(fn (Role $rol) => [
                'rol'   => $rol->nombre,
                'total' => $rol->usuarios_count,
            ]);

        $data = [
            'total_empresas'      => Empresa::count(),
            'empresas_activas'    => $empresasActivas,
            'total_usuarios'      => Usuario::count(),
            'total_proyectos'     => Proyecto::count(),
            'total_ventas'        => Venta::count(),
            'usuarios_por_rol'    => $usuariosPorRol,
            'empresas_por_estado' => [
                ['estado' => 'Activa', 'total' => $empresasActivas],
                ['estado' => 'Inactiva', 'total' => $empresasInactivas],
            ],
        ];

        return response()->json([
            'status'  => 'success',
            'message' => 'Estadísticas globales obtenidas correctamente.',
            'data'    => $data,
        ], 200);
    }
}
