<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ContribuyenteController;
use App\Http\Controllers\InstalacionController;
use App\Http\Controllers\TanqueController;
use App\Http\Controllers\MedidorController;
use App\Http\Controllers\ExistenciaController;
use App\Http\Controllers\RegistroVolumetricoController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ReporteSatController;
use App\Http\Controllers\AlarmaController;
use App\Http\Controllers\CfdiController;
use App\Http\Controllers\PedimentoController;
use App\Http\Controllers\DictamenController;
use App\Http\Controllers\CertificadoVerificacionController;
use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\AutodiagnosticoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Rutas de autenticación
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth:sanctum');

// Rutas públicas
Route::get('/health', function() {
    return response()->json(['status' => 'OK', 'timestamp' => now()]);
});

// Rutas protegidas
Route::middleware(['auth:sanctum'])->group(function () {
    // Usuario autenticado
    Route::get('/user', [AuthController::class, 'user'])->name('user');
    
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/tiempo-real', [DashboardController::class, 'tiempoReal'])->name('dashboard.tiempo-real');
    Route::get('/dashboard/grafica-movimientos', [DashboardController::class, 'graficaMovimientos'])->name('dashboard.grafica');

    // Contribuyentes
    Route::apiResource('contribuyentes', ContribuyenteController::class);
    Route::post('contribuyentes/{id}/restore', [ContribuyenteController::class, 'restore'])->name('contribuyentes.restore');
    Route::get('contribuyentes/{contribuyente}/instalaciones', [ContribuyenteController::class, 'instalaciones'])->name('contribuyentes.instalaciones');
    Route::get('contribuyentes/{contribuyente}/cumplimiento', [ContribuyenteController::class, 'cumplimiento'])->name('contribuyentes.cumplimiento');

    // Instalaciones
    Route::apiResource('instalaciones', InstalacionController::class);
    Route::get('instalaciones/{instalacion}/tanques', [InstalacionController::class, 'tanques'])->name('instalaciones.tanques');
    Route::get('instalaciones/{instalacion}/medidores', [InstalacionController::class, 'medidores'])->name('instalaciones.medidores');
    Route::get('instalaciones/{instalacion}/dispensarios', [InstalacionController::class, 'dispensarios'])->name('instalaciones.dispensarios');
    Route::get('instalaciones/{instalacion}/verificar-comunicacion', [InstalacionController::class, 'verificarComunicacion'])->name('instalaciones.verificar-comunicacion');
    Route::get('instalaciones/{instalacion}/resumen-operativo', [InstalacionController::class, 'resumenOperativo'])->name('instalaciones.resumen-operativo');
    Route::put('instalaciones/{instalacion}/configuracion-red', [InstalacionController::class, 'actualizarConfiguracionRed'])->name('instalaciones.configuracion-red');
    Route::put('instalaciones/{instalacion}/umbrales-alarma', [InstalacionController::class, 'actualizarUmbralesAlarma'])->name('instalaciones.umbrales-alarma');
    Route::get('instalaciones/{instalacion}/reporte-cumplimiento', [InstalacionController::class, 'reporteCumplimientoNormativo'])->name('instalaciones.reporte-cumplimiento');

    // Tanques
    Route::apiResource('tanques', TanqueController::class);
    Route::get('tanques/{tanque}/existencias', [TanqueController::class, 'existencias'])->name('tanques.existencias');
    Route::get('tanques/{tanque}/ultima-existencia', [TanqueController::class, 'ultimaExistencia'])->name('tanques.ultima-existencia');

    // Medidores
    Route::apiResource('medidores', MedidorController::class);
    Route::post('medidores/{medidor}/calibrar', [MedidorController::class, 'calibrar'])->name('medidores.calibrar');
    Route::get('medidores/tanques/{instalacionId}', [MedidorController::class, 'getTanquesByInstalacion'])->name('medidores.tanques-por-instalacion');

    // Existencias
    Route::apiResource('existencias', ExistenciaController::class);
    Route::post('existencias/{existencia}/validar', [ExistenciaController::class, 'validar'])->name('existencias.validar');
    Route::get('existencias/reporte/inventario-diario', [ExistenciaController::class, 'inventarioDiario'])->name('existencias.inventario-diario');
    Route::post('existencias/{existencia}/asociar-cfdi', [ExistenciaController::class, 'asociarCfdi'])->name('existencias.asociar-cfdi');
    Route::post('existencias/{existencia}/asociar-pedimento', [ExistenciaController::class, 'asociarPedimento'])->name('existencias.asociar-pedimento');

    // Registros Volumétricos
    Route::apiResource('registros-volumetricos', RegistroVolumetricoController::class);
    Route::post('registros-volumetricos/{registro_volumetrico}/validar', [RegistroVolumetricoController::class, 'validar'])->name('registros-volumetricos.validar');
    Route::post('registros-volumetricos/{registro_volumetrico}/asociar-cfdi', [RegistroVolumetricoController::class, 'asociarCfdi'])->name('registros-volumetricos.asociar-cfdi');
    Route::post('registros-volumetricos/{registro_volumetrico}/asociar-pedimento', [RegistroVolumetricoController::class, 'asociarPedimento'])->name('registros-volumetricos.asociar-pedimento');
    Route::post('registros-volumetricos/{registro_volumetrico}/marcar-con-alarma', [RegistroVolumetricoController::class, 'marcarConAlarma'])->name('registros-volumetricos.marcar-alarma');
    Route::post('registros-volumetricos/{registro_volumetrico}/cancelar', [RegistroVolumetricoController::class, 'cancelar'])->name('registros-volumetricos.cancelar');
    Route::get('registros-volumetricos/{registro_volumetrico}/resumen-diario', [RegistroVolumetricoController::class, 'resumenDiario'])->name('registros-volumetricos.resumen-diario');
    Route::get('registros-volumetricos/{registro_volumetrico}/estadisticas-mensuales', [RegistroVolumetricoController::class, 'estadisticasMensuales'])->name('registros-volumetricos.estadisticas-mensuales');

    // Productos
    Route::apiResource('productos', ProductoController::class);
    Route::get('productos/tipo/{tipo}', [ProductoController::class, 'byTipo'])->name('productos.by-tipo');

    // Dispensarios
    Route::apiResource('dispensarios', DispensarioController::class);

    // Mangueras
    Route::apiResource('mangueras', MangueraController::class);

    // Certificados de Verificación
    Route::apiResource('certificados-verificacion', CertificadoVerificacionController::class);

    // Reportes SAT
    Route::apiResource('reportes-sat', ReporteSatController::class);
    Route::post('reportes-sat/{reporte_sat}/firmar', [ReporteSatController::class, 'firmar'])->name('reportes-sat.firmar');
    Route::post('reportes-sat/{reporte_sat}/enviar', [ReporteSatController::class, 'enviar'])->name('reportes-sat.enviar');
    Route::get('reportes-sat/{reporte_sat}/descargar-xml', [ReporteSatController::class, 'descargarXml'])->name('reportes-sat.xml');
    Route::get('reportes-sat/{reporte_sat}/descargar-pdf', [ReporteSatController::class, 'descargarPdf'])->name('reportes-sat.pdf');
    Route::get('reportes-sat/{reporte_sat}/acuse', [ReporteSatController::class, 'acuse'])->name('reportes-sat.acuse');

    // Alarmas
    Route::apiResource('alarmas', AlarmaController::class)->except(['store', 'destroy']);
    Route::post('alarmas/{alarma}/atender', [AlarmaController::class, 'update'])->name('alarmas.atender');
    Route::get('alarmas/estadisticas/dashboard', [AlarmaController::class, 'dashboard'])->name('alarmas.dashboard');
    Route::get('alarmas/estadisticas/reporte', [AlarmaController::class, 'estadisticas'])->name('alarmas.estadisticas');

    // CFDI
    Route::apiResource('cfdi', CfdiController::class);
    Route::post('cfdi/{cfdi}/cancelar', [CfdiController::class, 'cancelar'])->name('cfdi.cancelar');

    // Pedimentos
    Route::apiResource('pedimentos', PedimentoController::class);
    Route::post('pedimentos/{pedimento}/asociar-registro', [PedimentoController::class, 'asociarRegistro'])->name('pedimentos.asociar-registro');

    // Dictámenes
    Route::apiResource('dictamenes', DictamenController::class);

    // Certificados de Verificación
    Route::apiResource('certificados', CertificadoVerificacionController::class);

    // Bitácora
    Route::apiResource('bitacora', BitacoraController::class)->only(['index', 'show']);

    // Roles y Permisos
    Route::apiResource('roles', RoleController::class);
    Route::apiResource('permisos', PermissionController::class);

    // Usuarios
    Route::apiResource('usuarios', UserController::class);

    // Configuración
    Route::prefix('configuracion')->name('configuracion.')->group(function () {
        Route::get('/', [ConfiguracionController::class, 'index'])->name('index');
        Route::put('/', [ConfiguracionController::class, 'update'])->name('update');
        Route::get('/parametros-medicion', [ConfiguracionController::class, 'parametrosMedicion'])->name('parametros-medicion');
        Route::put('/parametros-medicion', [ConfiguracionController::class, 'updateParametrosMedicion'])->name('update-parametros');
        Route::get('/umbrales-alarma', [ConfiguracionController::class, 'umbralesAlarma'])->name('umbrales-alarma');
        Route::put('/umbrales-alarma', [ConfiguracionController::class, 'updateUmbralesAlarma'])->name('update-umbrales');
        Route::get('/sgm', [ConfiguracionController::class, 'sgmConfig'])->name('sgm');
        Route::put('/sgm', [ConfiguracionController::class, 'updateSgmConfig'])->name('update-sgm');
    });

    // Autodiagnóstico
    Route::prefix('autodiagnostico')->name('autodiagnostico.')->group(function () {
        Route::get('/', [AutodiagnosticoController::class, 'dashboard'])->name('dashboard');
        Route::post('/diagnosticar', [AutodiagnosticoController::class, 'diagnosticar'])->name('diagnosticar');
        Route::get('/resultados', [AutodiagnosticoController::class, 'resultados'])->name('resultados');
        Route::get('/json/ultimo', [AutodiagnosticoController::class, 'ultimoJson'])->name('ultimo-json');
        Route::post('/json/diagnosticar', [AutodiagnosticoController::class, 'diagnosticarJson'])->name('diagnosticar-json');
    });
});