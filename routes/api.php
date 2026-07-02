<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\IncidenciaController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\ProyectoController;
use App\Http\Controllers\Api\ProveedorController;
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
    Route::prefix('proyectos')->name('proyectos.')->group(function () {
        Route::get('/', [ProyectoController::class, 'index'])->name('index');
        Route::post('/', [ProyectoController::class, 'store'])->name('store');
        Route::get('/{id}', [ProyectoController::class, 'show'])->name('show');
        Route::put('/{id}', [ProyectoController::class, 'update'])->name('update');
        Route::delete('/{id}', [ProyectoController::class, 'destroy'])->name('destroy');
        Route::get('/{id}/actividad', [ProyectoController::class, 'actividad'])->name('actividad');
        Route::get('/{id}/kpis', [ProyectoController::class, 'kpis'])->name('kpis');
        Route::get('/{id}/usuarios-disponibles', [ProyectoController::class, 'usuariosDisponibles'])->name('usuarios-disponibles');
        Route::post('/{id}/usuarios', [ProyectoController::class, 'asignarUsuario'])->name('asignar-usuario');
        Route::delete('/{id}/usuarios/{usuarioId}', [ProyectoController::class, 'removerUsuario'])->name('remover-usuario');
    });

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

    // =========================================================================
    // MÓDULO 5: Clientes
    // =========================================================================

    /** GET /api/clientes — Listado de clientes activos de la empresa */
    Route::get('/clientes', [ClienteController::class, 'index'])
         ->name('clientes.index');

    /** GET /api/clientes/search — Búsqueda de clientes por nombre o RFC */
    Route::get('/clientes/search', [ClienteController::class, 'search'])
         ->name('clientes.search');

    /** POST /api/clientes — Crear nuevo cliente */
    Route::post('/clientes', [ClienteController::class, 'store'])
         ->name('clientes.store');

    /** PUT /api/clientes/{id} — Actualizar cliente existente */
    Route::put('/clientes/{id}', [ClienteController::class, 'update'])
         ->name('clientes.update');

    /** PATCH /api/clientes/{id}/desactivar — Desactivar cliente (sin eliminar) */
    Route::patch('/clientes/{id}/desactivar', [ClienteController::class, 'desactivar'])
         ->name('clientes.desactivar');

    // =========================================================================
    // MÓDULO 7: Proveedores
    // =========================================================================

    /** GET /api/proveedores — Listado de proveedores activos de la empresa */
    Route::get('/proveedores', [ProveedorController::class, 'index'])
         ->name('proveedores.index');

    /** GET /api/proveedores/search — Búsqueda de proveedores */
    Route::get('/proveedores/search', [ProveedorController::class, 'search'])
         ->name('proveedores.search');

    /** POST /api/proveedores — Crear nuevo proveedor */
    Route::post('/proveedores', [ProveedorController::class, 'store'])
         ->name('proveedores.store');

    /** PUT /api/proveedores/{id} — Actualizar proveedor existente */
    Route::put('/proveedores/{id}', [ProveedorController::class, 'update'])
         ->name('proveedores.update');

    /** PATCH /api/proveedores/{id}/desactivar — Desactivar proveedor (sin eliminar) */
    Route::patch('/proveedores/{id}/desactivar', [ProveedorController::class, 'desactivar'])
         ->name('proveedores.desactivar');

    // =========================================================================
    // MÓDULO 8: Materiales
    // =========================================================================

    /** GET /api/materiales — Listado de materiales activos con proveedor */
    Route::get('/materiales', [MaterialController::class, 'index'])
         ->name('materiales.index');

    /** GET /api/materiales/search — Búsqueda por texto y/o proveedor */
    Route::get('/materiales/search', [MaterialController::class, 'search'])
         ->name('materiales.search');

    /** POST /api/materiales — Crear nuevo material */
    Route::post('/materiales', [MaterialController::class, 'store'])
         ->name('materiales.store');

    /** PUT /api/materiales/{id} — Actualizar material existente */
    Route::put('/materiales/{id}', [MaterialController::class, 'update'])
         ->name('materiales.update');

    /** PATCH /api/materiales/{id}/desactivar — Desactivar material (sin eliminar) */
    Route::patch('/materiales/{id}/desactivar', [MaterialController::class, 'desactivar'])
         ->name('materiales.desactivar');

    // =========================================================================
    // MÓDULO 9: Inventario
    // =========================================================================
    Route::prefix('inventario')->name('inventario.')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\InventarioController::class, 'index'])->name('index');
        Route::get('/bajo-stock', [App\Http\Controllers\Api\InventarioController::class, 'bajoStock'])->name('bajo-stock');
        Route::get('/historial', [App\Http\Controllers\Api\InventarioController::class, 'historial'])->name('historial');
        Route::post('/movimiento', [App\Http\Controllers\Api\InventarioController::class, 'registrarMovimiento'])->name('movimiento');
    });

    // =========================================================================
    // MÓDULO 10: Cotizaciones
    // =========================================================================
    Route::prefix('cotizaciones')->name('cotizaciones.')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\CotizacionController::class, 'index'])->name('index');
        Route::get('/search', [App\Http\Controllers\Api\CotizacionController::class, 'search'])->name('search');
        Route::get('/generar-folio', [App\Http\Controllers\Api\CotizacionController::class, 'generarFolio'])->name('generar-folio');
        Route::post('/', [App\Http\Controllers\Api\CotizacionController::class, 'store'])->name('store');
        Route::get('/{id}', [App\Http\Controllers\Api\CotizacionController::class, 'show'])->name('show');
        Route::post('/{id}/convertir-venta', [App\Http\Controllers\Api\CotizacionController::class, 'convertirAVenta'])->name('convertir-venta');
        Route::get('/{id}/pdf', [App\Http\Controllers\Api\CotizacionController::class, 'datosPdf'])->name('pdf');
    });
});

