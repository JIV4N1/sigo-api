<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe el acceso a rutas que requieren el rol "superadmin",
 * con control total sobre todas las empresas del sistema.
 */
class EnsureIsSuperadmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->esSuperadmin()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tienes permisos de superadministrador.',
            ], 403);
        }

        return $next($request);
    }
}
