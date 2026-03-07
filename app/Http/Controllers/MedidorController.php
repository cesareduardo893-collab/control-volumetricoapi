<?php

namespace App\Http\Controllers;

use App\Models\Medidor;
use App\Models\Instalacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MedidorController extends BaseController
{
    /**
     * Listar medidores
     */
    public function index(Request $request)
    {
        $query = Medidor::with(['instalacion', 'tanque']);

        // Filtros
        if ($request->has('instalacion_id')) {
            $query->where('instalacion_id', $request->instalacion_id);
        }

        if ($request->has('tanque_id')) {
            $query->where('tanque_id', $request->tanque_id);
        }

        if ($request->has('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->has('clase')) {
            $query->where('clase', $request->clase);
        }

        if ($request->has('estatus')) {
            $query->where('estatus', $request->estatus);
        }

        if ($request->has('tecnologia')) {
            $query->where('tecnologia', $request->tecnologia);
        }

        if ($request->has('calibracion_proxima')) {
            $query->where('fecha_proxima_calibracion', '<=', Carbon::parse($request->calibracion_proxima));
        }

        if ($request->has('marca')) {
            $query->where('marca', 'LIKE', "%{$request->marca}%");
        }

        if ($request->has('modelo')) {
            $query->where('modelo', 'LIKE', "%{$request->modelo}%");
        }

        if ($request->boolean('comunicacion_activa')) {
            $query->where('configuracion_comunicacion->activo', true);
        }

        if ($request->boolean('alerta_activa')) {
            $query->where(function($q) {
                $q->whereDate('fecha_proxima_calibracion', '<=', now()->addMonth())
                  ->orWhere('estatus', 'FALLA');
            });
        }

        $medidores = $query->orderBy('instalacion_id')
            ->orderBy('numero_serie')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($medidores, 'Medidores obtenidos exitosamente');
    }

    /**
     * Crear medidor
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'instalacion_id' => 'required|exists:instalaciones,id',
            'tanque_id' => 'nullable|exists:tanques,id',
            'tipo' => 'required|in:PRIMARIO,SECUNDARIO,TERCIARIO',
            'clase' => 'required|in:ESTATICO,DINAMICO,PORTATIL',
            'tecnologia' => 'required|in:ULTRASONIDO,RADAR,DESPLAZAMIENTO_POSITIVO,TURBINA,CORIOLIS,MAGNETICO,TERMAL,OTRO',
            'numero_serie' => 'required|string|max:100|unique:medidores,numero_serie',
            'marca' => 'required|string|max:100',
            'modelo' => 'required|string|max:100',
            'rango_medicion_min' => 'required|numeric|min:0',
            'rango_medicion_max' => 'required|numeric|min:0|gt:rango_medicion_min',
            'unidad_medicion' => 'required|in:LITROS,M3,BARRILES,GALONES,',
            'precision' => 'required|numeric|min:0|max:100',
            'clase_exactitud' => 'required|string|max:50',
            'resolucion' => 'nullable|numeric|min:0',
            'repetibilidad' => 'nullable|numeric|min:0',
            'linealidad' => 'nullable|numeric|min:0',
            'deriva' => 'nullable|numeric|min:0',
            'temperatura_operacion_min' => 'nullable|numeric',
            'temperatura_operacion_max' => 'nullable|numeric|gt:temperatura_operacion_min',
            'presion_operacion_max' => 'nullable|numeric|min:0',
            'caudal_maximo' => 'nullable|numeric|min:0',
            'fecha_fabricacion' => 'nullable|date',
            'fecha_instalacion' => 'nullable|date',
            'fecha_ultima_calibracion' => 'nullable|date',
            'fecha_proxima_calibracion' => 'nullable|date|after:fecha_ultima_calibracion',
            'numero_certificado_calibracion' => 'nullable|string|max:100',
            'laboratorio_calibracion' => 'nullable|string|max:255',
            'configuracion_comunicacion' => 'nullable|array',
            'configuracion_comunicacion.tipo' => 'required_with:configuracion_comunicacion|in:MODBUS,PROFIBUS,HART,ETHERNET,SERIAL,ANALOGICO',
            'configuracion_comunicacion.direccion' => 'required_with:configuracion_comunicacion|string',
            'configuracion_comunicacion.parametros' => 'nullable|array',
            'configuracion_comunicacion.activo' => 'boolean',
            'parametros_medicion' => 'nullable|array',
            'parametros_medicion.*.nombre' => 'required_with:parametros_medicion|string',
            'parametros_medicion.*.unidad' => 'required_with:parametros_medicion|string',
            'parametros_medicion.*.factor_conversion' => 'nullable|numeric',
            'observaciones' => 'nullable|string|max:1000',
            'estatus' => 'required|in:OPERACION,MANTENIMIENTO,FALLA,INACTIVO',
            'activo' => 'boolean'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            // Validar instalación activa
            $instalacion = Instalacion::find($request->instalacion_id);
            if (!$instalacion->activo) {
                return $this->sendError('La instalación no está activa', [], 422);
            }

            // Si se especifica tanque, validar que pertenezca a la instalación
            if ($request->has('tanque_id') && $request->tanque_id) {
                $tanque = Tanque::find($request->tanque_id);
                if ($tanque->instalacion_id != $request->instalacion_id) {
                    return $this->sendError('El tanque no pertenece a la instalación especificada', [], 422);
                }
            }

            // Validar consistencia según tipo de medidor
            if ($request->tipo == 'PRIMARIO' && !$request->tanque_id) {
                return $this->sendError('Los medidores primarios deben estar asociados a un tanque', [], 422);
            }

            $medidor = Medidor::create([
                'instalacion_id' => $request->instalacion_id,
                'tanque_id' => $request->tanque_id,
                'tipo' => $request->tipo,
                'clase' => $request->clase,
                'tecnologia' => $request->tecnologia,
                'numero_serie' => $request->numero_serie,
                'marca' => $request->marca,
                'modelo' => $request->modelo,
                'rango_medicion_min' => $request->rango_medicion_min,
                'rango_medicion_max' => $request->rango_medicion_max,
                'unidad_medicion' => $request->unidad_medicion,
                'precision' => $request->precision,
                'clase_exactitud' => $request->clase_exactitud,
                'resolucion' => $request->resolucion,
                'repetibilidad' => $request->repetibilidad,
                'linealidad' => $request->linealidad,
                'deriva' => $request->deriva,
                'temperatura_operacion_min' => $request->temperatura_operacion_min,
                'temperatura_operacion_max' => $request->temperatura_operacion_max,
                'presion_operacion_max' => $request->presion_operacion_max,
                'caudal_maximo' => $request->caudal_maximo,
                'fecha_fabricacion' => $request->fecha_fabricacion,
                'fecha_instalacion' => $request->fecha_instalacion,
                'fecha_ultima_calibracion' => $request->fecha_ultima_calibracion,
                'fecha_proxima_calibracion' => $request->fecha_proxima_calibracion,
                'numero_certificado_calibracion' => $request->numero_certificado_calibracion,
                'laboratorio_calibracion' => $request->laboratorio_calibracion,
                'configuracion_comunicacion' => $request->configuracion_comunicacion,
                'parametros_medicion' => $request->parametros_medicion,
                'observaciones' => $request->observaciones,
                'estatus' => $request->estatus,
                'activo' => $request->boolean('activo', true)
            ]);

            $this->logActivity(
                auth()->id(),
                'configuracion',
                'creacion_medidor',
                'medidores',
                "Medidor creado: {$medidor->numero_serie} - {$medidor->marca} {$medidor->modelo}",
                'medidores',
                $medidor->id
            );

            DB::commit();

            return $this->sendResponse($medidor->load(['instalacion', 'tanque']), 'Medidor creado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al crear medidor', [$e->getMessage()], 500);
        }
    }

    /**
     * Mostrar medidor
     */
    public function show($id)
    {
        $medidor = Medidor::with([
            'instalacion',
            'tanque',
            'mangueras' => function($q) {
                $q->with('dispensario');
            },
            'registrosVolumetricos' => function($q) {
                $q->latest()->limit(20);
            }
        ])->find($id);

        if (!$medidor) {
            return $this->sendError('Medidor no encontrado');
        }

        // Calcular estadísticas de operación
        $medidor->estadisticas = $this->calcularEstadisticasMedidor($medidor);

        return $this->sendResponse($medidor, 'Medidor obtenido exitosamente');
    }

    /**
     * Actualizar medidor
     */
    public function update(Request $request, $id)
    {
        $medidor = Medidor::find($id);

        if (!$medidor) {
            return $this->sendError('Medidor no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'tanque_id' => 'nullable|exists:tanques,id',
            'tipo' => 'sometimes|in:PRIMARIO,SECUNDARIO,TERCIARIO',
            'clase' => 'sometimes|in:ESTATICO,DINAMICO,PORTATIL',
            'tecnologia' => 'sometimes|in:ULTRASONIDO,RADAR,DESPLAZAMIENTO_POSITIVO,TURBINA,CORIOLIS,MAGNETICO,TERMAL,OTRO',
            'numero_serie' => "sometimes|string|max:100|unique:medidores,numero_serie,{$id}",
            'marca' => 'sometimes|string|max:100',
            'modelo' => 'sometimes|string|max:100',
            'rango_medicion_min' => 'sometimes|numeric|min:0',
            'rango_medicion_max' => 'sometimes|numeric|min:0|gt:rango_medicion_min',
            'unidad_medicion' => 'sometimes|in:LITROS,M3,BARRILES,GALONES,',
            'precision' => 'sometimes|numeric|min:0|max:100',
            'clase_exactitud' => 'sometimes|string|max:50',
            'resolucion' => 'nullable|numeric|min:0',
            'repetibilidad' => 'nullable|numeric|min:0',
            'linealidad' => 'nullable|numeric|min:0',
            'deriva' => 'nullable|numeric|min:0',
            'temperatura_operacion_min' => 'nullable|numeric',
            'temperatura_operacion_max' => 'nullable|numeric|gt:temperatura_operacion_min',
            'presion_operacion_max' => 'nullable|numeric|min:0',
            'caudal_maximo' => 'nullable|numeric|min:0',
            'fecha_fabricacion' => 'nullable|date',
            'fecha_instalacion' => 'nullable|date',
            'fecha_ultima_calibracion' => 'nullable|date',
            'fecha_proxima_calibracion' => 'nullable|date|after:fecha_ultima_calibracion',
            'numero_certificado_calibracion' => 'nullable|string|max:100',
            'laboratorio_calibracion' => 'nullable|string|max:255',
            'configuracion_comunicacion' => 'nullable|array',
            'parametros_medicion' => 'nullable|array',
            'observaciones' => 'nullable|string|max:1000',
            'estatus' => 'sometimes|in:OPERACION,MANTENIMIENTO,FALLA,INACTIVO',
            'activo' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            // Validar tanque si se especifica
            if ($request->has('tanque_id') && $request->tanque_id) {
                $tanque = Tanque::find($request->tanque_id);
                if ($tanque->instalacion_id != $medidor->instalacion_id) {
                    return $this->sendError('El tanque no pertenece a la instalación del medidor', [], 422);
                }
            }

            $datosAnteriores = $medidor->toArray();
            $medidor->update($request->all());

            $this->logActivity(
                auth()->id(),
                'configuracion',
                'actualizacion_medidor',
                'medidores',
                "Medidor actualizado: {$medidor->numero_serie}",
                'medidores',
                $medidor->id,
                $datosAnteriores,
                $medidor->toArray()
            );

            DB::commit();

            return $this->sendResponse($medidor, 'Medidor actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al actualizar medidor', [$e->getMessage()], 500);
        }
    }

    /**
     * Eliminar medidor (soft delete)
     */
    public function destroy($id)
    {
        $medidor = Medidor::find($id);

        if (!$medidor) {
            return $this->sendError('Medidor no encontrado');
        }

        // Verificar si tiene registros volumétricos
        $tieneRegistros = $medidor->registrosVolumetricos()->exists();
        
        if ($tieneRegistros) {
            return $this->sendError('No se puede eliminar el medidor porque tiene registros volumétricos asociados', [], 409);
        }

        // Verificar si tiene mangueras asociadas
        $tieneMangueras = $medidor->mangueras()->exists();
        
        if ($tieneMangueras) {
            return $this->sendError('No se puede eliminar el medidor porque tiene mangueras asociadas', [], 409);
        }

        try {
            DB::beginTransaction();

            $medidor->activo = false;
            $medidor->estatus = 'INACTIVO';
            $medidor->save();
            $medidor->delete();

            $this->logActivity(
                auth()->id(),
                'configuracion',
                'eliminacion_medidor',
                'medidores',
                "Medidor eliminado: {$medidor->numero_serie}",
                'medidores',
                $medidor->id
            );

            DB::commit();

            return $this->sendResponse([], 'Medidor eliminado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al eliminar medidor', [$e->getMessage()], 500);
        }
    }

    /**
     * Registrar calibración
     */
    public function registrarCalibracion(Request $request, $id)
    {
        $medidor = Medidor::find($id);

        if (!$medidor) {
            return $this->sendError('Medidor no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'fecha_calibracion' => 'required|date',
            'numero_certificado' => 'required|string|max:100',
            'laboratorio' => 'required|string|max:255',
            'tecnico_responsable' => 'required|string|max:255',
            'patron_utilizado' => 'required|string|max:255',
            'trazabilidad' => 'required|string|max:255',
            'condiciones_ambientales' => 'required|array',
            'condiciones_ambientales.temperatura' => 'required|numeric',
            'condiciones_ambientales.humedad' => 'required|numeric',
            'condiciones_ambientales.presion' => 'required|numeric',
            'puntos_calibracion' => 'required|array|min:3',
            'puntos_calibracion.*.valor_patron' => 'required|numeric',
            'puntos_calibracion.*.valor_medido' => 'required|numeric',
            'puntos_calibracion.*.error' => 'required|numeric',
            'resultados' => 'required|array',
            'resultados.error_maximo' => 'required|numeric',
            'resultados.incertidumbre' => 'required|numeric',
            'resultados.conforme' => 'required|boolean',
            'nueva_precision' => 'required|numeric|min:0|max:100',
            'nuevo_factor_correccion' => 'nullable|numeric',
            'observaciones' => 'nullable|string|max:1000',
            'archivo_certificado' => 'nullable|file|mimes:pdf|max:10240'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            // Guardar archivo del certificado
            $rutaArchivo = null;
            if ($request->hasFile('archivo_certificado')) {
                $rutaArchivo = $request->file('archivo_certificado')
                    ->store("certificados/medidores/{$medidor->id}", 'public');
            }

            // Actualizar información del medidor
            $datosAnteriores = $medidor->toArray();
            
            $medidor->fecha_ultima_calibracion = $request->fecha_calibracion;
            $medidor->fecha_proxima_calibracion = Carbon::parse($request->fecha_calibracion)->addYear();
            $medidor->numero_certificado_calibracion = $request->numero_certificado;
            $medidor->laboratorio_calibracion = $request->laboratorio;
            $medidor->precision = $request->nueva_precision;
            
            // Actualizar parámetros de medición con nuevo factor de corrección
            $parametros = $medidor->parametros_medicion ?? [];
            if ($request->has('nuevo_factor_correccion')) {
                foreach ($parametros as &$param) {
                    if ($param['nombre'] == 'volumen') {
                        $param['factor_conversion'] = $request->nuevo_factor_correccion;
                    }
                }
            }
            $medidor->parametros_medicion = $parametros;
            
            $metadata = $medidor->metadata ?? [];
            $metadata['calibraciones'][] = [
                'fecha' => $request->fecha_calibracion,
                'certificado' => $request->numero_certificado,
                'laboratorio' => $request->laboratorio,
                'tecnico' => $request->tecnico_responsable,
                'patron' => $request->patron_utilizado,
                'trazabilidad' => $request->trazabilidad,
                'condiciones' => $request->condiciones_ambientales,
                'puntos' => $request->puntos_calibracion,
                'resultados' => $request->resultados,
                'precision_anterior' => $datosAnteriores['precision'],
                'precision_nueva' => $request->nueva_precision,
                'archivo' => $rutaArchivo,
                'observaciones' => $request->observaciones,
                'registrado_por' => auth()->id(),
                'fecha_registro' => now()->toDateTimeString()
            ];
            $medidor->metadata = $metadata;
            
            $medidor->save();

            $this->logActivity(
                auth()->id(),
                'mantenimiento',
                'calibracion_medidor',
                'medidores',
                "Calibración registrada para medidor {$medidor->numero_serie} - Cert: {$request->numero_certificado}",
                'medidores',
                $medidor->id,
                $datosAnteriores,
                $medidor->toArray()
            );

            DB::commit();

            return $this->sendResponse($medidor, 'Calibración registrada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al registrar calibración', [$e->getMessage()], 500);
        }
    }

    /**
     * Probar comunicación
     */
    public function probarComunicacion($id)
    {
        $medidor = Medidor::find($id);

        if (!$medidor) {
            return $this->sendError('Medidor no encontrado');
        }

        if (!$medidor->configuracion_comunicacion || !$medidor->configuracion_comunicacion['activo']) {
            return $this->sendError('El medidor no tiene configuración de comunicación activa', [], 400);
        }

        try {
            // Simular prueba de comunicación
            $resultado = $this->simularPruebaComunicacion($medidor);

            // Registrar intento
            $metadata = $medidor->metadata ?? [];
            $metadata['pruebas_comunicacion'][] = [
                'fecha' => now()->toDateTimeString(),
                'exitosa' => $resultado['exitosa'],
                'latencia_ms' => $resultado['latencia_ms'],
                'detalle' => $resultado['detalle'],
                'usuario' => auth()->id()
            ];
            $medidor->metadata = $metadata;
            $medidor->save();

            $this->logActivity(
                auth()->id(),
                'mantenimiento',
                'prueba_comunicacion',
                'medidores',
                "Prueba de comunicación para medidor {$medidor->numero_serie}: " . ($resultado['exitosa'] ? 'EXITOSA' : 'FALLIDA'),
                'medidores',
                $medidor->id
            );

            return $this->sendResponse($resultado, 'Prueba de comunicación realizada');

        } catch (\Exception $e) {
            return $this->sendError('Error en prueba de comunicación', [$e->getMessage()], 500);
        }
    }

    /**
     * Obtener lecturas en tiempo real
     */
    public function lecturaTiempoReal($id)
    {
        $medidor = Medidor::find($id);

        if (!$medidor) {
            return $this->sendError('Medidor no encontrado');
        }

        if ($medidor->estatus != 'OPERACION') {
            return $this->sendError('El medidor no está en operación', [], 400);
        }

        try {
            // Simular lectura en tiempo real
            $lectura = $this->simularLecturaTiempoReal($medidor);

            // Registrar lectura
            $metadata = $medidor->metadata ?? [];
            $metadata['lecturas_tiempo_real'][] = [
                'fecha' => now()->toDateTimeString(),
                'lectura' => $lectura,
                'usuario' => auth()->id()
            ];
            $medidor->metadata = $metadata;
            $medidor->save();

            return $this->sendResponse($lectura, 'Lectura en tiempo real obtenida');

        } catch (\Exception $e) {
            return $this->sendError('Error al obtener lectura', [$e->getMessage()], 500);
        }
    }

    /**
     * Verificar estado del medidor
     */
    public function verificarEstado($id)
    {
        $medidor = Medidor::find($id);

        if (!$medidor) {
            return $this->sendError('Medidor no encontrado');
        }

        $alertas = [];

        // Verificar calibración
        if ($medidor->fecha_proxima_calibracion) {
            $diasParaCalibracion = now()->diffInDays($medidor->fecha_proxima_calibracion, false);
            
            if ($diasParaCalibracion <= 30) {
                $alertas[] = [
                    'tipo' => 'CALIBRACION_PROXIMA',
                    'severidad' => $diasParaCalibracion <= 7 ? 'ALTA' : 'MEDIA',
                    'mensaje' => "Próxima calibración en {$diasParaCalibracion} días",
                    'fecha_limite' => $medidor->fecha_proxima_calibracion->format('Y-m-d')
                ];
            }
        }

        // Verificar comunicación
        if ($medidor->configuracion_comunicacion && $medidor->configuracion_comunicacion['activo']) {
            $prueba = $this->simularPruebaComunicacion($medidor);
            if (!$prueba['exitosa']) {
                $alertas[] = [
                    'tipo' => 'FALLA_COMUNICACION',
                    'severidad' => 'ALTA',
                    'mensaje' => 'Falla en la comunicación con el medidor',
                    'detalle' => $prueba['detalle']
                ];
            }
        }

        // Verificar última lectura
        $ultimoRegistro = $medidor->registrosVolumetricos()
            ->latest('fecha_operacion')
            ->first();

        if ($ultimoRegistro) {
            $horasSinLectura = now()->diffInHours($ultimoRegistro->fecha_operacion);
            if ($horasSinLectura > 24) {
                $alertas[] = [
                    'tipo' => 'SIN_LECTURA',
                    'severidad' => 'MEDIA',
                    'mensaje' => "No se reciben lecturas desde hace {$horasSinLectura} horas",
                    'ultima_lectura' => $ultimoRegistro->fecha_operacion->format('Y-m-d H:i:s')
                ];
            }
        }

        $estado = [
            'medidor_id' => $medidor->id,
            'numero_serie' => $medidor->numero_serie,
            'estatus' => $medidor->estatus,
            'activo' => $medidor->activo,
            'precision_actual' => $medidor->precision,
            'fecha_ultima_calibracion' => $medidor->fecha_ultima_calibracion,
            'fecha_proxima_calibracion' => $medidor->fecha_proxima_calibracion,
            'comunicacion' => $medidor->configuracion_comunicacion ? [
                'configurada' => true,
                'tipo' => $medidor->configuracion_comunicacion['tipo'] ?? null,
                'activa' => $medidor->configuracion_comunicacion['activo'] ?? false
            ] : ['configurada' => false],
            'ultima_lectura' => $ultimoRegistro ? [
                'fecha' => $ultimoRegistro->fecha_operacion,
                'valor' => $ultimoRegistro->volumen_corregido
            ] : null,
            'alertas' => $alertas,
            'fecha_verificacion' => now()->toDateTimeString()
        ];

        return $this->sendResponse($estado, 'Estado del medidor verificado exitosamente');
    }

    /**
     * Obtener historial de calibraciones
     */
    public function historialCalibraciones($id)
    {
        $medidor = Medidor::find($id);

        if (!$medidor) {
            return $this->sendError('Medidor no encontrado');
        }

        $historial = collect($medidor->metadata['calibraciones'] ?? [])
            ->sortByDesc('fecha')
            ->values();

        return $this->sendResponse($historial, 'Historial de calibraciones obtenido exitosamente');
    }

    /**
     * Métodos privados
     */
    private function calcularEstadisticasMedidor($medidor)
    {
        $registros = $medidor->registrosVolumetricos()
            ->where('fecha_operacion', '>=', now()->subDays(30))
           ->get();

        return [
            'total_registros_30d' => $registros->count(),
            'volumen_total_30d' => $registros->sum('volumen_corregido'),
            'promedio_diario' => $registros->count() > 0 ? $registros->sum('volumen_corregido') / 30 : 0,
            'fecha_primer_registro' => $medidor->registrosVolumetricos()
                ->oldest('fecha_operacion')
                ->first()?->fecha_operacion,
            'fecha_ultimo_registro' => $medidor->registrosVolumetricos()
                ->latest('fecha_operacion')
                ->first()?->fecha_operacion,
            'total_registros_historico' => $medidor->registrosVolumetricos()->count(),
            'tiempo_operacion' => $medidor->fecha_instalacion ? 
                now()->diffInDays($medidor->fecha_instalacion) . ' días' : null
        ];
    }

    private function simularPruebaComunicacion($medidor)
    {
        // Simular diferentes tipos de comunicación
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

    private function simularLecturaTiempoReal($medidor)
    {
        // Simular lectura basada en el tipo de medidor
        $valorBase = rand(1000, 50000) / 10;
        
        // Aplicar variación aleatoria
        $variacion = ($valorBase * $medidor->precision / 100) * (rand(-100, 100) / 100);
        $valor = $valorBase + $variacion;

        return [
            'timestamp' => now()->toIso8601String(),
            'valor_medido' => round($valor, 2),
            'unidad' => $medidor->unidad_medicion,
            'parametros' => [
                'temperatura' => round(20 + (rand(-50, 50) / 10), 1),
                'presion' => round(101.325 + (rand(-10, 10) / 10), 3),
                'caudal_instantaneo' => round(rand(10, 1000) / 10, 1)
            ],
            'estado_medidor' => 'OPERANDO',
            'senal_calidad' => rand(85, 100) . '%'
        ];
    }
}