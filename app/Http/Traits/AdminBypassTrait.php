<?php

namespace App\Http\Traits;

use App\Models\ProyectoUsuario;
use Illuminate\Http\Request;

trait AdminBypassTrait
{
    /**
     * Verifica si el usuario autenticado es administrador o gerente.
     * Estos roles tienen acceso total a todos los proyectos de su empresa.
     */
    protected function isAdminOrGerente(Request $request): bool
    {
        $rol = $request->user()?->rol?->nombre;
        return in_array($rol, ['administrador', 'gerente'], true);
    }

    /**
     * Verifica si el usuario tiene acceso a un proyecto específico.
     * Admin y gerente siempre tienen acceso (bypass automático).
     * Otros roles solo si están asignados en proyecto_usuario.
     */
    protected function tieneAccesoAProyecto(Request $request, int $proyectoId): bool
    {
        if ($this->isAdminOrGerente($request)) {
            return true;
        }

        return ProyectoUsuario::where('proyecto_id', $proyectoId)
            ->where('usuario_id', $request->user()->id)
            ->exists();
    }

    /**
     * Retorna la empresa "activa" del usuario autenticado, para usarse en todo
     * filtro/scoping multi-tenant en lugar de un empresa_id fijo.
     *
     * Un administrador multi-empresa puede tener seleccionada una empresa_activa_id
     * (ver AuthController::cambiarEmpresa). El resto de los roles usan siempre
     * su empresa_id fija.
     */
    protected function getEmpresaId(Request $request): ?int
    {
        $user = $request->user();

        if ($user->empresa_activa_id) {
            return $user->empresa_activa_id;
        }

        return $user->empresa_id;
    }
}
