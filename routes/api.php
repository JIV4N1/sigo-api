<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\IncidenciaController;
use App\Http\Controllers\Api\ProyectoController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\AsistenciaController;
use Illuminate\Support\Facades\Route;

// =============================================================================
// MÓDULO 1: Autenticación
// =============================================================================

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [AuthController::class, 'me'])->name('me');
    });
});

// =============================================================================
// Rutas protegidas — requieren token Sanctum válido
// =============================================================================

Route::middleware('auth:sanctum')->group(function (): void {

    // =========================================================================
    // MÓDULO 2: Proyectos
    // =========================================================================
    Route::get('/proyectos', [ProyectoController::class, 'index'])->name('proyectos.index');
    Route::get('/proyectos/{id}', [ProyectoController::class, 'show'])->name('proyectos.show');
    Route::get('/proyectos/{id}/actividad', [ProyectoController::class, 'actividad'])->name('proyectos.actividad');

    // =========================================================================
    // MÓDULO 3: Reportes Diarios
    // =========================================================================
    Route::get('/proyectos/{id}/reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::post('/reportes', [ReporteController::class, 'store'])->name('reportes.store');
    Route::get('/reportes/{id}', [ReporteController::class, 'show'])->name('reportes.show');
    Route::post('/reportes/{id}/fotos', [ReporteController::class, 'subirFotos'])->name('reportes.fotos');

    // =========================================================================
    // MÓDULO 4: Incidencias
    // =========================================================================

    /** GET /api/proyectos/{id}/incidencias — Listado paginado con filtros */
    Route::get('/proyectos/{id}/incidencias', [IncidenciaController::class, 'index'])
         ->name('incidencias.index');

    /** POST /api/incidencias — Crear nueva incidencia (con fotos opcionales) */
    Route::post('/incidencias', [IncidenciaController::class, 'store'])
         ->name('incidencias.store');

    /** GET /api/incidencias/{id} — Detalle completo con historial y comentarios */
    Route::get('/incidencias/{id}', [IncidenciaController::class, 'show'])
         ->name('incidencias.show');

    /** PUT /api/incidencias/{id}/estado — Cambiar estado con historial automático */
    Route::put('/incidencias/{id}/estado', [IncidenciaController::class, 'cambiarEstado'])
         ->name('incidencias.estado');

    /** POST /api/incidencias/{id}/fotos — Subir fotos de evidencia (máx. 6) */
    Route::post('/incidencias/{id}/fotos', [IncidenciaController::class, 'subirFotos'])
         ->name('incidencias.fotos');

    /** GET /api/incidencias/{id}/comentarios — Listar comentarios */
    Route::get('/incidencias/{id}/comentarios', [IncidenciaController::class, 'comentarios'])
         ->name('incidencias.comentarios.index');

    /** POST /api/incidencias/{id}/comentarios — Agregar comentario */
    Route::post('/incidencias/{id}/comentarios', [IncidenciaController::class, 'agregarComentario'])
         ->name('incidencias.comentarios.store');

    // =========================================================================
    // MÓDULO 6: Asistencia
    // =========================================================================
    Route::prefix('asistencia')->name('asistencia.')->group(function () {
        Route::get('/hoy', [AsistenciaController::class, 'hoy'])->name('hoy');
        Route::post('/entrada', [AsistenciaController::class, 'registrarEntrada'])->name('entrada');
        Route::post('/comida/inicio', [AsistenciaController::class, 'iniciarComida'])->name('comida.inicio');
        Route::post('/comida/fin', [AsistenciaController::class, 'finalizarComida'])->name('comida.fin');
        Route::post('/salida', [AsistenciaController::class, 'registrarSalida'])->name('salida');
        Route::get('/historial', [AsistenciaController::class, 'historial'])->name('historial');
    });
});
