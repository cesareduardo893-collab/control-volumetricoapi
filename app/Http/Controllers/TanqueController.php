<?php

namespace App\Http\Controllers;

use App\Models\Tanque;
use App\Models\Instalacion;
use App\Models\Existencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TanqueController extends BaseController
{
    /**
     * Listar tanques
     */
    public function index(Request $request)
    {
        $query = Tanque::with(['instalacion', 'productoActual']);

        // Filtros
        if ($request->has('instalacion_id')) {
            $query->where('instalacion_id', $request->instalacion_id);
        }

        if ($request->has('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->has('producto_actual_id')) {
            $query->where('producto_actual_id', $request->producto_actual_id);
        }

        if ($request->has('estatus')) {
            $query->where('estatus', $request->estatus);
        }

        if ($request->has('codigo')) {
            $query->where('codigo', 'LIKE', "%{$request->codigo}%");
        }

        if ($request->has('calibracion_proxima')) {
            $query->where('fecha_proxima_calibracion', '<=', Carbon::parse($request->calibracion_proxima));
        }

        if ($request->boolean('alerta_activa')) {
            $query->where(function($q) {
                $q->where('nivel_actual', '<=', DB::raw('nivel_minimo_operacion'))
                  ->orWhere('nivel_actual', '>=', DB::raw('nivel_maximo_operacion'))
                  ->orWhereDate('fecha_proxima_calibracion', '<=', now()->addMonth());
            });
        }

        $tanques = $query->orderBy('instalacion_id')
            ->orderBy('codigo')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($tanques, 'Tanques obtenidos exitosamente');
    }

    /**
     * Crear tanque
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'instalacion_id' => 'required|exists:instalaciones,id',
            'codigo' => 'required|string|max:50|unique:tanques,codigo,NULL,id,instalacion_id,' . $request->instalacion_id,
            'tipo' => 'required|in:ALMACENAMIENTO,PROCESO,AUTOTANQUE,CARROTANQUE',
            'capacidad_nominal' => 'required|numeric|min:0',
            'capacidad_operativa' => 'required|numeric|min:0|lte:capacidad_nominal',
            'unidad_capacidad' => 'required|in:LITROS,M3,BARRILES,GALONES',
            'nivel_minimo_operacion' => 'required|numeric|min:0',
            'nivel_maximo_operacion' => 'required|numeric|min:0|gt:nivel_minimo_operacion|lte:capacidad_operativa',
            'nivel_alarma_alto' => 'required|numeric|min:0|lte:capacidad_operativa',
            'nivel_alarma_bajo' => 'required|numeric|min:0|lte:nivel_alarma_alto',
            'producto_actual_id' => 'nullable|exists:productos,id',
            'fecha_fabricacion' => 'nullable|date',
            'fecha_instalacion' => 'nullable|date',
            'fecha_ultima_calibracion' => 'nullable|date',
            'fecha_proxima_calibracion' => 'nullable|date|after:fecha_ultima_calibracion',
            'numero_certificado_calibracion' => 'nullable|string|max:100',
            'material' => 'nullable|string|max:100',
            'espesor_pared' => 'nullable|numeric|min:0',
            'temperatura_operacion_min' => 'nullable|numeric',
            'temperatura_operacion_max' => 'nullable|numeric|gt:temperatura_operacion_min',
            'presion_operacion_max' => 'nullable|numeric|min:0',
            'configuracion_medicion' => 'nullable|array',
            'configuracion_medicion.tipo_medicion' => 'required_with:configuracion_medicion|in:AUTOMATICA,MANUAL,MIXTA',
            'configuracion_medicion.instrumentos' => 'required_with:configuracion_medicion|array',
            'configuracion_medicion.frecuencia_lectura' => 'required_with:configuracion_medicion|integer|min:1',
            'tabla_calibracion' => 'nullable|array',
            'tabla_calibracion.*.nivel' => 'required_with:tabla_calibracion|numeric',
            'tabla_calibracion.*.volumen' => 'required_with:tabla_calibracion|numeric',
            'observaciones' => 'nullable|string|max:1000',
            'estatus' => 'required|in:OPERACION,MANTENIMIENTO,INACTIVO,FUERA_SERVICIO',
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

            // Validar consistencia de niveles
            if ($request->nivel_alarma_alto > $request->nivel_maximo_operacion) {
                return $this->sendError('El nivel de alarma alto no puede ser mayor al nivel máximo de operación', [], 422);
            }

            if ($request->nivel_alarma_bajo < $request->nivel_minimo_operacion) {
                return $this->sendError('El nivel de alarma bajo no puede ser menor al nivel mínimo de operación', [], 422);
            }

            $tanque = Tanque::create([
                'instalacion_id' => $request->instalacion_id,
                'codigo' => $request->codigo,
                'tipo' => $request->tipo,
                'capacidad_nominal' => $request->capacidad_nominal,
                'capacidad_operativa' => $request->capacidad_operativa,
                'unidad_capacidad' => $request->unidad_capacidad,
                'nivel_minimo_operacion' => $request->nivel_minimo_operacion,
                'nivel_maximo_operacion' => $request->nivel_maximo_operacion,
                'nivel_alarma_alto' => $request->nivel_alarma_alto,
                'nivel_alarma_bajo' => $request->nivel_alarma_bajo,
                'producto_actual_id' => $request->producto_actual_id,
                'fecha_fabricacion' => $request->fecha_fabricacion,
                'fecha_instalacion' => $request->fecha_instalacion,
                'fecha_ultima_calibracion' => $request->fecha_ultima_calibracion,
                'fecha_proxima_calibracion' => $request->fecha_proxima_calibracion,
                'numero_certificado_calibracion' => $request->numero_certificado_calibracion,
                'material' => $request->material,
                'espesor_pared' => $request->espesor_pared,
                'temperatura_operacion_min' => $request->temperatura_operacion_min,
                'temperatura_operacion_max' => $request->temperatura_operacion_max,
                'presion_operacion_max' => $request->presion_operacion_max,
                'configuracion_medicion' => $request->configuracion_medicion,
                'tabla_calibracion' => $request->tabla_calibracion,
                'observaciones' => $request->observaciones,
                'estatus' => $request->estatus,
                'activo' => $request->boolean('activo', true)
            ]);

            $this->logActivity(
                auth()->id(),
                'configuracion',
                'creacion_tanque',
                'tanques',
                "Tanque creado: {$tanque->codigo} en instalación {$instalacion->clave_instalacion}",
                'tanques',
                $tanque->id
            );

            DB::commit();

            return $this->sendResponse($tanque->load('instalacion'), 'Tanque creado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al crear tanque', [$e->getMessage()], 500);
        }
    }

    /**
     * Mostrar tanque
     */
    public function show($id)
    {
        $tanque = Tanque::with([
            'instalacion',
            'productoActual',
            'medidores' => function($q) {
                $q->where('activo', true);
            },
            'existencias' => function($q) {
                $q->latest()->limit(30);
            },
            'registrosVolumetricos' => function($q) {
                $q->latest()->limit(20);
            },
            'alarmas' => function($q) {
                $q->where('estado', 'ACTIVA');
            }
        ])->find($id);

        if (!$tanque) {
            return $this->sendError('Tanque no encontrado');
        }

        // Calcular nivel actual
        $tanque->nivel_actual = $this->calcularNivelActual($tanque);
        $tanque->porcentaje_ocupacion = ($tanque->nivel_actual / $tanque->capacidad_operativa) * 100;

        return $this->sendResponse($tanque, 'Tanque obtenido exitosamente');
    }

    /**
     * Actualizar tanque
     */
    public function update(Request $request, $id)
    {
        $tanque = Tanque::find($id);

        if (!$tanque) {
            return $this->sendError('Tanque no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'codigo' => "sometimes|string|max:50|unique:tanques,codigo,{$id},id,instalacion_id,{$tanque->instalacion_id}",
            'tipo' => 'sometimes|in:ALMACENAMIENTO,PROCESO,AUTOTANQUE,CARROTANQUE',
            'capacidad_nominal' => 'sometimes|numeric|min:0',
            'capacidad_operativa' => 'sometimes|numeric|min:0|lte:capacidad_nominal',
            'unidad_capacidad' => 'sometimes|in:LITROS,M3,BARRILES,GALONES',
            'nivel_minimo_operacion' => 'sometimes|numeric|min:0',
            'nivel_maximo_operacion' => 'sometimes|numeric|min:0|gt:nivel_minimo_operacion',
            'nivel_alarma_alto' => 'sometimes|numeric|min:0',
            'nivel_alarma_bajo' => 'sometimes|numeric|min:0',
            'producto_actual_id' => 'nullable|exists:productos,id',
            'fecha_fabricacion' => 'nullable|date',
            'fecha_instalacion' => 'nullable|date',
            'fecha_ultima_calibracion' => 'nullable|date',
            'fecha_proxima_calibracion' => 'nullable|date|after:fecha_ultima_calibracion',
            'numero_certificado_calibracion' => 'nullable|string|max:100',
            'material' => 'nullable|string|max:100',
            'espesor_pared' => 'nullable|numeric|min:0',
            'temperatura_operacion_min' => 'nullable|numeric',
            'temperatura_operacion_max' => 'nullable|numeric|gt:temperatura_operacion_min',
            'presion_operacion_max' => 'nullable|numeric|min:0',
            'configuracion_medicion' => 'nullable|array',
            'tabla_calibracion' => 'nullable|array',
            'observaciones' => 'nullable|string|max:1000',
            'estatus' => 'sometimes|in:OPERACION,MANTENIMIENTO,INACTIVO,FUERA_SERVICIO',
            'activo' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            // Validar consistencia de niveles si se actualizan
            if ($request->has('nivel_alarma_alto') || $request->has('nivel_maximo_operacion')) {
                $nivelAlarmaAlto = $request->nivel_alarma_alto ?? $tanque->nivel_alarma_alto;
                $nivelMaximo = $request->nivel_maximo_operacion ?? $tanque->nivel_maximo_operacion;
                
                if ($nivelAlarmaAlto > $nivelMaximo) {
                    return $this->sendError('El nivel de alarma alto no puede ser mayor al nivel máximo de operación', [], 422);
                }
            }

            if ($request->has('nivel_alarma_bajo') || $request->has('nivel_minimo_operacion')) {
                $nivelAlarmaBajo = $request->nivel_alarma_bajo ?? $tanque->nivel_alarma_bajo;
                $nivelMinimo = $request->nivel_minimo_operacion ?? $tanque->nivel_minimo_operacion;
                
                if ($nivelAlarmaBajo < $nivelMinimo) {
                    return $this->sendError('El nivel de alarma bajo no puede ser menor al nivel mínimo de operación', [], 422);
                }
            }

            $datosAnteriores = $tanque->toArray();
            $tanque->update($request->all());

            $this->logActivity(
                auth()->id(),
                'configuracion',
                'actualizacion_tanque',
                'tanques',
                "Tanque actualizado: {$tanque->codigo}",
                'tanques',
                $tanque->id,
                $datosAnteriores,
                $tanque->toArray()
            );

            DB::commit();

            return $this->sendResponse($tanque, 'Tanque actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al actualizar tanque', [$e->getMessage()], 500);
        }
    }

    /**
     * Eliminar tanque (soft delete)
     */
    public function destroy($id)
    {
        $tanque = Tanque::find($id);

        if (!$tanque) {
            return $this->sendError('Tanque no encontrado');
        }

        // Verificar si tiene registros volumétricos
        $tieneRegistros = $tanque->registrosVolumetricos()->exists();
        
        if ($tieneRegistros) {
            return $this->sendError('No se puede eliminar el tanque porque tiene registros volumétricos asociados', [], 409);
        }

        try {
            DB::beginTransaction();

            $tanque->activo = false;
            $tanque->estatus = 'INACTIVO';
            $tanque->save();
            $tanque->delete();

            $this->logActivity(
                auth()->id(),
                'configuracion',
                'eliminacion_tanque',
                'tanques',
                "Tanque eliminado: {$tanque->codigo}",
                'tanques',
                $tanque->id
            );

            DB::commit();

            return $this->sendResponse([], 'Tanque eliminado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al eliminar tanque', [$e->getMessage()], 500);
        }
    }

    /**
     * Obtener existencias del tanque
     */
    public function existencias(Request $request, $id)
    {
        $tanque = Tanque::find($id);

        if (!$tanque) {
            return $this->sendError('Tanque no encontrado');
        }

        $query = Existencia::where('tanque_id', $id)
            ->with('producto');

        if ($request->has('fecha_inicio')) {
            $query->where('fecha', '>=', Carbon::parse($request->fecha_inicio));
        }

        if ($request->has('fecha_fin')) {
            $query->where('fecha', '<=', Carbon::parse($request->fecha_fin));
        }

        if ($request->has('producto_id')) {
            $query->where('producto_id', $request->producto_id);
        }

        $existencias = $query->orderBy('fecha', 'desc')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($existencias, 'Existencias obtenidas exitosamente');
    }

    /**
     * Registrar calibración
     */
    public function registrarCalibracion(Request $request, $id)
    {
        $tanque = Tanque::find($id);

        if (!$tanque) {
            return $this->sendError('Tanque no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'fecha_calibracion' => 'required|date',
            'numero_certificado' => 'required|string|max:100',
            'laboratorio' => 'required|string|max:255',
            'tecnico_responsable' => 'required|string|max:255',
            'resultados' => 'required|array',
            'resultados.volumenes' => 'required|array',
            'resultados.desviaciones' => 'required|array',
            'nueva_tabla_calibracion' => 'required|array',
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
                    ->store("certificados/tanques/{$tanque->id}", 'public');
            }

            // Actualizar información del tanque
            $datosAnteriores = $tanque->toArray();
            
            $tanque->fecha_ultima_calibracion = $request->fecha_calibracion;
            $tanque->fecha_proxima_calibracion = Carbon::parse($request->fecha_calibracion)->addYear();
            $tanque->numero_certificado_calibracion = $request->numero_certificado;
            $tanque->tabla_calibracion = $request->nueva_tabla_calibracion;
            
            $metadata = $tanque->metadata ?? [];
            $metadata['calibraciones'][] = [
                'fecha' => $request->fecha_calibracion,
                'certificado' => $request->numero_certificado,
                'laboratorio' => $request->laboratorio,
                'tecnico' => $request->tecnico_responsable,
                'resultados' => $request->resultados,
                'archivo' => $rutaArchivo,
                'observaciones' => $request->observaciones,
                'registrado_por' => auth()->id(),
                'fecha_registro' => now()->toDateTimeString()
            ];
            $tanque->metadata = $metadata;
            
            $tanque->save();

            $this->logActivity(
                auth()->id(),
                'mantenimiento',
                'calibracion_tanque',
                'tanques',
                "Calibración registrada para tanque {$tanque->codigo} - Cert: {$request->numero_certificado}",
                'tanques',
                $tanque->id,
                $datosAnteriores,
                $tanque->toArray()
            );

            DB::commit();

            return $this->sendResponse($tanque, 'Calibración registrada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al registrar calibración', [$e->getMessage()], 500);
        }
    }

    /**
     * Obtener historial de calibraciones
     */
    public function historialCalibraciones($id)
    {
        $tanque = Tanque::find($id);

        if (!$tanque) {
            return $this->sendError('Tanque no encontrado');
        }

        $historial = collect($tanque->metadata['calibraciones'] ?? [])
            ->sortByDesc('fecha')
            ->values();

        return $this->sendResponse($historial, 'Historial de calibraciones obtenido exitosamente');
    }

    /**
     * Verificar estado del tanque
     */
    public function verificarEstado($id)
    {
        $tanque = Tanque::with('productoActual')->find($id);

        if (!$tanque) {
            return $this->sendError('Tanque no encontrado');
        }

        $nivelActual = $this->calcularNivelActual($tanque);
        $alertas = [];

        // Verificar niveles
        if ($nivelActual >= $tanque->nivel_alarma_alto) {
            $alertas[] = [
                'tipo' => 'NIVEL_ALTO',
                'severidad' => 'ALTA',
                'mensaje' => 'El tanque ha alcanzado el nivel de alarma alto',
                'valor_actual' => $nivelActual,
                'limite' => $tanque->nivel_alarma_alto
            ];
        }

        if ($nivelActual <= $tanque->nivel_alarma_bajo) {
            $alertas[] = [
                'tipo' => 'NIVEL_BAJO',
                'severidad' => 'ALTA',
                'mensaje' => 'El tanque ha alcanzado el nivel de alarma bajo',
                'valor_actual' => $nivelActual,
                'limite' => $tanque->nivel_alarma_bajo
            ];
        }

        // Verificar calibración
        if ($tanque->fecha_proxima_calibracion) {
            $diasParaCalibracion = now()->diffInDays($tanque->fecha_proxima_calibracion, false);
            
            if ($diasParaCalibracion <= 30) {
                $alertas[] = [
                    'tipo' => 'CALIBRACION_PROXIMA',
                    'severidad' => $diasParaCalibracion <= 7 ? 'ALTA' : 'MEDIA',
                    'mensaje' => "Próxima calibración en {$diasParaCalibracion} días",
                    'fecha_limite' => $tanque->fecha_proxima_calibracion->format('Y-m-d')
                ];
            }
        }

        // Verificar producto
        if (!$tanque->producto_actual_id) {
            $alertas[] = [
                'tipo' => 'SIN_PRODUCTO',
                'severidad' => 'MEDIA',
                'mensaje' => 'El tanque no tiene producto asignado'
            ];
        }

        $estado = [
            'tanque_id' => $tanque->id,
            'codigo' => $tanque->codigo,
            'estatus' => $tanque->estatus,
            'activo' => $tanque->activo,
            'nivel_actual' => $nivelActual,
            'porcentaje_ocupacion' => ($nivelActual / $tanque->capacidad_operativa) * 100,
            'producto_actual' => $tanque->productoActual ? [
                'id' => $tanque->productoActual->id,
                'nombre' => $tanque->productoActual->nombre,
                'clave_sat' => $tanque->productoActual->clave_sat
            ] : null,
            'alertas' => $alertas,
            'fecha_verificacion' => now()->toDateTimeString()
        ];

        return $this->sendResponse($estado, 'Estado del tanque verificado exitosamente');
    }

    /**
     * Cambiar producto del tanque
     */
    public function cambiarProducto(Request $request, $id)
    {
        $tanque = Tanque::find($id);

        if (!$tanque) {
            return $this->sendError('Tanque no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'producto_id' => 'required|exists:productos,id',
            'fecha_cambio' => 'required|date',
            'volumen_residual' => 'nullable|numeric|min:0',
            'requiere_limpieza' => 'boolean',
            'observaciones' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $productoAnterior = $tanque->productoActual;
            
            $datosAnteriores = $tanque->toArray();
            
            $tanque->producto_actual_id = $request->producto_id;
            
            $metadata = $tanque->metadata ?? [];
            $metadata['cambios_producto'][] = [
                'fecha' => $request->fecha_cambio,
                'producto_anterior_id' => $productoAnterior ? $productoAnterior->id : null,
                'producto_anterior_nombre' => $productoAnterior ? $productoAnterior->nombre : null,
                'producto_nuevo_id' => $request->producto_id,
                'volumen_residual' => $request->volumen_residual,
                'requiere_limpieza' => $request->boolean('requiere_limpieza', false),
                'observaciones' => $request->observaciones,
                'usuario_id' => auth()->id(),
                'fecha_registro' => now()->toDateTimeString()
            ];
            $tanque->metadata = $metadata;
            
            $tanque->save();

            $this->logActivity(
                auth()->id(),
                'operacion',
                'cambio_producto_tanque',
                'tanques',
                "Cambio de producto en tanque {$tanque->codigo}: " . 
                ($productoAnterior ? $productoAnterior->nombre : 'VACIO') . " -> " . 
                Tanque::find($request->producto_id)->nombre,
                'tanques',
                $tanque->id,
                $datosAnteriores,
                $tanque->toArray()
            );

            DB::commit();

            return $this->sendResponse($tanque, 'Producto del tanque actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al cambiar producto del tanque', [$e->getMessage()], 500);
        }
    }

    /**
     * Obtener curva de calibración
     */
    public function curvaCalibracion($id)
    {
        $tanque = Tanque::find($id);

        if (!$tanque) {
            return $this->sendError('Tanque no encontrado');
        }

        if (!$tanque->tabla_calibracion) {
            return $this->sendError('El tanque no tiene tabla de calibración', [], 404);
        }

        $curva = [
            'tanque_id' => $tanque->id,
            'codigo' => $tanque->codigo,
            'fecha_ultima_calibracion' => $tanque->fecha_ultima_calibracion,
            'certificado' => $tanque->numero_certificado_calibracion,
            'puntos_calibracion' => $tanque->tabla_calibracion,
            'grafico' => $this->generarDatosGraficoCalibracion($tanque->tabla_calibracion)
        ];

        return $this->sendResponse($curva, 'Curva de calibración obtenida exitosamente');
    }

    /**
     * Métodos privados
     */
    private function calcularNivelActual($tanque)
    {
        // Obtener última existencia registrada
        $ultimaExistencia = Existencia::where('tanque_id', $tanque->id)
            ->latest('fecha')
            ->first();

        if ($ultimaExistencia) {
            return $ultimaExistencia->volumen_final;
        }

        // Si no hay existencias, obtener del último registro volumétrico
        $ultimoRegistro = $tanque->registrosVolumetricos()
            ->latest('fecha_operacion')
            ->first();

        if ($ultimoRegistro) {
            return $ultimoRegistro->volumen_final;
        }

        return 0;
    }

    private function generarDatosGraficoCalibracion($tabla)
    {
        return [
            'labels' => array_column($tabla, 'nivel'),
            'datasets' => [
                [
                    'label' => 'Volumen vs Nivel',
                    'data' => array_column($tabla, 'volumen'),
                    'borderColor' => 'rgb(75, 192, 192)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'tension' => 0.1
                ]
            ]
        ];
    }
}