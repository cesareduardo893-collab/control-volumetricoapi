<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Medidor;
use App\Models\Instalacion;
use App\Models\Tanque;
use App\Models\HistorialCalibracionMedidor;
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
        $query = Medidor::with(['instalacion', 'tanque'])->whereNull('deleted_at');

        // Filtros
        if ($request->has('instalacion_id')) {
            $query->where('instalacion_id', $request->instalacion_id);
        }

        if ($request->has('tanque_id')) {
            $query->where('tanque_id', $request->tanque_id);
        }

        if ($request->has('numero_serie')) {
            $query->where('numero_serie', 'LIKE', "%{$request->numero_serie}%");
        }

        if ($request->has('clave')) {
            $query->where('clave', 'LIKE', "%{$request->clave}%");
        }

        if ($request->has('elemento_tipo')) {
            $query->where('elemento_tipo', $request->elemento_tipo);
        }

        if ($request->has('tipo_medicion')) {
            $query->where('tipo_medicion', $request->tipo_medicion);
        }

        if ($request->has('tecnologia_id')) {
            $query->where('tecnologia_id', $request->tecnologia_id);
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('protocolo_comunicacion')) {
            $query->where('protocolo_comunicacion', $request->protocolo_comunicacion);
        }

        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        if ($request->has('calibracion_proxima')) {
            $query->where('fecha_proxima_calibracion', '<=', Carbon::parse($request->calibracion_proxima));
        }

        if ($request->boolean('alerta_alteracion')) {
            $query->where('alerta_alteracion', true);
        }

        $medidores = $query->orderBy('instalacion_id')
            ->orderBy('numero_serie')
            ->paginate($request->get('per_page', 15));

        return $this->success($medidores, 'Medidores obtenidos exitosamente');
    }

    /**
     * Crear medidor
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tanque_id' => 'nullable|exists:tanques,id',
            'instalacion_id' => 'required|exists:instalaciones,id',
            'numero_serie' => 'required|string|max:255|unique:medidores,numero_serie',
            'clave' => 'required|string|max:255|unique:medidores,clave',
            'modelo' => 'nullable|string|max:255',
            'fabricante' => 'nullable|string|max:255',
            'elemento_tipo' => 'required|in:primario,secundario,terciario',
            'tipo_medicion' => 'required|in:estatica,dinamica',
            'tecnologia_id' => 'nullable|exists:catalogo_valores,id',
            'precision' => 'required|numeric|min:0',
            'repetibilidad' => 'nullable|numeric|min:0',
            'capacidad_maxima' => 'required|numeric|min:0',
            'capacidad_minima' => 'nullable|numeric|min:0',
            'fecha_instalacion' => 'nullable|date',
            'ubicacion_fisica' => 'nullable|string|max:255',
            'fecha_ultima_calibracion' => 'nullable|date',
            'fecha_proxima_calibracion' => 'nullable|date|after:fecha_ultima_calibracion',
            'certificado_calibracion' => 'nullable|string|max:255',
            'laboratorio_calibracion' => 'nullable|string|max:255',
            'incertidumbre_calibracion' => 'nullable|numeric|min:0',
            'protocolo_comunicacion' => 'nullable|in:modbus,opc,serial,ethernet,wireless,otros',
            'direccion_ip' => 'nullable|ip',
            'puerto_comunicacion' => 'nullable|integer|min:1|max:65535',
            'parametros_conexion' => 'nullable|array',
            'mecanismos_seguridad' => 'nullable|array',
            'evidencias_alteracion' => 'nullable|array',
            'ultima_deteccion_alteracion' => 'nullable|date',
            'alerta_alteracion' => 'boolean',
            'historial_desconexiones' => 'nullable|array',
            'estado' => 'required|in:OPERATIVO,CALIBRACION,MANTENIMIENTO,FUERA_SERVICIO,FALLA_COMUNICACION',
            'observaciones' => 'nullable|string',
            'activo' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            // Validar instalación activa
            $instalacion = Instalacion::find($request->instalacion_id);
            if (!$instalacion || !$instalacion->activo) {
                return $this->error('La instalación no está activa', 422);
            }

            // Validar tanque si se especifica
            if ($request->has('tanque_id') && $request->tanque_id) {
                $tanque = Tanque::find($request->tanque_id);
                if (!$tanque) {
                    return $this->error('El tanque no existe', 422);
                }
                if ($tanque->instalacion_id != $request->instalacion_id) {
                    return $this->error('El tanque no pertenece a la instalación especificada', 422);
                }
            }

            $medidor = Medidor::create([
                'tanque_id' => $request->tanque_id,
                'instalacion_id' => $request->instalacion_id,
                'numero_serie' => $request->numero_serie,
                'clave' => $request->clave,
                'modelo' => $request->modelo,
                'fabricante' => $request->fabricante,
                'elemento_tipo' => $request->elemento_tipo,
                'tipo_medicion' => $request->tipo_medicion,
                'tecnologia_id' => $request->tecnologia_id,
                'precision' => $request->precision,
                'repetibilidad' => $request->repetibilidad,
                'capacidad_maxima' => $request->capacidad_maxima,
                'capacidad_minima' => $request->capacidad_minima,
                'fecha_instalacion' => $request->fecha_instalacion,
                'ubicacion_fisica' => $request->ubicacion_fisica,
                'fecha_ultima_calibracion' => $request->fecha_ultima_calibracion,
                'fecha_proxima_calibracion' => $request->fecha_proxima_calibracion,
                'certificado_calibracion' => $request->certificado_calibracion,
                'laboratorio_calibracion' => $request->laboratorio_calibracion,
                'incertidumbre_calibracion' => $request->incertidumbre_calibracion,
                'protocolo_comunicacion' => $request->protocolo_comunicacion,
                'direccion_ip' => $request->direccion_ip,
                'puerto_comunicacion' => $request->puerto_comunicacion,
                'parametros_conexion' => $request->parametros_conexion,
                'mecanismos_seguridad' => $request->mecanismos_seguridad,
                'evidencias_alteracion' => $request->evidencias_alteracion,
                'ultima_deteccion_alteracion' => $request->ultima_deteccion_alteracion,
                'alerta_alteracion' => $request->boolean('alerta_alteracion', false),
                'historial_desconexiones' => $request->historial_desconexiones,
                'estado' => $request->estado,
                'observaciones' => $request->observaciones,
                'activo' => $request->boolean('activo', true),
            ]);

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'CREACION_MEDIDOR',
                'Configuración',
                "Medidor creado: {$medidor->numero_serie} - {$medidor->clave}",
                'medidores',
                $medidor->id
            );

            DB::commit();

            return $this->success($medidor->load(['instalacion', 'tanque']), 'Medidor creado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al crear medidor: ' . $e->getMessage(), 500);
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
            'historialCalibracionesMedidor' => function($q) {
                $q->latest('fecha_calibracion')->limit(10);
            }
        ])->find($id);

        if (!$medidor) {
            return $this->error('Medidor no encontrado', 404);
        }

        return $this->success($medidor, 'Medidor obtenido exitosamente');
    }

    /**
     * Actualizar medidor
     */
    public function update(Request $request, $id)
    {
        $medidor = Medidor::find($id);

        if (!$medidor) {
            return $this->error('Medidor no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'tanque_id' => 'nullable|exists:tanques,id',
            'numero_serie' => "sometimes|string|max:255|unique:medidores,numero_serie,{$id}",
            'clave' => "sometimes|string|max:255|unique:medidores,clave,{$id}",
            'modelo' => 'nullable|string|max:255',
            'fabricante' => 'nullable|string|max:255',
            'elemento_tipo' => 'sometimes|in:primario,secundario,terciario',
            'tipo_medicion' => 'sometimes|in:estatica,dinamica',
            'tecnologia_id' => 'nullable|exists:catalogo_valores,id',
            'precision' => 'sometimes|numeric|min:0',
            'repetibilidad' => 'nullable|numeric|min:0',
            'capacidad_maxima' => 'sometimes|numeric|min:0',
            'capacidad_minima' => 'nullable|numeric|min:0',
            'fecha_instalacion' => 'nullable|date',
            'ubicacion_fisica' => 'nullable|string|max:255',
            'fecha_ultima_calibracion' => 'nullable|date',
            'fecha_proxima_calibracion' => 'nullable|date|after:fecha_ultima_calibracion',
            'certificado_calibracion' => 'nullable|string|max:255',
            'laboratorio_calibracion' => 'nullable|string|max:255',
            'incertidumbre_calibracion' => 'nullable|numeric|min:0',
            'protocolo_comunicacion' => 'nullable|in:modbus,opc,serial,ethernet,wireless,otros',
            'direccion_ip' => 'nullable|ip',
            'puerto_comunicacion' => 'nullable|integer|min:1|max:65535',
            'parametros_conexion' => 'nullable|array',
            'mecanismos_seguridad' => 'nullable|array',
            'alerta_alteracion' => 'sometimes|boolean',
            'estado' => 'sometimes|in:OPERATIVO,CALIBRACION,MANTENIMIENTO,FUERA_SERVICIO,FALLA_COMUNICACION',
            'observaciones' => 'nullable|string',
            'activo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            // Validar tanque si se especifica
            if ($request->has('tanque_id') && $request->tanque_id) {
                $tanque = Tanque::find($request->tanque_id);
                if (!$tanque) {
                    return $this->error('El tanque no existe', 422);
                }
                if ($tanque->instalacion_id != $medidor->instalacion_id) {
                    return $this->error('El tanque no pertenece a la instalación del medidor', 422);
                }
            }

            $datosAnteriores = $medidor->toArray();
            $medidor->update($request->all());

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'ACTUALIZACION_MEDIDOR',
                'Configuración',
                "Medidor actualizado: {$medidor->numero_serie}",
                'medidores',
                $medidor->id,
                $datosAnteriores,
                $medidor->toArray()
            );

            DB::commit();

            return $this->success($medidor, 'Medidor actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al actualizar medidor: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar medidor (soft delete)
     */
    public function destroy($id)
    {
        $medidor = Medidor::find($id);

        if (!$medidor) {
            return $this->error('Medidor no encontrado', 404);
        }

        try {
            DB::beginTransaction();

            $medidor->activo = false;
            $medidor->estado = 'FUERA_SERVICIO';
            $medidor->save();
            $medidor->delete();

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'ELIMINACION_MEDIDOR',
                'Configuración',
                "Medidor eliminado: {$medidor->numero_serie}",
                'medidores',
                $medidor->id
            );

            DB::commit();

            return $this->success([], 'Medidor eliminado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al eliminar medidor: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Registrar calibración
     */
    public function registrarCalibracion(Request $request, $id)
    {
        $medidor = Medidor::find($id);

        if (!$medidor) {
            return $this->error('Medidor no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'fecha_calibracion' => 'required|date',
            'fecha_proxima_calibracion' => 'required|date|after:fecha_calibracion',
            'certificado_calibracion' => 'required|string|max:255',
            'laboratorio_calibracion' => 'required|string|max:255',
            'incertidumbre_calibracion' => 'nullable|numeric|min:0',
            'precision' => 'required|numeric|min:0',
            'repetibilidad' => 'nullable|numeric|min:0',
            'observaciones' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $medidor->toArray();

            // Registrar en tabla historial_calibraciones_medidores
            HistorialCalibracionMedidor::create([
                'medidor_id' => $medidor->id,
                'fecha_calibracion' => $request->fecha_calibracion,
                'fecha_proxima_calibracion' => $request->fecha_proxima_calibracion,
                'certificado_calibracion' => $request->certificado_calibracion,
                'laboratorio_calibracion' => $request->laboratorio_calibracion,
                'incertidumbre_calibracion' => $request->incertidumbre_calibracion,
                'precision' => $request->precision,
                'repetibilidad' => $request->repetibilidad,
                'observaciones' => $request->observaciones,
                'usuario_id' => auth()->id(),
            ]);

            // Actualizar medidor
            $medidor->fecha_ultima_calibracion = $request->fecha_calibracion;
            $medidor->fecha_proxima_calibracion = $request->fecha_proxima_calibracion;
            $medidor->certificado_calibracion = $request->certificado_calibracion;
            $medidor->laboratorio_calibracion = $request->laboratorio_calibracion;
            $medidor->incertidumbre_calibracion = $request->incertidumbre_calibracion;
            $medidor->precision = $request->precision;
            $medidor->repetibilidad = $request->repetibilidad;
            $medidor->save();

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_OPERACIONES,
                'CALIBRACION_MEDIDOR',
                'Mantenimiento',
                "Calibración registrada para medidor {$medidor->numero_serie}",
                'medidores',
                $medidor->id,
                $datosAnteriores,
                $medidor->toArray()
            );

            DB::commit();

            return $this->success($medidor, 'Calibración registrada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al registrar calibración: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Probar comunicación
     */
    public function probarComunicacion($id)
    {
        $medidor = Medidor::find($id);

        if (!$medidor) {
            return $this->error('Medidor no encontrado', 404);
        }

        // Simular prueba de comunicación
        $exitosa = rand(1, 100) <= 90; // 90% de éxito
        $latencia = $exitosa ? rand(10, 500) : null;

        $resultado = [
            'exitosa' => $exitosa,
            'latencia_ms' => $latencia,
            'protocolo' => $medidor->protocolo_comunicacion,
            'direccion' => $medidor->direccion_ip ?? ($medidor->parametros_conexion['direccion'] ?? null),
            'timestamp' => now()->toDateTimeString(),
            'mensaje' => $exitosa ? 'Comunicación exitosa' : 'Error de comunicación',
        ];

        // Registrar intento
        $historial = $medidor->historial_desconexiones ?? [];
        $historial[] = [
            'fecha' => now()->toDateTimeString(),
            'tipo' => $exitosa ? 'PRUEBA_EXITOSA' : 'PRUEBA_FALLIDA',
            'resultado' => $resultado,
            'usuario_id' => auth()->id(),
        ];
        $medidor->historial_desconexiones = $historial;
        $medidor->save();

        return $this->success($resultado, 'Prueba de comunicación realizada');
    }

    /**
     * Verificar estado del medidor
     */
    public function verificarEstado($id)
    {
        $medidor = Medidor::find($id);

        if (!$medidor) {
            return $this->error('Medidor no encontrado', 404);
        }

        $hoy = Carbon::now();
        $alertas = [];

        // Verificar calibración
        if ($medidor->fecha_proxima_calibracion) {
            $diasRestantes = $hoy->diffInDays($medidor->fecha_proxima_calibracion, false);
            
            if ($diasRestantes <= 0) {
                $alertas[] = [
                    'tipo' => 'CALIBRACION_VENCIDA',
                    'severidad' => 'CRITICA',
                    'mensaje' => 'La calibración del medidor ha vencido',
                ];
            } elseif ($diasRestantes <= 30) {
                $alertas[] = [
                    'tipo' => 'CALIBRACION_PROXIMA',
                    'severidad' => 'MEDIA',
                    'mensaje' => "Próxima calibración en {$diasRestantes} días",
                ];
            }
        }

        // Verificar alerta de alteración
        if ($medidor->alerta_alteracion) {
            $alertas[] = [
                'tipo' => 'ALTERACION_DETECTADA',
                'severidad' => 'ALTA',
                'mensaje' => 'Se ha detectado una posible alteración en el medidor',
                'fecha' => $medidor->ultima_deteccion_alteracion,
            ];
        }

        // Verificar última conexión
        $ultimaDesconexion = collect($medidor->historial_desconexiones ?? [])
            ->where('tipo', 'DESCONEXION')
            ->sortByDesc('fecha')
            ->first();

        if ($ultimaDesconexion) {
            $diasSinConexion = $hoy->diffInDays(Carbon::parse($ultimaDesconexion['fecha']));
            if ($diasSinConexion > 7) {
                $alertas[] = [
                    'tipo' => 'SIN_COMUNICACION',
                    'severidad' => 'ALTA',
                    'mensaje' => "Sin comunicación desde hace {$diasSinConexion} días",
                ];
            }
        }

        $estado = [
            'medidor_id' => $medidor->id,
            'numero_serie' => $medidor->numero_serie,
            'clave' => $medidor->clave,
            'estado' => $medidor->estado,
            'activo' => $medidor->activo,
            'precision_actual' => $medidor->precision,
            'comunicacion' => [
                'protocolo' => $medidor->protocolo_comunicacion,
                'configurada' => !is_null($medidor->protocolo_comunicacion),
            ],
            'calibracion' => [
                'ultima' => $medidor->fecha_ultima_calibracion,
                'proxima' => $medidor->fecha_proxima_calibracion,
                'certificado' => $medidor->certificado_calibracion,
            ],
            'alertas' => $alertas,
            'fecha_verificacion' => $hoy->toDateTimeString(),
        ];

        return $this->success($estado, 'Estado del medidor verificado exitosamente');
    }

    /**
     * Obtener historial de calibraciones
     */
    public function historialCalibraciones($id)
    {
        $medidor = Medidor::find($id);

        if (!$medidor) {
            return $this->error('Medidor no encontrado', 404);
        }

        $historial = HistorialCalibracionMedidor::where('medidor_id', $id)
            ->orderBy('fecha_calibracion', 'desc')
            ->get();

        return $this->success([
            'medidor_id' => $medidor->id,
            'numero_serie' => $medidor->numero_serie,
            'historial' => $historial,
        ], 'Historial de calibraciones obtenido exitosamente');
    }
}