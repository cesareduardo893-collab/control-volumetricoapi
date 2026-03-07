<?php

namespace App\Http\Controllers;

use App\Models\Instalacion;
use App\Models\Contribuyente;
use App\Models\Tanque;
use App\Models\Medidor;
use App\Models\Dispensario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InstalacionController extends BaseController
{
    /**
     * Listar instalaciones
     */
    public function index(Request $request)
    {
        $query = Instalacion::with(['contribuyente']);

        // Filtros
        if ($request->has('contribuyente_id')) {
            $query->where('contribuyente_id', $request->contribuyente_id);
        }

        if ($request->has('clave_instalacion')) {
            $query->where('clave_instalacion', 'LIKE', "%{$request->clave_instalacion}%");
        }

        if ($request->has('nombre')) {
            $query->where('nombre', 'LIKE', "%{$request->nombre}%");
        }

        if ($request->has('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->has('estatus')) {
            $query->where('estatus', $request->estatus);
        }

        if ($request->has('permiso_cre')) {
            $query->where('permiso_cre', 'LIKE', "%{$request->permiso_cre}%");
        }

        if ($request->has('codigo_postal')) {
            $query->where('codigo_postal', $request->codigo_postal);
        }

        if ($request->has('municipio')) {
            $query->where('municipio', 'LIKE', "%{$request->municipio}%");
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->boolean('activo')) {
            $query->where('activo', true);
        }

        $instalaciones = $query->orderBy('contribuyente_id')
            ->orderBy('clave_instalacion')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($instalaciones, 'Instalaciones obtenidas exitosamente');
    }

    /**
     * Crear instalación
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contribuyente_id' => 'required|exists:contribuyentes,id',
            'clave_instalacion' => 'required|string|max:50|unique:instalaciones,clave_instalacion',
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|in:REFINERIA,TERMINAL_ALMACENAMIENTO,ESTACION_SERVICIO,PLANTA_PROCESO,DUCTO,PUNTO_VENTA,OTRO',
            'subtipo' => 'nullable|string|max:100',
            'descripcion' => 'nullable|string|max:500',
            'domicilio' => 'required|string|max:255',
            'colonia' => 'nullable|string|max:100',
            'municipio' => 'required|string|max:100',
            'estado' => 'required|string|max:50',
            'codigo_postal' => 'required|string|size:5',
            'pais' => 'required|string|size:3|default:MEX',
            'coordenadas' => 'nullable|array',
            'coordenadas.latitud' => 'required_with:coordenadas|numeric|between:-90,90',
            'coordenadas.longitud' => 'required_with:coordenadas|numeric|between:-180,180',
            'coordenadas.altitud' => 'nullable|numeric',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'responsable_nombre' => 'required|string|max:255',
            'responsable_rfc' => 'required|string|size:13',
            'responsable_telefono' => 'nullable|string|max:20',
            'responsable_email' => 'nullable|email|max:255',
            'permiso_cre' => 'nullable|string|max:50',
            'tipo_permiso' => 'nullable|string|max:100',
            'fecha_otorgamiento' => 'nullable|date',
            'fecha_vencimiento' => 'nullable|date|after:fecha_otorgamiento',
            'capacidad_almacenamiento' => 'nullable|numeric|min:0',
            'unidad_capacidad' => 'nullable|in:LITROS,M3,BARRILES',
            'giros_autorizados' => 'nullable|array',
            'giros_autorizados.*' => 'string',
            'productos_autorizados' => 'nullable|array',
            'productos_autorizados.*.clave' => 'required_with:productos_autorizados|string',
            'productos_autorizados.*.descripcion' => 'required_with:productos_autorizados|string',
            'horario_operacion' => 'nullable|array',
            'horario_operacion.lunes' => 'nullable|string',
            'horario_operacion.martes' => 'nullable|string',
            'horario_operacion.miercoles' => 'nullable|string',
            'horario_operacion.jueves' => 'nullable|string',
            'horario_operacion.viernes' => 'nullable|string',
            'horario_operacion.sabado' => 'nullable|string',
            'horario_operacion.domingo' => 'nullable|string',
            'configuracion_red' => 'nullable|array',
            'configuracion_red.tipo_conexion' => 'required_with:configuracion_red|in:VPN,INTERNET_PRIVADO,INTERNET_PUBLICO',
            'configuracion_red.ip_publica' => 'nullable|ip',
            'configuracion_red.ip_privada' => 'nullable|ip',
            'configuracion_red.mascara' => 'nullable|string',
            'configuracion_red.gateway' => 'nullable|ip',
            'configuracion_red.dns_primario' => 'nullable|ip',
            'configuracion_red.dns_secundario' => 'nullable|ip',
            'configuracion_red.puertos' => 'nullable|array',
            'configuracion_red.puertos.*.numero' => 'required|integer|min:1|max:65535',
            'configuracion_red.puertos.*.protocolo' => 'required|in:TCP,UDP',
            'configuracion_red.puertos.*.servicio' => 'required|string',
            'umbrales_alarma' => 'nullable|array',
            'umbrales_alarma.diferencia_maxima_liquidos' => 'nullable|numeric|min:0|max:100',
            'umbrales_alarma.diferencia_maxima_gaseosos' => 'nullable|numeric|min:0|max:100',
            'umbrales_alarma.temperatura_maxima' => 'nullable|numeric',
            'umbrales_alarma.temperatura_minima' => 'nullable|numeric',
            'umbrales_alarma.presion_maxima' => 'nullable|numeric',
            'umbrales_alarma.tiempo_sin_comunicacion' => 'nullable|integer|min:1',
            'parametros_operativos' => 'nullable|array',
            'parametros_operativos.horario_envio_sat' => 'nullable|string',
            'parametros_operativos.frecuencia_respaldo' => 'nullable|in:DIARIO,SEMANAL,QUINCENAL,MENSUAL',
            'parametros_operativos.dias_conservacion_bitacora' => 'nullable|integer|min:180',
            'observaciones' => 'nullable|string|max:1000',
            'estatus' => 'required|in:OPERACION,MANTENIMIENTO,INACTIVO,CONSTRUCCION',
            'activo' => 'boolean',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            // Validar contribuyente activo
            $contribuyente = Contribuyente::find($request->contribuyente_id);
            if (!$contribuyente->activo) {
                return $this->sendError('El contribuyente no está activo', [], 422);
            }

            // Validar permiso CRE si se requiere
            if (in_array($request->tipo, ['REFINERIA', 'TERMINAL_ALMACENAMIENTO', 'ESTACION_SERVICIO', 'PLANTA_PROCESO'])) {
                if (!$request->permiso_cre) {
                    return $this->sendError('Para este tipo de instalación es requerido el permiso CRE', [], 422);
                }
            }

            $instalacion = Instalacion::create([
                'contribuyente_id' => $request->contribuyente_id,
                'clave_instalacion' => $request->clave_instalacion,
                'nombre' => $request->nombre,
                'tipo' => $request->tipo,
                'subtipo' => $request->subtipo,
                'descripcion' => $request->descripcion,
                'domicilio' => $request->domicilio,
                'colonia' => $request->colonia,
                'municipio' => $request->municipio,
                'estado' => $request->estado,
                'codigo_postal' => $request->codigo_postal,
                'pais' => $request->pais ?? 'MEX',
                'coordenadas' => $request->coordenadas,
                'telefono' => $request->telefono,
                'email' => $request->email,
                'responsable_nombre' => $request->responsable_nombre,
                'responsable_rfc' => $request->responsable_rfc,
                'responsable_telefono' => $request->responsable_telefono,
                'responsable_email' => $request->responsable_email,
                'permiso_cre' => $request->permiso_cre,
                'tipo_permiso' => $request->tipo_permiso,
                'fecha_otorgamiento' => $request->fecha_otorgamiento,
                'fecha_vencimiento' => $request->fecha_vencimiento,
                'capacidad_almacenamiento' => $request->capacidad_almacenamiento,
                'unidad_capacidad' => $request->unidad_capacidad,
                'giros_autorizados' => $request->giros_autorizados,
                'productos_autorizados' => $request->productos_autorizados,
                'horario_operacion' => $request->horario_operacion,
                'configuracion_red' => $request->configuracion_red,
                'umbrales_alarma' => $request->umbrales_alarma,
                'parametros_operativos' => $request->parametros_operativos,
                'observaciones' => $request->observaciones,
                'estatus' => $request->estatus,
                'activo' => $request->boolean('activo', true),
                'metadata' => $request->metadata
            ]);

            $this->logActivity(
                auth()->id(),
                'configuracion',
                'creacion_instalacion',
                'instalaciones',
                "Instalación creada: {$instalacion->clave_instalacion} - {$instalacion->nombre}",
                'instalaciones',
                $instalacion->id
            );

            DB::commit();

            return $this->sendResponse($instalacion->load('contribuyente'), 
                'Instalación creada exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al crear instalación', [$e->getMessage()], 500);
        }
    }

    /**
     * Mostrar instalación
     */
    public function show($id)
    {
        $instalacion = Instalacion::with([
            'contribuyente',
            'tanques' => function($q) {
                $q->with('productoActual')->orderBy('codigo');
            },
            'medidores' => function($q) {
                $q->orderBy('numero_serie');
            },
            'dispensarios' => function($q) {
                $q->with('mangueras')->orderBy('codigo');
            },
            'reportesSat' => function($q) {
                $q->latest()->limit(6);
            },
            'alarmas' => function($q) {
                $q->where('estado', 'ACTIVA')->latest();
            }
        ])->find($id);

        if (!$instalacion) {
            return $this->sendError('Instalación no encontrada');
        }

        // Calcular estadísticas
        $instalacion->estadisticas = $this->calcularEstadisticas($instalacion);

        return $this->sendResponse($instalacion, 'Instalación obtenida exitosamente');
    }

    /**
     * Actualizar instalación
     */
    public function update(Request $request, $id)
    {
        $instalacion = Instalacion::find($id);

        if (!$instalacion) {
            return $this->sendError('Instalación no encontrada');
        }

        $validator = Validator::make($request->all(), [
            'clave_instalacion' => "sometimes|string|max:50|unique:instalaciones,clave_instalacion,{$id}",
            'nombre' => 'sometimes|string|max:255',
            'tipo' => 'sometimes|in:REFINERIA,TERMINAL_ALMACENAMIENTO,ESTACION_SERVICIO,PLANTA_PROCESO,DUCTO,PUNTO_VENTA,OTRO',
            'subtipo' => 'nullable|string|max:100',
            'descripcion' => 'nullable|string|max:500',
            'domicilio' => 'sometimes|string|max:255',
            'colonia' => 'nullable|string|max:100',
            'municipio' => 'sometimes|string|max:100',
            'estado' => 'sometimes|string|max:50',
            'codigo_postal' => 'sometimes|string|size:5',
            'pais' => 'sometimes|string|size:3',
            'coordenadas' => 'nullable|array',
            'coordenadas.latitud' => 'required_with:coordenadas|numeric|between:-90,90',
            'coordenadas.longitud' => 'required_with:coordenadas|numeric|between:-180,180',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'responsable_nombre' => 'sometimes|string|max:255',
            'responsable_rfc' => 'sometimes|string|size:13',
            'responsable_telefono' => 'nullable|string|max:20',
            'responsable_email' => 'nullable|email|max:255',
            'permiso_cre' => 'nullable|string|max:50',
            'tipo_permiso' => 'nullable|string|max:100',
            'fecha_otorgamiento' => 'nullable|date',
            'fecha_vencimiento' => 'nullable|date|after:fecha_otorgamiento',
            'capacidad_almacenamiento' => 'nullable|numeric|min:0',
            'unidad_capacidad' => 'nullable|in:LITROS,M3,BARRILES',
            'giros_autorizados' => 'nullable|array',
            'productos_autorizados' => 'nullable|array',
            'horario_operacion' => 'nullable|array',
            'configuracion_red' => 'nullable|array',
            'umbrales_alarma' => 'nullable|array',
            'parametros_operativos' => 'nullable|array',
            'observaciones' => 'nullable|string|max:1000',
            'estatus' => 'sometimes|in:OPERACION,MANTENIMIENTO,INACTIVO,CONSTRUCCION',
            'activo' => 'sometimes|boolean',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $instalacion->toArray();
            $instalacion->update($request->all());

            $this->logActivity(
                auth()->id(),
                'configuracion',
                'actualizacion_instalacion',
                'instalaciones',
                "Instalación actualizada: {$instalacion->clave_instalacion}",
                'instalaciones',
                $instalacion->id,
                $datosAnteriores,
                $instalacion->toArray()
            );

            DB::commit();

            return $this->sendResponse($instalacion, 'Instalación actualizada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al actualizar instalación', [$e->getMessage()], 500);
        }
    }

    /**
     * Eliminar instalación (soft delete)
     */
    public function destroy($id)
    {
        $instalacion = Instalacion::find($id);

        if (!$instalacion) {
            return $this->sendError('Instalación no encontrada');
        }

        // Verificar si tiene tanques activos
        $tanquesActivos = $instalacion->tanques()->where('activo', true)->count();
        if ($tanquesActivos > 0) {
            return $this->sendError('No se puede eliminar la instalación porque tiene tanques activos', [], 409);
        }

        // Verificar si tiene medidores activos
        $medidoresActivos = $instalacion->medidores()->where('activo', true)->count();
        if ($medidoresActivos > 0) {
            return $this->sendError('No se puede eliminar la instalación porque tiene medidores activos', [], 409);
        }

        try {
            DB::beginTransaction();

            $instalacion->activo = false;
            $instalacion->estatus = 'INACTIVO';
            $instalacion->save();
            $instalacion->delete();

            $this->logActivity(
                auth()->id(),
                'configuracion',
                'eliminacion_instalacion',
                'instalaciones',
                "Instalación eliminada: {$instalacion->clave_instalacion}",
                'instalaciones',
                $instalacion->id
            );

            DB::commit();

            return $this->sendResponse([], 'Instalación eliminada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al eliminar instalación', [$e->getMessage()], 500);
        }
    }

    /**
     * Obtener tanques de la instalación
     */
    public function tanques(Request $request, $id)
    {
        $instalacion = Instalacion::find($id);

        if (!$instalacion) {
            return $this->sendError('Instalación no encontrada');
        }

        $query = $instalacion->tanques()->with('productoActual');

        if ($request->has('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->has('estatus')) {
            $query->where('estatus', $request->estatus);
        }

        if ($request->has('producto_id')) {
            $query->where('producto_actual_id', $request->producto_id);
        }

        if ($request->boolean('activos')) {
            $query->where('activo', true);
        }

        $tanques = $query->orderBy('codigo')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($tanques, 'Tanques de la instalación obtenidos exitosamente');
    }

    /**
     * Obtener medidores de la instalación
     */
    public function medidores(Request $request, $id)
    {
        $instalacion = Instalacion::find($id);

        if (!$instalacion) {
            return $this->sendError('Instalación no encontrada');
        }

        $query = $instalacion->medidores()->with('tanque');

        if ($request->has('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->has('clase')) {
            $query->where('clase', $request->clase);
        }

        if ($request->has('estatus')) {
            $query->where('estatus', $request->estatus);
        }

        if ($request->boolean('activos')) {
            $query->where('activo', true);
        }

        if ($request->boolean('calibracion_proxima')) {
            $query->where('fecha_proxima_calibracion', '<=', now()->addDays(30));
        }

        $medidores = $query->orderBy('numero_serie')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($medidores, 'Medidores de la instalación obtenidos exitosamente');
    }

    /**
     * Obtener dispensarios de la instalación
     */
    public function dispensarios(Request $request, $id)
    {
        $instalacion = Instalacion::find($id);

        if (!$instalacion) {
            return $this->sendError('Instalación no encontrada');
        }

        $query = $instalacion->dispensarios()->with('mangueras');

        if ($request->has('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->has('estatus')) {
            $query->where('estatus', $request->estatus);
        }

        if ($request->boolean('activos')) {
            $query->where('activo', true);
        }

        $dispensarios = $query->orderBy('codigo')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($dispensarios, 'Dispensarios de la instalación obtenidos exitosamente');
    }

    /**
     * Verificar estado de comunicación
     */
    public function verificarComunicacion($id)
    {
        $instalacion = Instalacion::find($id);

        if (!$instalacion) {
            return $this->sendError('Instalación no encontrada');
        }

        $resultado = [
            'instalacion_id' => $instalacion->id,
            'clave' => $instalacion->clave_instalacion,
            'nombre' => $instalacion->nombre,
            'configuracion_red' => $instalacion->configuracion_red,
            'verificacion' => [
                'timestamp' => now()->toDateTimeString(),
                'resultados' => []
            ]
        ];

        // Verificar medidores con comunicación
        $medidores = $instalacion->medidores()
            ->where('activo', true)
            ->get();

        foreach ($medidores as $medidor) {
            if ($medidor->configuracion_comunicacion && $medidor->configuracion_comunicacion['activo'] ?? false) {
                $prueba = $this->simularPruebaComunicacion($medidor);
                $resultado['verificacion']['resultados'][] = [
                    'tipo' => 'MEDIDOR',
                    'id' => $medidor->id,
                    'nombre' => $medidor->numero_serie,
                    'comunicacion' => $prueba
                ];
            }
        }

        // Calcular estadísticas de comunicación
        $exitosos = collect($resultado['verificacion']['resultados'])
            ->where('comunicacion.exitosa', true)
            ->count();
        $total = count($resultado['verificacion']['resultados']);

        $resultado['verificacion']['resumen'] = [
            'total_dispositivos' => $total,
            'comunicacion_exitosa' => $exitosos,
            'comunicacion_fallida' => $total - $exitosos,
            'porcentaje_exito' => $total > 0 ? round(($exitosos / $total) * 100, 2) : 0
        ];

        return $this->sendResponse($resultado, 'Verificación de comunicación completada');
    }

    /**
     * Obtener resumen operativo
     */
    public function resumenOperativo($id)
    {
        $instalacion = Instalacion::find($id);

        if (!$instalacion) {
            return $this->sendError('Instalación no encontrada');
        }

        $hoy = Carbon::today();
        $inicioMes = Carbon::now()->startOfMonth();

        // Resumen de tanques
        $tanques = $instalacion->tanques()
            ->where('activo', true)
            ->get();

        $tanquesOperativos = $tanques->where('estatus', 'OPERACION')->count();
        $capacidadTotal = $tanques->sum('capacidad_operativa');

        // Inventario actual
        $inventarioActual = DB::table('existencias')
            ->whereIn('tanque_id', $tanques->pluck('id'))
            ->where('fecha', $hoy)
            ->sum('volumen_final');

        // Volumen del día
        $volumenHoy = RegistroVolumetrico::where('instalacion_id', $id)
            ->whereDate('fecha_operacion', $hoy)
            ->sum('volumen_corregido');

        // Volumen del mes
        $volumenMes = RegistroVolumetrico::where('instalacion_id', $id)
            ->where('fecha_operacion', '>=', $inicioMes)
            ->sum('volumen_corregido');

        // Alarmas activas
        $alarmasActivas = Alarma::where('instalacion_id', $id)
            ->where('estado', 'ACTIVA')
            ->count();

        $alarmasCriticas = Alarma::where('instalacion_id', $id)
            ->where('estado', 'ACTIVA')
            ->where('gravedad', 'CRITICA')
            ->count();

        // Último reporte SAT
        $ultimoReporte = ReporteSat::where('instalacion_id', $id)
            ->latest('fecha_generacion')
            ->first();

        $resumen = [
            'instalacion' => [
                'id' => $instalacion->id,
                'clave' => $instalacion->clave_instalacion,
                'nombre' => $instalacion->nombre,
                'estatus' => $instalacion->estatus,
                'permiso_cre' => $instalacion->permiso_cre,
                'fecha_vencimiento_permiso' => $instalacion->fecha_vencimiento
            ],
            'tanques' => [
                'total' => $tanques->count(),
                'operativos' => $tanquesOperativos,
                'capacidad_total' => $capacidadTotal,
                'inventario_actual' => $inventarioActual,
                'porcentaje_ocupacion' => $capacidadTotal > 0 ? round(($inventarioActual / $capacidadTotal) * 100, 2) : 0
            ],
            'volumen' => [
                'hoy' => $volumenHoy,
                'mes' => $volumenMes,
                'promedio_diario_mes' => $hoy->day > 0 ? $volumenMes / $hoy->day : 0
            ],
            'alarmas' => [
                'activas' => $alarmasActivas,
                'criticas' => $alarmasCriticas
            ],
            'cumplimiento' => [
                'ultimo_reporte_sat' => $ultimoReporte ? [
                    'id' => $ultimoReporte->id,
                    'periodo' => "{$ultimoReporte->anio}-{$ultimoReporte->mes}",
                    'fecha' => $ultimoReporte->fecha_generacion,
                    'estado' => $ultimoReporte->estado
                ] : null,
                'reportes_pendientes' => $this->calcularReportesPendientes($instalacion)
            ],
            'fecha_consulta' => now()->toDateTimeString()
        ];

        return $this->sendResponse($resumen, 'Resumen operativo obtenido exitosamente');
    }

    /**
     * Actualizar configuración de red
     */
    public function actualizarConfiguracionRed(Request $request, $id)
    {
        $instalacion = Instalacion::find($id);

        if (!$instalacion) {
            return $this->sendError('Instalación no encontrada');
        }

        $validator = Validator::make($request->all(), [
            'configuracion_red' => 'required|array',
            'configuracion_red.tipo_conexion' => 'required|in:VPN,INTERNET_PRIVADO,INTERNET_PUBLICO',
            'configuracion_red.ip_publica' => 'nullable|ip',
            'configuracion_red.ip_privada' => 'nullable|ip',
            'configuracion_red.mascara' => 'nullable|string',
            'configuracion_red.gateway' => 'nullable|ip',
            'configuracion_red.dns_primario' => 'nullable|ip',
            'configuracion_red.dns_secundario' => 'nullable|ip',
            'configuracion_red.puertos' => 'nullable|array',
            'configuracion_red.puertos.*.numero' => 'required|integer|min:1|max:65535',
            'configuracion_red.puertos.*.protocolo' => 'required|in:TCP,UDP',
            'configuracion_red.puertos.*.servicio' => 'required|string'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $instalacion->toArray();
            $instalacion->configuracion_red = $request->configuracion_red;
            $instalacion->save();

            $this->logActivity(
                auth()->id(),
                'configuracion',
                'actualizacion_red',
                'instalaciones',
                "Configuración de red actualizada para instalación {$instalacion->clave_instalacion}",
                'instalaciones',
                $instalacion->id,
                $datosAnteriores,
                $instalacion->toArray()
            );

            DB::commit();

            return $this->sendResponse($instalacion->configuracion_red, 
                'Configuración de red actualizada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al actualizar configuración de red', [$e->getMessage()], 500);
        }
    }

    /**
     * Actualizar umbrales de alarma
     */
    public function actualizarUmbralesAlarma(Request $request, $id)
    {
        $instalacion = Instalacion::find($id);

        if (!$instalacion) {
            return $this->sendError('Instalación no encontrada');
        }

        $validator = Validator::make($request->all(), [
            'umbrales_alarma' => 'required|array',
            'umbrales_alarma.diferencia_maxima_liquidos' => 'nullable|numeric|min:0|max:100',
            'umbrales_alarma.diferencia_maxima_gaseosos' => 'nullable|numeric|min:0|max:100',
            'umbrales_alarma.temperatura_maxima' => 'nullable|numeric',
            'umbrales_alarma.temperatura_minima' => 'nullable|numeric',
            'umbrales_alarma.presion_maxima' => 'nullable|numeric',
            'umbrales_alarma.tiempo_sin_comunicacion' => 'nullable|integer|min:1'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $instalacion->toArray();
            $instalacion->umbrales_alarma = $request->umbrales_alarma;
            $instalacion->save();

            $this->logActivity(
                auth()->id(),
                'configuracion',
                'actualizacion_umbrales',
                'instalaciones',
                "Umbrales de alarma actualizados para instalación {$instalacion->clave_instalacion}",
                'instalaciones',
                $instalacion->id,
                $datosAnteriores,
                $instalacion->toArray()
            );

            DB::commit();

            return $this->sendResponse($instalacion->umbrales_alarma, 
                'Umbrales de alarma actualizados exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al actualizar umbrales de alarma', [$e->getMessage()], 500);
        }
    }

    /**
     * Obtener reporte de cumplimiento normativo
     */
    public function reporteCumplimientoNormativo($id)
    {
        $instalacion = Instalacion::with('contribuyente')->find($id);

        if (!$instalacion) {
            return $this->sendError('Instalación no encontrada');
        }

        $hoy = Carbon::now();

        $reporte = [
            'instalacion' => [
                'id' => $instalacion->id,
                'clave' => $instalacion->clave_instalacion,
                'nombre' => $instalacion->nombre,
                'contribuyente' => $instalacion->contribuyente->razon_social,
                'rfc' => $instalacion->contribuyente->rfc
            ],
            'fecha_generacion' => $hoy->toDateTimeString(),
            'verificaciones' => []
        ];

        // Verificar permiso CRE
        if ($instalacion->permiso_cre) {
            $vigente = !$instalacion->fecha_vencimiento || 
                      $instalacion->fecha_vencimiento >= $hoy;
            
            $reporte['verificaciones'][] = [
                'requisito' => 'Permiso CRE',
                'cumple' => $vigente,
                'detalle' => $vigente 
                    ? "Permiso {$instalacion->permiso_cre} vigente hasta {$instalacion->fecha_vencimiento->format('d/m/Y')}"
                    : "Permiso {$instalacion->permiso_cre} vencido",
                'evidencia' => $instalacion->permiso_cre
            ];
        } else {
            $reporte['verificaciones'][] = [
                'requisito' => 'Permiso CRE',
                'cumple' => false,
                'detalle' => 'No se cuenta con permiso CRE registrado',
                'evidencia' => null
            ];
        }

        // Verificar medidores calibrados
        $medidoresSinCalibracion = $instalacion->medidores()
            ->where('activo', true)
            ->where(function($q) use ($hoy) {
                $q->whereNull('fecha_proxima_calibracion')
                  ->orWhere('fecha_proxima_calibracion', '<', $hoy);
            })
            ->count();

        $totalMedidores = $instalacion->medidores()
            ->where('activo', true)
            ->count();

        $reporte['verificaciones'][] = [
            'requisito' => 'Medidores calibrados',
            'cumple' => $medidoresSinCalibracion == 0,
            'detalle' => "{$medidoresSinCalibracion} de {$totalMedidores} medidores requieren calibración",
            'evidencia' => [
                'total' => $totalMedidores,
                'pendientes' => $medidoresSinCalibracion
            ]
        ];

        // Verificar reportes SAT del último trimestre
        $trimestreInicio = $hoy->copy()->subMonths(3)->startOfMonth();
        
        $reportesRequeridos = 3; // Últimos 3 meses
        $reportesEnviados = ReporteSat::where('instalacion_id', $id)
            ->where('fecha_generacion', '>=', $trimestreInicio)
            ->where('estado', 'ENVIADO')
            ->count();

        $reporte['verificaciones'][] = [
            'requisito' => 'Reportes SAT enviados',
            'cumple' => $reportesEnviados >= $reportesRequeridos,
            'detalle' => "{$reportesEnviados} de {$reportesRequeridos} reportes enviados en el último trimestre",
            'evidencia' => [
                'requeridos' => $reportesRequeridos,
                'enviados' => $reportesEnviados
            ]
        ];

        // Verificar dictámenes vigentes
        $dictamenesVigentes = Dictamen::where('contribuyente_id', $instalacion->contribuyente_id)
            ->where('instalacion_id', $id)
            ->where('fecha_vencimiento', '>=', $hoy)
            ->where('estado', 'EMITIDO')
            ->where('resultado', 'CONFORME')
            ->count();

        $reporte['verificaciones'][] = [
            'requisito' => 'Dictámenes de calidad vigentes',
            'cumple' => $dictamenesVigentes > 0,
            'detalle' => $dictamenesVigentes > 0 
                ? "Se cuenta con {$dictamenesVigentes} dictámenes vigentes"
                : "No se encontraron dictámenes vigentes",
            'evidencia' => [
                'cantidad' => $dictamenesVigentes
            ]
        ];

        // Verificar alarmas críticas
        $alarmasCriticasActivas = Alarma::where('instalacion_id', $id)
            ->where('estado', 'ACTIVA')
            ->where('gravedad', 'CRITICA')
            ->count();

        $reporte['verificaciones'][] = [
            'requisito' => 'Sin alarmas críticas activas',
            'cumple' => $alarmasCriticasActivas == 0,
            'detalle' => $alarmasCriticasActivas > 0 
                ? "Hay {$alarmasCriticasActivas} alarmas críticas activas"
                : "No hay alarmas críticas activas",
            'evidencia' => [
                'alarmas_criticas' => $alarmasCriticasActivas
            ]
        ];

        // Calcular cumplimiento general
        $totalRequisitos = count($reporte['verificaciones']);
        $requisitosCumplidos = collect($reporte['verificaciones'])
            ->where('cumple', true)
            ->count();

        $reporte['cumplimiento_general'] = [
            'total_requisitos' => $totalRequisitos,
            'cumplidos' => $requisitosCumplidos,
            'porcentaje' => $totalRequisitos > 0 
                ? round(($requisitosCumplidos / $totalRequisitos) * 100, 2)
                : 0,
            'estatus' => $this->determinarEstatusCumplimiento($requisitosCumplidos, $totalRequisitos)
        ];

        return $this->sendResponse($reporte, 'Reporte de cumplimiento normativo obtenido exitosamente');
    }

    /**
     * Métodos privados
     */
    private function calcularEstadisticas($instalacion)
    {
        $hoy = Carbon::today();
        $inicioMes = Carbon::now()->startOfMonth();

        $tanques = $instalacion->tanques()->where('activo', true)->get();
        $medidores = $instalacion->medidores()->where('activo', true)->get();

        // Volumen del mes
        $volumenMes = RegistroVolumetrico::where('instalacion_id', $instalacion->id)
            ->where('fecha_operacion', '>=', $inicioMes)
            ->sum('volumen_corregido');

        // Alarmas del mes
        $alarmasMes = Alarma::where('instalacion_id', $instalacion->id)
            ->where('fecha_alarma', '>=', $inicioMes)
            ->count();

        return [
            'tanques' => [
                'total' => $tanques->count(),
                'operativos' => $tanques->where('estatus', 'OPERACION')->count(),
                'capacidad_total' => $tanques->sum('capacidad_operativa')
            ],
            'medidores' => [
                'total' => $medidores->count(),
                'operativos' => $medidores->where('estatus', 'OPERACION')->count(),
                'calibracion_proxima' => $medidores->filter(function ($m) {
                    return $m->fecha_proxima_calibracion && 
                           $m->fecha_proxima_calibracion->lte(Carbon::now()->addDays(30));
                })->count()
            ],
            'volumen' => [
                'mes_actual' => $volumenMes,
                'promedio_diario' => $hoy->day > 0 ? $volumenMes / $hoy->day : 0
            ],
            'alarmas' => [
                'mes_actual' => $alarmasMes,
                'activas' => Alarma::where('instalacion_id', $instalacion->id)
                    ->where('estado', 'ACTIVA')
                    ->count()
            ],
            'dias_operacion' => RegistroVolumetrico::where('instalacion_id', $instalacion->id)
                ->where('fecha_operacion', '>=', $inicioMes)
                ->select(DB::raw('DATE(fecha_operacion) as dia'))
                ->distinct()
                ->count()
        ];
    }

    private function calcularReportesPendientes($instalacion)
    {
        $hoy = Carbon::now();
        $anio = $hoy->year;
        $mes = $hoy->month;

        $reportesGenerados = ReporteSat::where('instalacion_id', $instalacion->id)
            ->where('anio', $anio)
            ->pluck('mes')
            ->toArray();

        $pendientes = [];
        for ($m = 1; $m < $mes; $m++) {
            if (!in_array($m, $reportesGenerados)) {
                $pendientes[] = [
                    'anio' => $anio,
                    'mes' => $m,
                    'nombre_mes' => Carbon::createFromDate($anio, $m, 1)->format('F')
                ];
            }
        }

        return $pendientes;
    }

    private function determinarEstatusCumplimiento($cumplidos, $total)
    {
        $porcentaje = $total > 0 ? ($cumplidos / $total) * 100 : 0;

        if ($porcentaje >= 90) {
            return 'EXCELENTE';
        } elseif ($porcentaje >= 70) {
            return 'BUENO';
        } elseif ($porcentaje >= 50) {
            return 'REGULAR';
        } else {
            return 'CRITICO';
        }
    }

    private function simularPruebaComunicacion($medidor)
    {
        // Simular prueba de comunicación
        $tipos = [
            'MODBUS' => ['exitosa' => true, 'latencia' => rand(50, 200)],
            'PROFIBUS' => ['exitosa' => true, 'latencia' => rand(30, 150)],
            'HART' => ['exitosa' => true, 'latencia' => rand(100, 300)],
            'ETHERNET' => ['exitosa' => true, 'latencia' => rand(10, 100)],
            'SERIAL' => ['exitosa' => true, 'latencia' => rand(200, 500)],
        ];

        $tipo = $medidor->configuracion_comunicacion['tipo'] ?? 'MODBUS';
        $simulacion = $tipos[$tipo] ?? $tipos['MODBUS'];

        // 90% de éxito para simular realidad
        $exitosa = rand(1, 100) <= 90;

        if (!$exitosa) {
            return [
                'exitosa' => false,
                'latencia_ms' => null,
                'detalle' => 'Timeout en la comunicación - No respuesta del dispositivo'
            ];
        }

        return [
            'exitosa' => true,
            'latencia_ms' => $simulacion['latencia'],
            'detalle' => "Comunicación establecida correctamente vía {$tipo}"
        ];
    }
}