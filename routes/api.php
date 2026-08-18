<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\EvidenciaController;
use App\Http\Controllers\Api\IncidenciaController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\ProyectoController;
use App\Http\Controllers\Api\ProveedorController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\AsistenciaController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\HorarioController;
use App\Http\Controllers\Api\DiaNoLaboralController;
use App\Http\Controllers\Api\ReporteHorasController;
use App\Http\Controllers\Api\ControlHorarioController;
use App\Http\Controllers\Api\EmpresaController;
use App\Http\Controllers\Api\VentaController;
use App\Http\Controllers\Api\ReporteVentasController;
use App\Http\Controllers\Api\PedidoController;
use App\Http\Controllers\Api\DashboardEjecutivoController;
use App\Http\Controllers\Api\GastoObraController;
use App\Http\Controllers\Api\CategoriaGastoController;
use App\Http\Controllers\Api\DepartamentoController;
use App\Http\Controllers\Api\NotificacionController;
use App\Http\Controllers\Api\MaquinariaController;
use App\Http\Controllers\Api\RegistroController;
use App\Http\Controllers\Api\Superadmin\EmpresaController as SuperadminEmpresaController;
use App\Http\Controllers\Api\Superadmin\EstadisticaController as SuperadminEstadisticaController;
use App\Http\Controllers\Api\Superadmin\SolicitudesController as SuperadminSolicitudesController;
use App\Http\Controllers\Api\Superadmin\UsuarioController as SuperadminUsuarioController;
use Illuminate\Support\Facades\Route;

// =============================================================================
// MÓDULO 1: Autenticación
// =============================================================================

// =============================================================================
// MÓDULO: Registro público (auto-registro pendiente de aprobación por superadmin)
// =============================================================================

Route::post('/registro', [RegistroController::class, 'registrar'])->name('registro');
Route::get('/empresas-publicas', [EmpresaController::class, 'publicas'])->name('empresas-publicas');

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::put('/cambiar-empresa', [AuthController::class, 'cambiarEmpresa'])->name('cambiar-empresa');
    });
});

// =============================================================================
// Rutas protegidas — requieren token Sanctum válido
// =============================================================================

Route::middleware('auth:sanctum')->group(function (): void {

    // =========================================================================
    // MÓDULO: Empresas
    // =========================================================================
    Route::post('/empresas', [EmpresaController::class, 'store'])->name('empresas.store');

    // Configuración de la empresa activa del usuario (respaldada por la tabla `empresas`)
    Route::prefix('empresa')->name('empresa.')->group(function () {
        Route::get('/configuracion', [EmpresaController::class, 'configuracion'])->name('configuracion.show');
        Route::put('/configuracion', [EmpresaController::class, 'actualizarConfiguracion'])->name('configuracion.update');
        Route::post('/logo', [EmpresaController::class, 'subirLogo'])->name('logo');
    });

    // =========================================================================
    // MÓDULO: Usuarios
    // =========================================================================
    Route::prefix('usuarios')->name('usuarios.')->group(function () {
        Route::get('/', [UsuarioController::class, 'index'])->name('index');
        Route::post('/', [UsuarioController::class, 'store'])->name('store');
        Route::get('/{id}', [UsuarioController::class, 'show'])->name('show');
        Route::put('/{id}', [UsuarioController::class, 'update'])->name('update');
        Route::delete('/{id}', [UsuarioController::class, 'destroy'])->name('destroy');
    });

    // =========================================================================
    // MÓDULO: Departamentos
    // =========================================================================
    Route::prefix('departamentos')->name('departamentos.')->group(function () {
        Route::get('/', [DepartamentoController::class, 'index'])->name('index');
        Route::post('/', [DepartamentoController::class, 'store'])->name('store');
        Route::put('/{id}', [DepartamentoController::class, 'update'])->name('update');
        Route::delete('/{id}', [DepartamentoController::class, 'destroy'])->name('destroy');
    });

    // =========================================================================
    // MÓDULO: Notificaciones
    // =========================================================================
    Route::prefix('notificaciones')->name('notificaciones.')->group(function () {
        Route::get('/', [NotificacionController::class, 'index'])->name('index');
        Route::get('/no-leidas', [NotificacionController::class, 'noLeidas'])->name('no-leidas');
        Route::patch('/{id}/leer', [NotificacionController::class, 'marcarLeida'])->name('leer');
        Route::patch('/leer-todas', [NotificacionController::class, 'marcarTodasLeidas'])->name('leer-todas');
    });

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
        Route::get('/{id}/usuarios', [ProyectoController::class, 'usuarios'])->name('usuarios');
        Route::get('/{id}/usuarios-disponibles', [ProyectoController::class, 'usuariosDisponibles'])->name('usuarios-disponibles');
        Route::post('/{id}/usuarios', [ProyectoController::class, 'asignarUsuario'])->name('asignar-usuario');
        Route::post('/{id}/asignar-masivo', [ProyectoController::class, 'asignarMasivo'])->name('asignar-masivo');
        Route::delete('/{id}/usuarios/{usuarioId}', [ProyectoController::class, 'removerUsuario'])->name('remover-usuario');
    });

    // =========================================================================
    // MÓDULO: Reportes de Ventas (analítica) — declarado ANTES de "Reportes
    // Diarios" a propósito: sus rutas son segmentos literales bajo /reportes/*
    // y deben resolverse antes que el wildcard GET /reportes/{id} de abajo,
    // o Laravel lo capturaría como si "ventas-por-dia" fuera un {id}.
    // =========================================================================
    Route::prefix('reportes')->name('reportes-ventas.')->group(function () {
        Route::get('/ventas-por-dia', [ReporteVentasController::class, 'ventasPorDia'])->name('ventas-por-dia');
        Route::get('/ventas-por-mes', [ReporteVentasController::class, 'ventasPorMes'])->name('ventas-por-mes');
        Route::get('/materiales-mas-vendidos', [ReporteVentasController::class, 'materialesMasVendidos'])->name('materiales-mas-vendidos');
        Route::get('/clientes-frecuentes', [ReporteVentasController::class, 'clientesFrecuentes'])->name('clientes-frecuentes');
        Route::get('/inventario-actual', [ReporteVentasController::class, 'inventarioActual'])->name('inventario-actual');
        Route::get('/materiales-bajo-stock', [ReporteVentasController::class, 'materialesBajoStock'])->name('materiales-bajo-stock');
    });

    // =========================================================================
    // MÓDULO 3: Reportes Diarios
    // =========================================================================
    Route::get('/proyectos/{id}/reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::get('/reportes', [ReporteController::class, 'listar'])->name('reportes.listar');
    Route::post('/reportes', [ReporteController::class, 'store'])->name('reportes.store');
    Route::get('/reportes/{id}', [ReporteController::class, 'show'])->name('reportes.show');

    // Alias esperado por el frontend web (app/(dashboard)/reportes-diarios/[id]/page.tsx
    // llama a /api/reportes-diarios/{id}, que no existía; apunta al mismo show()).
    Route::get('/reportes-diarios/{id}', [ReporteController::class, 'show'])->name('reportes-diarios.show');

    Route::post('/reportes/{id}/fotos', [ReporteController::class, 'subirFotos'])->name('reportes.fotos');
    Route::put('/reportes/{id}/validar', [ReporteController::class, 'validar'])->name('reportes.validar');

    // Alias esperado por la app móvil (mismo método validar(), mismo body
    // {accion:'aprobar'|'rechazar', notas?} — solo cambia la URL).
    Route::put('/reportes/{id}/estado', [ReporteController::class, 'validar'])->name('reportes.estado');

    // =========================================================================
    // MÓDULO 4: Incidencias
    // =========================================================================

    /** GET /api/incidencias — TODAS las incidencias de la empresa */
    Route::get('/incidencias', [IncidenciaController::class, 'index'])
         ->name('incidencias.index');

    /** GET /api/proyectos/{id}/incidencias — Listado paginado con filtros */
    Route::get('/proyectos/{id}/incidencias', [IncidenciaController::class, 'porProyecto'])
         ->name('proyectos.incidencias');

    /** GET /api/incidencias-asignadas — Listado de incidencias asignadas al usuario */
    Route::get('/incidencias-asignadas', [IncidenciaController::class, 'incidenciasAsignadas'])
         ->name('incidencias.asignadas');

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
    // MÓDULO: Evidencias — galería unificada de fotos de reportes e incidencias
    // =========================================================================

    /** GET /api/evidencias — Todas las fotos de los proyectos accesibles */
    Route::get('/evidencias', [EvidenciaController::class, 'index'])
         ->name('evidencias.index');

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
    // MÓDULO: Horarios laborales
    // =========================================================================
    Route::prefix('horarios')->name('horarios.')->group(function () {
        Route::get('/', [HorarioController::class, 'index'])->name('index');
        Route::post('/', [HorarioController::class, 'store'])->name('store');
        Route::put('/{id}', [HorarioController::class, 'update'])->name('update');
    });

    // =========================================================================
    // MÓDULO: Días no laborales
    // =========================================================================
    Route::prefix('dias-no-laborales')->name('dias-no-laborales.')->group(function () {
        Route::get('/', [DiaNoLaboralController::class, 'index'])->name('index');
        Route::post('/', [DiaNoLaboralController::class, 'store'])->name('store');
        Route::delete('/{id}', [DiaNoLaboralController::class, 'destroy'])->name('destroy');
    });

    // =========================================================================
    // MÓDULO: Reporte de horas
    // =========================================================================
    Route::get('/reporte-horas', [ReporteHorasController::class, 'index'])->name('reporte-horas.index');

    // =========================================================================
    // MÓDULO: Control horario
    // =========================================================================
    Route::get('/control-horario', [ControlHorarioController::class, 'index'])->name('control-horario.index');

    // =========================================================================
    // MÓDULO 5: Clientes
    // =========================================================================

    /** GET /api/clientes — Listado de clientes activos de la empresa */
    Route::get('/clientes', [ClienteController::class, 'index'])
         ->name('clientes.index');

    /** GET /api/clientes/search — Búsqueda de clientes por nombre o RFC */
    Route::get('/clientes/search', [ClienteController::class, 'search'])
         ->name('clientes.search');

    /** GET /api/clientes/{id} — Detalle de un cliente */
    Route::get('/clientes/{id}', [ClienteController::class, 'show'])
         ->name('clientes.show');

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

    /** GET /api/proveedores/{id} — Detalle de un proveedor */
    Route::get('/proveedores/{id}', [ProveedorController::class, 'show'])
         ->name('proveedores.show');

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

    /** GET /api/materiales/{id} — Detalle de un material */
    Route::get('/materiales/{id}', [MaterialController::class, 'show'])
         ->name('materiales.show');

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
    // MÓDULO: Maquinaria
    // =========================================================================

    /** GET /api/maquinaria — Listado de maquinaria activa */
    Route::get('/maquinaria', [MaquinariaController::class, 'index'])
         ->name('maquinaria.index');

    /** GET /api/maquinaria/search — Búsqueda por texto (nombre o código) */
    Route::get('/maquinaria/search', [MaquinariaController::class, 'search'])
         ->name('maquinaria.search');

    /** GET /api/maquinaria/{id} — Detalle de una maquinaria */
    Route::get('/maquinaria/{id}', [MaquinariaController::class, 'show'])
         ->name('maquinaria.show');

    /** POST /api/maquinaria — Crear nueva maquinaria (admin/gerente) */
    Route::post('/maquinaria', [MaquinariaController::class, 'store'])
         ->name('maquinaria.store');

    /** PUT /api/maquinaria/{id} — Actualizar maquinaria existente (admin/gerente) */
    Route::put('/maquinaria/{id}', [MaquinariaController::class, 'update'])
         ->name('maquinaria.update');

    /** PATCH /api/maquinaria/{id}/desactivar — Desactivar maquinaria (admin/gerente) */
    Route::patch('/maquinaria/{id}/desactivar', [MaquinariaController::class, 'desactivar'])
         ->name('maquinaria.desactivar');

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

    // =========================================================================
    // MÓDULO 11: Ventas
    // =========================================================================
    Route::prefix('ventas')->name('ventas.')->group(function () {
        Route::get('/', [VentaController::class, 'index'])->name('index');
        Route::get('/search', [VentaController::class, 'search'])->name('search');
        Route::get('/folio', [VentaController::class, 'generarFolio'])->name('folio');
        Route::post('/venta-directa', [VentaController::class, 'store'])->name('venta-directa');
        Route::get('/{id}', [VentaController::class, 'show'])->name('show');
    });

    // =========================================================================
    // MÓDULO 12: Pedidos de Venta
    // =========================================================================
    Route::prefix('pedidos')->name('pedidos.')->group(function () {
        Route::get('/', [PedidoController::class, 'index'])->name('index');
        Route::get('/search', [PedidoController::class, 'search'])->name('search');
        Route::post('/', [PedidoController::class, 'store'])->name('store');
        Route::get('/{id}', [PedidoController::class, 'show'])->name('show');
        Route::put('/{id}', [PedidoController::class, 'update'])->name('update');
        Route::patch('/{id}/estado', [PedidoController::class, 'cambiarEstado'])->name('estado');
        Route::delete('/{id}', [PedidoController::class, 'destroy'])->name('destroy');
    });

    // =========================================================================
    // MÓDULO 13: Dashboard Ejecutivo
    // =========================================================================
    Route::prefix('dashboard-ejecutivo')->name('dashboard-ejecutivo.')->group(function () {
        Route::get('/', [DashboardEjecutivoController::class, 'index'])->name('index');
        Route::get('/kpis', [DashboardEjecutivoController::class, 'kpis'])->name('kpis');
        Route::get('/tendencias', [DashboardEjecutivoController::class, 'tendencias'])->name('tendencias');
        Route::get('/distribuciones', [DashboardEjecutivoController::class, 'distribuciones'])->name('distribuciones');
        Route::get('/actividad-reciente', [DashboardEjecutivoController::class, 'actividadReciente'])->name('actividad-reciente');
    });

    // =========================================================================
    // MÓDULO 14: Gastos de Obra
    // =========================================================================
    Route::prefix('gastos-obra')->name('gastos-obra.')->group(function () {
        // Segmentos literales declarados ANTES de /{id} para que Laravel no
        // los capture como si fueran un {id} (mismo criterio que Ventas/Reportes).
        Route::get('/search', [GastoObraController::class, 'search'])->name('search');
        Route::get('/resumen', [GastoObraController::class, 'resumen'])->name('resumen');

        Route::get('/categorias', [CategoriaGastoController::class, 'index'])->name('categorias.index');
        Route::post('/categorias', [CategoriaGastoController::class, 'store'])->name('categorias.store');
        Route::put('/categorias/{id}', [CategoriaGastoController::class, 'update'])->name('categorias.update');
        Route::delete('/categorias/{id}', [CategoriaGastoController::class, 'destroy'])->name('categorias.destroy');

        Route::get('/', [GastoObraController::class, 'index'])->name('index');
        Route::post('/', [GastoObraController::class, 'store'])->name('store');
        Route::get('/{id}', [GastoObraController::class, 'show'])->name('show');
        Route::put('/{id}', [GastoObraController::class, 'update'])->name('update');
        Route::delete('/{id}', [GastoObraController::class, 'destroy'])->name('destroy');
    });

    // =========================================================================
    // MÓDULO 15: Superadmin — control total sobre todas las empresas del sistema
    // =========================================================================
    Route::prefix('superadmin')->name('superadmin.')->middleware('superadmin')->group(function () {
        Route::get('/estadisticas', [SuperadminEstadisticaController::class, 'index'])->name('estadisticas');

        Route::prefix('empresas')->name('empresas.')->group(function () {
            Route::get('/', [SuperadminEmpresaController::class, 'index'])->name('index');
            Route::get('/{id}', [SuperadminEmpresaController::class, 'show'])->name('show');
            Route::put('/{id}', [SuperadminEmpresaController::class, 'update'])->name('update');
            Route::patch('/{id}/estado', [SuperadminEmpresaController::class, 'cambiarEstado'])->name('estado');
            Route::get('/{id}/usuarios', [SuperadminEmpresaController::class, 'usuarios'])->name('usuarios');
            Route::post('/{id}/admin', [SuperadminEmpresaController::class, 'asignarAdmin'])->name('admin');
        });

        Route::prefix('usuarios')->name('usuarios.')->group(function () {
            Route::get('/', [SuperadminUsuarioController::class, 'index'])->name('index');
            Route::patch('/{id}/rol', [SuperadminUsuarioController::class, 'cambiarRol'])->name('rol');
            Route::patch('/{id}/estado', [SuperadminUsuarioController::class, 'cambiarEstado'])->name('estado');
        });

        Route::prefix('solicitudes')->name('solicitudes.')->group(function () {
            Route::get('/', [SuperadminSolicitudesController::class, 'index'])->name('index');
            Route::patch('/{id}/aprobar', [SuperadminSolicitudesController::class, 'aprobar'])->name('aprobar');
            Route::patch('/{id}/rechazar', [SuperadminSolicitudesController::class, 'rechazar'])->name('rechazar');
        });
    });
});

