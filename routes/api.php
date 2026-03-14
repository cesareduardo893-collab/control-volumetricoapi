<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ContribuyenteController;
use App\Http\Controllers\InstalacionController;
use App\Http\Controllers\TanqueController;
use App\Http\Controllers\MedidorController;
use App\Http\Controllers\DispensarioController;
use App\Http\Controllers\MangueraController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\DictamenController;
use App\Http\Controllers\CertificadoVerificacionController;
use App\Http\Controllers\RegistroVolumetricoController;
use App\Http\Controllers\ExistenciaController;
use App\Http\Controllers\AlarmaController;
use App\Http\Controllers\CfdiController;
use App\Http\Controllers\PedimentoController;
use App\Http\Controllers\ReporteSatController;
use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\DashboardController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Rutas públicas
Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);

// Rutas protegidas con sanctum
Route::middleware('auth:sanctum')->group(function () {
    
    // ==================== AUTENTICACIÓN ====================
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('user', [AuthController::class, 'user']);
    
    // ==================== EXPORTAR E IMPORTAR ====================
    // Route::get('exportar/{modulo}', [ExportarImportarController::class, 'exportar']);
    // Route::post('importar/{modulo}', [ExportarImportarController::class, 'importar']);
    
    // ==================== DASHBOARD ====================
    Route::get('dashboard/resumen', [DashboardController::class, 'resumen']);
    Route::get('dashboard/tiempo-real', [DashboardController::class, 'tiempoReal']);
    Route::get('dashboard/grafica-movimientos', [DashboardController::class, 'graficaMovimientos']);
    Route::get('dashboard/grafica-productos', [DashboardController::class, 'graficaProductos']);

    // ==================== USUARIOS ====================
    Route::apiResource('users', UserController::class);
    Route::post('users/{id}/change-password', [UserController::class, 'cambiarPassword']);
    Route::post('users/{id}/block', [UserController::class, 'bloquear']);
    Route::post('users/{id}/unblock', [UserController::class, 'desbloquear']);
    Route::post('users/{id}/assign-role', [UserController::class, 'asignarRol']);
    Route::delete('users/{id}/remove-role', [UserController::class, 'quitarRol']);
    Route::get('users/{id}/permissions', [UserController::class, 'permisos']);
    Route::get('users/{id}/activity', [UserController::class, 'actividad']);
    
    // ==================== ROLES ====================
    Route::apiResource('roles', RoleController::class);
    Route::post('roles/{id}/assign-permissions', [RoleController::class, 'asignarPermisos']);
    Route::get('roles/{id}/permissions', [RoleController::class, 'permisos']);
    Route::get('matriz-permisos', [RoleController::class, 'matrizPermisos']);
    Route::post('roles/{id}/clone', [RoleController::class, 'clonar']);
    
    // ==================== PERMISOS ====================
    Route::apiResource('permissions', PermissionController::class);
    Route::get('permisos-por-modulo', [PermissionController::class, 'porModulo']);
    Route::post('permissions/sync', [PermissionController::class, 'sincronizar']);
    Route::post('permissions/verify', [PermissionController::class, 'verificar']);
    
    // ==================== CONTRIBUYENTES ====================
    Route::apiResource('contribuyentes', ContribuyenteController::class);
    Route::get('contribuyentes/{id}/instalaciones', [ContribuyenteController::class, 'instalaciones']);
    Route::get('contribuyentes/{id}/cumplimiento', [ContribuyenteController::class, 'cumplimiento']);
    Route::get('catalogo/contribuyentes', [ContribuyenteController::class, 'catalogo']);
    
    // ==================== INSTALACIONES ====================
    Route::apiResource('instalaciones', InstalacionController::class);
    Route::get('instalaciones/{id}/tanques', [InstalacionController::class, 'tanques']);
    Route::get('instalaciones/{id}/medidores', [InstalacionController::class, 'medidores']);
    Route::get('instalaciones/{id}/dispensarios', [InstalacionController::class, 'dispensarios']);
    Route::get('instalaciones/{id}/resumen-operativo', [InstalacionController::class, 'resumenOperativo']);
    
    // ==================== TANQUES ====================
    Route::apiResource('tanques', TanqueController::class);
    Route::post('tanques/{id}/calibrar', [TanqueController::class, 'registrarCalibracion']);
    Route::get('tanques/{id}/verificar-estado', [TanqueController::class, 'verificarEstado']);
    Route::post('tanques/{id}/cambiar-producto', [TanqueController::class, 'cambiarProducto']);
    Route::get('tanques/{id}/curva-calibracion', [TanqueController::class, 'curvaCalibracion']);
    Route::get('tanques/{id}/historial-calibraciones', [TanqueController::class, 'historialCalibraciones']);
    
    // ==================== MEDIDORES ====================
    Route::apiResource('medidores', MedidorController::class);
    Route::post('medidores/{id}/calibrar', [MedidorController::class, 'registrarCalibracion']);
    Route::get('medidores/{id}/probar-comunicacion', [MedidorController::class, 'probarComunicacion']);
    Route::get('medidores/{id}/verificar-estado', [MedidorController::class, 'verificarEstado']);
    Route::get('medidores/{id}/historial-calibraciones', [MedidorController::class, 'historialCalibraciones']);
    
    // ==================== DISPENSARIOS ====================
    Route::apiResource('dispensarios', DispensarioController::class);
    Route::get('dispensarios/{id}/mangueras', [DispensarioController::class, 'mangueras']);
    Route::get('dispensarios/{id}/verificar-estado', [DispensarioController::class, 'verificarEstado']);
    
    // ==================== MANGUERAS ====================
    Route::apiResource('mangueras', MangueraController::class);
    Route::post('mangueras/{id}/assign-meter', [MangueraController::class, 'asignarMedidor']);
    Route::delete('mangueras/{id}/remove-meter', [MangueraController::class, 'quitarMedidor']);
    
    // ==================== PRODUCTOS ====================
    Route::apiResource('productos', ProductoController::class);
    Route::get('productos/tipo/{tipo}', [ProductoController::class, 'porTipo']);
    Route::get('catalogo/productos', [ProductoController::class, 'catalogo']);
    Route::get('productos/clave-sat/{claveSat}', [ProductoController::class, 'buscarPorClaveSat']);
    
    // ==================== DICTÁMENES ====================
    Route::apiResource('dictamenes', DictamenController::class);
    Route::post('dictamenes/{id}/cancel', [DictamenController::class, 'cancelar']);
    Route::get('dictamenes/{id}/vigencia', [DictamenController::class, 'verificarVigencia']);
    Route::get('dictamenes/estadisticas', [DictamenController::class, 'estadisticas']);
    Route::get('dictamenes/producto/{productoId}', [DictamenController::class, 'porProducto']);
    
    // ==================== CERTIFICADOS DE VERIFICACIÓN ====================
    Route::apiResource('certificados-verificacion', CertificadoVerificacionController::class);
    Route::get('certificados-verificacion/{id}/vigencia', [CertificadoVerificacionController::class, 'verificarVigencia']);
    Route::get('certificados-verificacion/estadisticas', [CertificadoVerificacionController::class, 'estadisticas']);
    
    // ==================== REGISTROS VOLUMÉTRICOS ====================
    Route::apiResource('registros-volumetricos', RegistroVolumetricoController::class);
    Route::post('registros-volumetricos/{id}/validate', [RegistroVolumetricoController::class, 'validar']);
    Route::post('registros-volumetricos/{id}/cancel', [RegistroVolumetricoController::class, 'cancelar']);
    Route::post('registros-volumetricos/{id}/associate-dictamen', [RegistroVolumetricoController::class, 'asociarDictamen']);
    Route::get('registros-volumetricos/resumen-diario', [RegistroVolumetricoController::class, 'resumenDiario']);
    Route::get('registros-volumetricos/estadisticas-mensuales', [RegistroVolumetricoController::class, 'estadisticasMensuales']);
    
    // ==================== EXISTENCIAS ====================
    Route::apiResource('existencias', ExistenciaController::class);
    Route::post('existencias/{id}/validate', [ExistenciaController::class, 'validar']);
    Route::get('existencias/tanque/{tanqueId}/inventario-actual', [ExistenciaController::class, 'inventarioActual']);
    Route::get('existencias/tanque/{tanqueId}/historico', [ExistenciaController::class, 'historico']);
    Route::get('existencias/reporte-mermas', [ExistenciaController::class, 'reporteMermas']);
    Route::get('existencias/por-fecha', [ExistenciaController::class, 'porFecha']);
    
    // ==================== ALARMAS ====================
    Route::apiResource('alarmas', AlarmaController::class);
    Route::post('alarmas/{id}/atender', [AlarmaController::class, 'atender']);
    Route::put('alarmas/{id}/estado', [AlarmaController::class, 'actualizarEstado']);
    Route::get('alarmas/activas', [AlarmaController::class, 'activas']);
    Route::get('alarmas/estadisticas', [AlarmaController::class, 'estadisticas']);
    
    // ==================== CFDI ====================
    Route::apiResource('cfdi', CfdiController::class);
    Route::post('cfdi/{id}/cancel', [CfdiController::class, 'cancelar']);
    Route::get('cfdi/rfc/{rfc}', [CfdiController::class, 'porRfc']);
    Route::get('cfdi/resumen-fiscal', [CfdiController::class, 'resumenFiscal']);
    
    // ==================== PEDIMENTOS ====================
    Route::apiResource('pedimentos', PedimentoController::class);
    Route::post('pedimentos/{id}/cancel', [PedimentoController::class, 'cancelar']);
    Route::post('pedimentos/{id}/mark-as-used', [PedimentoController::class, 'marcarUtilizado']);
    Route::get('pedimentos/resumen-comercio-exterior', [PedimentoController::class, 'resumenComercioExterior']);
    
    // ==================== REPORTES SAT ====================
    Route::apiResource('reportes-sat', ReporteSatController::class);
    Route::post('reportes-sat/{id}/send', [ReporteSatController::class, 'enviar']);
    Route::post('reportes-sat/{id}/sign', [ReporteSatController::class, 'firmar']);
    Route::post('reportes-sat/{id}/cancel', [ReporteSatController::class, 'cancelar']);
    Route::get('reportes-sat/instalacion/{instalacionId}/historial', [ReporteSatController::class, 'historialEnvios']);
    
    // ==================== BITÁCORA ====================
    Route::apiResource('bitacora', BitacoraController::class)->except(['store', 'update', 'destroy']);
    Route::get('bitacora/resumen-actividad', [BitacoraController::class, 'resumenActividad']);
    Route::get('bitacora/usuario/{usuarioId}/actividad', [BitacoraController::class, 'actividadUsuario']);
    Route::get('bitacora/modulo/{modulo}/actividad', [BitacoraController::class, 'actividadModulo']);
    Route::get('bitacora/tabla/{tabla}/actividad/{registroId?}', [BitacoraController::class, 'actividadTabla']);
    Route::get('bitacora/exportar', [BitacoraController::class, 'exportar']);
});

// Ruta de prueba para verificar que la API funciona
Route::get('health', function() {
    return response()->json([
        'success' => true,
        'message' => 'API is running',
        'timestamp' => now()->toIso8601String()
    ]);
});