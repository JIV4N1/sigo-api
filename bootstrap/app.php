<?php

use App\Http\Middleware\EnsureIsSuperadmin;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',       // Rutas de la API REST (prefijo /api)
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Stateful domains para Sanctum (necesario si se usan cookies SPA)
        // $middleware->statefulApi();

        $middleware->alias([
            'superadmin' => EnsureIsSuperadmin::class,
        ]);

        // Esta API no tiene ruta 'login' (no hay vistas web de sesión). Por
        // defecto Laravel redirige ahí a los guests cuando el request no
        // trae "Accept: application/json" (típico en apps de escritorio),
        // y como la ruta no existe, eso revienta con
        // "RouteNotFoundException: Route [login] not defined." antes
        // siquiera de llegar al manejo de AuthenticationException de abajo.
        // Al no redirigir nunca, dejamos que ese render() sí se ejecute.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Como SIGO es una API pura, cualquier request bajo /api debe recibir
        // siempre JSON al fallar la autenticación, sin importar los headers
        // que mande el cliente (ver nota en withMiddleware sobre por qué
        // esto dependía antes de "Accept: application/json").
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No autenticado. Inicia sesión para continuar.',
                ], 401);
            }
        });
    })->create();
