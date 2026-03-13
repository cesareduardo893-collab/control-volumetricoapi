<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Tanque;
use App\Models\Instalacion;
use App\Models\Producto;
use App\Models\Bitacora as BitacoraLog;
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
        $query = Tanque::with(['instalacion', 'producto'])->whereNull('deleted_at');

        // Filtros
        if ($request->has('instalacion_id')) {
            $query->where('instalacion_id', $request->instalacion_id);
        }

        if ($request->has('producto_id')) {
            $query->where('producto_id', $request->producto_id);
        }

        if ($request->has('identificador')) {
            $query->where('identificador', 'LIKE', "%{$request->identificador}%");
        }

        if ($request->has('numero_serie')) {
            $query->where('numero_serie', 'LIKE', "%{$request->numero_serie}%");
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('tipo_tanque_id')) {
            $query->where('tipo_tanque_id', $request->tipo_tanque_id);
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

        $tanques = $query->orderBy('instalacion_id')
            ->orderBy('identificador')
            ->paginate($request->get('per_page', 15));

        return $this->success($tanques, 'Tanques obtenidos exitosamente');
    }

    /**
     * Crear tanque
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'instalacion_id' => 'required|exists:instalaciones,id',
            'producto_id' => 'nullable|exists:productos,id',
            'numero_serie' => 'nullable|string|max:255',
            'identificador' => 'required|string|max:255|unique:tanques,identificador',
            'modelo' => 'nullable|string|max:255',
            'fabricante' => 'nullable|string|max:255',
            'material' => 'required|string|max:100',
            'capacidad_total' => 'required|numeric|min:0',
            'capacidad_util' => 'required|numeric|min:0|lte:capacidad_total',
            'capacidad_operativa' => 'required|numeric|min:0|lte:capacidad_util',
            'capacidad_minima' => 'required|numeric|min:0',
            'capacidad_gas_talon' => 'nullable|numeric|min:0',
            'fecha_fabricacion' => 'nullable|date',
            'fecha_instalacion' => 'nullable|date',
            'fecha_ultima_calibracion' => 'nullable|date',
            'fecha_proxima_calibracion' => 'nullable|date|after:fecha_ultima_calibracion',
            'certificado_calibracion' => 'nullable|string|max:255',
            'entidad_calibracion' => 'nullable|string|max:255',
            'incertidumbre_medicion' => 'nullable|numeric|min:0',
            'temperatura_referencia' => 'required|numeric',
            'presion_referencia' => 'required|numeric',
            'tipo_medicion' => 'required|in:estatica,dinamica',
            'estado' => 'required|in:OPERATIVO,MANTENIMIENTO,FUERA_SERVICIO,CALIBRACION',
            'tabla_aforo' => 'nullable|array',
            'curvas_calibracion' => 'nullable|array',
            'evidencias_alteracion' => 'nullable|array',
            'ultima_deteccion_alteracion' => 'nullable|date',
            'alerta_alteracion' => 'boolean',
            'tipo_tanque_id' => 'nullable|exists:catalogo_valores,id',
            'placas' => 'nullable|string|max:255',
            'numero_economico' => 'nullable|string|max:255',
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

            $tanque = Tanque::create([
                'instalacion_id' => $request->instalacion_id,
                'producto_id' => $request->producto_id,
                'numero_serie' => $request->numero_serie,
                'identificador' => $request->identificador,
                'modelo' => $request->modelo,
                'fabricante' => $request->fabricante,
                'material' => $request->material,
                'capacidad_total' => $request->capacidad_total,
                'capacidad_util' => $request->capacidad_util,
                'capacidad_operativa' => $request->capacidad_operativa,
                'capacidad_minima' => $request->capacidad_minima,
                'capacidad_gas_talon' => $request->capacidad_gas_talon,
                'fecha_fabricacion' => $request->fecha_fabricacion,
                'fecha_instalacion' => $request->fecha_instalacion,
                'fecha_ultima_calibracion' => $request->fecha_ultima_calibracion,
                'fecha_proxima_calibracion' => $request->fecha_proxima_calibracion,
                'certificado_calibracion' => $request->certificado_calibracion,
                'entidad_calibracion' => $request->entidad_calibracion,
                'incertidumbre_medicion' => $request->incertidumbre_medicion,
                'temperatura_referencia' => $request->temperatura_referencia,
                'presion_referencia' => $request->presion_referencia,
                'tipo_medicion' => $request->tipo_medicion,
                'estado' => $request->estado,
                'tabla_aforo' => $request->tabla_aforo,
                'curvas_calibracion' => $request->curvas_calibracion,
                'evidencias_alteracion' => $request->evidencias_alteracion,
                'ultima_deteccion_alteracion' => $request->ultima_deteccion_alteracion,
                'alerta_alteracion' => $request->boolean('alerta_alteracion', false),
                'tipo_tanque_id' => $request->tipo_tanque_id,
                'placas' => $request->placas,
                'numero_economico' => $request->numero_economico,
                'observaciones' => $request->observaciones,
                'activo' => $request->boolean('activo', true),
            ]);

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'CREACION_TANQUE',
                'Configuración',
                "Tanque creado: {$tanque->identificador}",
                'tanques',
                $tanque->id
            );

            DB::commit();

            return $this->success($tanque->load(['instalacion', 'producto']), 'Tanque creado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al crear tanque: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mostrar tanque
     */
    public function show($id)
    {
        $tanque = Tanque::with([
            'instalacion',
            'producto',
            'medidores' => function($q) {
                $q->where('activo', true);
            },
            'historialCalibraciones' => function($q) {
                $q->latest('fecha_calibracion')->limit(10);
            }
        ])->find($id);

        if (!$tanque) {
            return $this->error('Tanque no encontrado', 404);
        }

        return $this->success($tanque, 'Tanque obtenido exitosamente');
    }

    /**
     * Actualizar tanque
     */
    public function update(Request $request, $id)
    {
        $tanque = Tanque::find($id);

        if (!$tanque) {
            return $this->error('Tanque no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'producto_id' => 'nullable|exists:productos,id',
            'numero_serie' => 'nullable|string|max:255',
            'identificador' => "sometimes|string|max:255|unique:tanques,identificador,{$id}",
            'modelo' => 'nullable|string|max:255',
            'fabricante' => 'nullable|string|max:255',
            'material' => 'sometimes|string|max:100',
            'capacidad_total' => 'sometimes|numeric|min:0',
            'capacidad_util' => 'sometimes|numeric|min:0|lte:capacidad_total',
            'capacidad_operativa' => 'sometimes|numeric|min:0|lte:capacidad_util',
            'capacidad_minima' => 'sometimes|numeric|min:0',
            'capacidad_gas_talon' => 'nullable|numeric|min:0',
            'fecha_fabricacion' => 'nullable|date',
            'fecha_instalacion' => 'nullable|date',
            'fecha_ultima_calibracion' => 'nullable|date',
            'fecha_proxima_calibracion' => 'nullable|date|after:fecha_ultima_calibracion',
            'certificado_calibracion' => 'nullable|string|max:255',
            'entidad_calibracion' => 'nullable|string|max:255',
            'incertidumbre_medicion' => 'nullable|numeric|min:0',
            'temperatura_referencia' => 'sometimes|numeric',
            'presion_referencia' => 'sometimes|numeric',
            'tipo_medicion' => 'sometimes|in:estatica,dinamica',
            'estado' => 'sometimes|in:OPERATIVO,MANTENIMIENTO,FUERA_SERVICIO,CALIBRACION',
            'tabla_aforo' => 'nullable|array',
            'curvas_calibracion' => 'nullable|array',
            'alerta_alteracion' => 'sometimes|boolean',
            'tipo_tanque_id' => 'nullable|exists:catalogo_valores,id',
            'placas' => 'nullable|string|max:255',
            'numero_economico' => 'nullable|string|max:255',
            'observaciones' => 'nullable|string',
            'activo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $tanque->toArray();
            $tanque->update($request->all());

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'ACTUALIZACION_TANQUE',
                'Configuración',
                "Tanque actualizado: {$tanque->identificador}",
                'tanques',
                $tanque->id,
                $datosAnteriores,
                $tanque->toArray()
            );

            DB::commit();

            return $this->success($tanque, 'Tanque actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al actualizar tanque: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar tanque (soft delete)
     */
    public function destroy($id)
    {
        $tanque = Tanque::find($id);

        if (!$tanque) {
            return $this->error('Tanque no encontrado', 404);
        }

        // Verificar si tiene medidores asociados
        $medidoresActivos = $tanque->medidores()->where('activo', true)->count();
        if ($medidoresActivos > 0) {
            return $this->error("No se puede eliminar el tanque porque tiene {$medidoresActivos} medidores asociados", 409);
        }

        try {
            DB::beginTransaction();

            $tanque->activo = false;
            $tanque->estado = 'FUERA_SERVICIO';
            $tanque->save();
            $tanque->delete();

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'ELIMINACION_TANQUE',
                'Configuración',
                "Tanque eliminado: {$tanque->identificador}",
                'tanques',
                $tanque->id
            );

            DB::commit();

            return $this->success([], 'Tanque eliminado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al eliminar tanque: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Registrar calibración
     */
    public function registrarCalibracion(Request $request, $id)
    {
        $tanque = Tanque::find($id);

        if (!$tanque) {
            return $this->error('Tanque no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'fecha_calibracion' => 'required|date',
            'fecha_proxima_calibracion' => 'required|date|after:fecha_calibracion',
            'certificado_calibracion' => 'required|string|max:255',
            'entidad_calibracion' => 'required|string|max:255',
            'incertidumbre_medicion' => 'nullable|numeric|min:0',
            'tabla_aforo' => 'required|array',
            'curvas_calibracion' => 'nullable|array',
            'observaciones' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $tanque->toArray();

            // Registrar en tabla historial_calibraciones
            HistorialCalibracion::create([
                'tanque_id' => $tanque->id,
                'fecha_calibracion' => $request->fecha_calibracion,
                'fecha_proxima_calibracion' => $request->fecha_proxima_calibracion,
                'certificado_calibracion' => $request->certificado_calibracion,
                'entidad_calibracion' => $request->entidad_calibracion,
                'incertidumbre_medicion' => $request->incertidumbre_medicion,
                'tabla_aforo' => $request->tabla_aforo,
                'curvas_calibracion' => $request->curvas_calibracion,
                'observaciones' => $request->observaciones,
                'usuario_id' => auth()->id(),
            ]);

            // Actualizar tanque
            $tanque->fecha_ultima_calibracion = $request->fecha_calibracion;
            $tanque->fecha_proxima_calibracion = $request->fecha_proxima_calibracion;
            $tanque->certificado_calibracion = $request->certificado_calibracion;
            $tanque->entidad_calibracion = $request->entidad_calibracion;
            $tanque->incertidumbre_medicion = $request->incertidumbre_medicion;
            $tanque->tabla_aforo = $request->tabla_aforo;
            $tanque->curvas_calibracion = $request->curvas_calibracion;
            $tanque->save();

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_OPERACIONES,
                'CALIBRACION_TANQUE',
                'Mantenimiento',
                "Calibración registrada para tanque {$tanque->identificador}",
                'tanques',
                $tanque->id,
                $datosAnteriores,
                $tanque->toArray()
            );

            DB::commit();

            return $this->success($tanque, 'Calibración registrada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al registrar calibración: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Verificar estado del tanque
     */
    public function verificarEstado($id)
    {
        $tanque = Tanque::with('producto')->find($id);

        if (!$tanque) {
            return $this->error('Tanque no encontrado', 404);
        }

        $hoy = Carbon::now();
        $alertas = [];

        // Verificar calibración
        if ($tanque->fecha_proxima_calibracion) {
            $diasRestantes = $hoy->diffInDays($tanque->fecha_proxima_calibracion, false);
            
            if ($diasRestantes <= 0) {
                $alertas[] = [
                    'tipo' => 'CALIBRACION_VENCIDA',
                    'severidad' => 'CRITICA',
                    'mensaje' => 'La calibración del tanque ha vencido',
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
        if ($tanque->alerta_alteracion) {
            $alertas[] = [
                'tipo' => 'ALTERACION_DETECTADA',
                'severidad' => 'ALTA',
                'mensaje' => 'Se ha detectado una posible alteración en el tanque',
                'fecha' => $tanque->ultima_deteccion_alteracion,
            ];
        }

        // Obtener última existencia
        $ultimaExistencia = DB::table('existencias')
            ->where('tanque_id', $id)
            ->orderBy('fecha', 'desc')
            ->orderBy('hora', 'desc')
            ->first();

        $estado = [
            'tanque_id' => $tanque->id,
            'identificador' => $tanque->identificador,
            'estado' => $tanque->estado,
            'activo' => $tanque->activo,
            'producto' => $tanque->producto ? $tanque->producto->nombre : null,
            'capacidad_operativa' => $tanque->capacidad_operativa,
            'ultimo_volumen' => $ultimaExistencia ? $ultimaExistencia->volumen_corregido : null,
            'fecha_ultima_lectura' => $ultimaExistencia ? $ultimaExistencia->fecha : null,
            'calibracion' => [
                'ultima' => $tanque->fecha_ultima_calibracion,
                'proxima' => $tanque->fecha_proxima_calibracion,
                'certificado' => $tanque->certificado_calibracion,
            ],
            'alertas' => $alertas,
            'fecha_verificacion' => $hoy->toDateTimeString(),
        ];

        return $this->success($estado, 'Estado del tanque verificado exitosamente');
    }

    /**
     * Cambiar producto del tanque
     */
    public function cambiarProducto(Request $request, $id)
    {
        $tanque = Tanque::find($id);

        if (!$tanque) {
            return $this->error('Tanque no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'producto_id' => 'required|exists:productos,id',
            'motivo' => 'required|string|max:500',
            'fecha_cambio' => 'required|date',
            'observaciones' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $productoAnterior = $tanque->producto;
            $datosAnteriores = $tanque->toArray();

            $tanque->producto_id = $request->producto_id;
            
            $evidencias = $tanque->evidencias_alteracion ?? [];
            $evidencias[] = [
                'tipo' => 'CAMBIO_PRODUCTO',
                'fecha' => $request->fecha_cambio,
                'producto_anterior_id' => $productoAnterior ? $productoAnterior->id : null,
                'producto_anterior_nombre' => $productoAnterior ? $productoAnterior->nombre : null,
                'producto_nuevo_id' => $request->producto_id,
                'motivo' => $request->motivo,
                'observaciones' => $request->observaciones,
                'usuario_id' => auth()->id(),
            ];
            $tanque->evidencias_alteracion = $evidencias;
            
            $tanque->save();

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_OPERACIONES,
                'CAMBIO_PRODUCTO_TANQUE',
                'Operación',
                "Cambio de producto en tanque {$tanque->identificador}",
                'tanques',
                $tanque->id,
                $datosAnteriores,
                $tanque->toArray()
            );

            DB::commit();

            return $this->success($tanque->load('producto'), 'Producto del tanque actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al cambiar producto: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener curva de calibración
     */
    public function curvaCalibracion($id)
    {
        $tanque = Tanque::find($id);

        if (!$tanque) {
            return $this->error('Tanque no encontrado', 404);
        }

        if (!$tanque->tabla_aforo) {
            return $this->error('El tanque no tiene tabla de aforo', 404);
        }

        return $this->success([
            'tanque_id' => $tanque->id,
            'identificador' => $tanque->identificador,
            'fecha_ultima_calibracion' => $tanque->fecha_ultima_calibracion,
            'certificado' => $tanque->certificado_calibracion,
            'tabla_aforo' => $tanque->tabla_aforo,
            'curvas_calibracion' => $tanque->curvas_calibracion,
        ], 'Curva de calibración obtenida exitosamente');
    }

    /**
     * Obtener historial de calibraciones
     */
    public function historialCalibraciones($id)
    {
        $tanque = Tanque::find($id);

        if (!$tanque) {
            return $this->error('Tanque no encontrado', 404);
        }

        $historial = HistorialCalibracion::where('tanque_id', $id)
            ->orderBy('fecha_calibracion', 'desc')
            ->get();

        return $this->success([
            'tanque_id' => $tanque->id,
            'identificador' => $tanque->identificador,
            'historial' => $historial,
        ], 'Historial de calibraciones obtenido exitosamente');
    }
}