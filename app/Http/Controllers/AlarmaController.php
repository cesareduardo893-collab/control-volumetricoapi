<?php

namespace App\Http\Controllers;

use App\Models\Alarma;
use App\Models\User;
use App\Models\CatalogoValor;
use Illuminate\Http\Request;
use App\Models\Bitacora;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AlarmaController extends BaseController
{
    /**
     * Listar alarmas
     */
    public function index(Request $request)
    {
        $query = Alarma::with(['tipoAlarma', 'atendidaPor']);

        // Filtros
        if ($request->has('componente_tipo')) {
            $query->where('componente_tipo', $request->componente_tipo);
        }

        if ($request->has('componente_id')) {
            $query->where('componente_id', $request->componente_id);
        }

        if ($request->has('tipo_alarma_id')) {
            $query->where('tipo_alarma_id', $request->tipo_alarma_id);
        }

        if ($request->has('gravedad')) {
            $query->where('gravedad', $request->gravedad);
        }

        if ($request->has('atendida')) {
            $query->where('atendida', $request->boolean('atendida'));
        }

        if ($request->has('estado_atencion')) {
            $query->where('estado_atencion', $request->estado_atencion);
        }

        if ($request->has('requiere_atencion_inmediata')) {
            $query->where('requiere_atencion_inmediata', $request->boolean('requiere_atencion_inmediata'));
        }

        if ($request->has('fecha_inicio')) {
            $query->where('fecha_hora', '>=', Carbon::parse($request->fecha_inicio));
        }

        if ($request->has('fecha_fin')) {
            $query->where('fecha_hora', '<=', Carbon::parse($request->fecha_fin));
        }

        if ($request->has('numero_registro')) {
            $query->where('numero_registro', 'LIKE', "%{$request->numero_registro}%");
        }

        $alarmas = $query->orderBy('fecha_hora', 'desc')
            ->paginate($request->get('per_page', 15));

        return $this->success($alarmas, 'Alarmas obtenidas exitosamente');
    }

    /**
     * Crear alarma
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'numero_registro' => 'required|string|max:255|unique:alarmas,numero_registro',
            'fecha_hora' => 'required|date',
            'componente_tipo' => 'required|string|max:255',
            'componente_id' => 'nullable|integer',
            'componente_identificador' => 'required|string|max:255',
            'tipo_alarma_id' => 'required|exists:catalogo_valores,id',
            'gravedad' => 'required|in:BAJA,MEDIA,ALTA,CRITICA',
            'descripcion' => 'required|string',
            'datos_contexto' => 'nullable|array',
            'diferencia_detectada' => 'nullable|numeric',
            'porcentaje_diferencia' => 'nullable|numeric|min:0|max:100',
            'limite_permitido' => 'nullable|numeric',
            'diagnostico_automatico' => 'nullable|array',
            'recomendaciones' => 'nullable|array',
            'atendida' => 'boolean',
            'fecha_atencion' => 'nullable|date',
            'atendida_por' => 'nullable|exists:users,id',
            'acciones_tomadas' => 'nullable|string',
            'estado_atencion' => 'required|in:PENDIENTE,EN_PROCESO,RESUELTA,IGNORADA',
            'requiere_atencion_inmediata' => 'boolean',
            'fecha_limite_atencion' => 'nullable|date',
            'historial_cambios_estado' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $alarma = Alarma::create([
                'numero_registro' => $request->numero_registro,
                'fecha_hora' => $request->fecha_hora,
                'componente_tipo' => $request->componente_tipo,
                'componente_id' => $request->componente_id,
                'componente_identificador' => $request->componente_identificador,
                'tipo_alarma_id' => $request->tipo_alarma_id,
                'gravedad' => $request->gravedad,
                'descripcion' => $request->descripcion,
                'datos_contexto' => $request->datos_contexto,
                'diferencia_detectada' => $request->diferencia_detectada,
                'porcentaje_diferencia' => $request->porcentaje_diferencia,
                'limite_permitido' => $request->limite_permitido,
                'diagnostico_automatico' => $request->diagnostico_automatico,
                'recomendaciones' => $request->recomendaciones,
                'atendida' => $request->boolean('atendida', false),
                'fecha_atencion' => $request->fecha_atencion,
                'atendida_por' => $request->atendida_por,
                'acciones_tomadas' => $request->acciones_tomadas,
                'estado_atencion' => $request->estado_atencion,
                'requiere_atencion_inmediata' => $request->boolean('requiere_atencion_inmediata', false),
                'fecha_limite_atencion' => $request->fecha_limite_atencion,
                'historial_cambios_estado' => $request->historial_cambios_estado,
            ]);

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_SEGURIDAD,
                'CREACION_ALARMA',
                'Alarmas',
                "Alarma creada: {$alarma->numero_registro}",
                'alarmas',
                $alarma->id
            );

            DB::commit();

            return $this->success($alarma->load('tipoAlarma'), 'Alarma creada exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al crear alarma: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mostrar alarma
     */
    public function show($id)
    {
        $alarma = Alarma::with([
            'tipoAlarma',
            'atendidaPor'
        ])->find($id);

        if (!$alarma) {
            return $this->error('Alarma no encontrada', 404);
        }

        return $this->success($alarma, 'Alarma obtenida exitosamente');
    }

    /**
     * Atender alarma
     */
    public function atender(Request $request, $id)
    {
        $alarma = Alarma::find($id);

        if (!$alarma) {
            return $this->error('Alarma no encontrada', 404);
        }

        if ($alarma->atendida) {
            return $this->error('La alarma ya ha sido atendida', 403);
        }

        $validator = Validator::make($request->all(), [
            'acciones_tomadas' => 'required|string',
            'estado_atencion' => 'required|in:EN_PROCESO,RESUELTA,IGNORADA',
            'requiere_seguimiento' => 'boolean',
            'observaciones' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $alarma->toArray();

            $alarma->atendida = true;
            $alarma->fecha_atencion = now();
            $alarma->atendida_por = auth()->id();
            $alarma->acciones_tomadas = $request->acciones_tomadas;
            $alarma->estado_atencion = $request->estado_atencion;

            $historial = $alarma->historial_cambios_estado ?? [];
            $historial[] = [
                'fecha' => now()->toDateTimeString(),
                'usuario_id' => auth()->id(),
                'estado_anterior' => $datosAnteriores['estado_atencion'],
                'estado_nuevo' => $request->estado_atencion,
                'acciones' => $request->acciones_tomadas,
                'observaciones' => $request->observaciones,
            ];
            $alarma->historial_cambios_estado = $historial;

            $alarma->save();

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_SEGURIDAD,
                'ATENCION_ALARMA',
                'Alarmas',
                "Alarma atendida: {$alarma->numero_registro}",
                'alarmas',
                $alarma->id,
                $datosAnteriores,
                $alarma->toArray()
            );

            DB::commit();

            return $this->success($alarma, 'Alarma atendida exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al atender alarma: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar estado
     */
    public function actualizarEstado(Request $request, $id)
    {
        $alarma = Alarma::find($id);

        if (!$alarma) {
            return $this->error('Alarma no encontrada', 404);
        }

        $validator = Validator::make($request->all(), [
            'estado_atencion' => 'required|in:PENDIENTE,EN_PROCESO,RESUELTA,IGNORADA',
            'observaciones' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $alarma->toArray();

            $historial = $alarma->historial_cambios_estado ?? [];
            $historial[] = [
                'fecha' => now()->toDateTimeString(),
                'usuario_id' => auth()->id(),
                'estado_anterior' => $alarma->estado_atencion,
                'estado_nuevo' => $request->estado_atencion,
                'observaciones' => $request->observaciones,
            ];
            $alarma->historial_cambios_estado = $historial;
            $alarma->estado_atencion = $request->estado_atencion;

            if ($request->estado_atencion == 'RESUELTA' || $request->estado_atencion == 'IGNORADA') {
                $alarma->atendida = true;
                $alarma->fecha_atencion = now();
                $alarma->atendida_por = auth()->id();
            }

            $alarma->save();

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_SEGURIDAD,
                'CAMBIO_ESTADO_ALARMA',
                'Alarmas',
                "Estado de alarma actualizado: {$alarma->numero_registro} -> {$request->estado_atencion}",
                'alarmas',
                $alarma->id,
                $datosAnteriores,
                $alarma->toArray()
            );

            DB::commit();

            return $this->success($alarma, 'Estado de alarma actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al actualizar estado de alarma: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener estadísticas
     */
    public function estadisticas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'instalacion_id' => 'required|exists:instalaciones,id',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        $alarmas = Alarma::where('componente_tipo', 'instalacion')
            ->where('componente_id', $request->instalacion_id)
            ->whereBetween('fecha_hora', [
                Carbon::parse($request->fecha_inicio),
                Carbon::parse($request->fecha_fin)
            ])
            ->get();

        $estadisticas = [
            'instalacion_id' => $request->instalacion_id,
            'periodo' => [
                'inicio' => $request->fecha_inicio,
                'fin' => $request->fecha_fin,
            ],
            'resumen' => [
                'total_alarmas' => $alarmas->count(),
                'atendidas' => $alarmas->where('atendida', true)->count(),
                'pendientes' => $alarmas->where('atendida', false)->count(),
                'requieren_atencion' => $alarmas->where('requiere_atencion_inmediata', true)->count(),
            ],
            'por_gravedad' => $alarmas->groupBy('gravedad')
                ->map(function ($items) {
                    return [
                        'cantidad' => $items->count(),
                        'atendidas' => $items->where('atendida', true)->count(),
                        'pendientes' => $items->where('atendida', false)->count(),
                    ];
                }),
            'por_estado' => $alarmas->groupBy('estado_atencion')
                ->map(function ($items) {
                    return $items->count();
                }),
            'tendencia_diaria' => $alarmas->groupBy(function ($item) {
                    return Carbon::parse($item->fecha_hora)->format('Y-m-d');
                })
                ->map(function ($items, $fecha) {
                    return [
                        'fecha' => $fecha,
                        'total' => $items->count(),
                        'criticas' => $items->where('gravedad', 'CRITICA')->count(),
                    ];
                })->values(),
            'tiempo_promedio_respuesta' => $this->calcularTiempoPromedioRespuesta($alarmas),
        ];

        return $this->success($estadisticas, 'Estadísticas de alarmas obtenidas exitosamente');
    }

    /**
     * Obtener alarmas activas
     */
    public function activas(Request $request)
    {
        $query = Alarma::where('atendida', false)
            ->with(['tipoAlarma']);

        if ($request->has('componente_tipo')) {
            $query->where('componente_tipo', $request->componente_tipo);
        }

        if ($request->has('componente_id')) {
            $query->where('componente_id', $request->componente_id);
        }

        if ($request->has('gravedad')) {
            $query->where('gravedad', $request->gravedad);
        }

        $alarmas = $query->orderBy('fecha_hora', 'desc')
            ->orderBy('gravedad', 'desc')
            ->get();

        return $this->success([
            'total' => $alarmas->count(),
            'criticas' => $alarmas->where('gravedad', 'CRITICA')->count(),
            'altas' => $alarmas->where('gravedad', 'ALTA')->count(),
            'requieren_atencion' => $alarmas->where('requiere_atencion_inmediata', true)->count(),
            'alarmas' => $alarmas,
        ], 'Alarmas activas obtenidas exitosamente');
    }

    /**
     * Métodos privados
     */
    private function calcularTiempoPromedioRespuesta($alarmas)
    {
        $atendidas = $alarmas->filter(function ($a) {
            return $a->atendida && $a->fecha_atencion;
        });

        if ($atendidas->isEmpty()) {
            return null;
        }

        $tiempos = $atendidas->map(function ($a) {
            $inicio = Carbon::parse($a->fecha_hora);
            $fin = Carbon::parse($a->fecha_atencion);
            return $inicio->diffInMinutes($fin);
        });

        return [
            'promedio_minutos' => round($tiempos->avg(), 2),
            'minimo_minutos' => $tiempos->min(),
            'maximo_minutos' => $tiempos->max(),
        ];
    }

    /**
     * Mostrar formulario de edición (no implementado para API)
     */
    public function edit($id)
    {
        return $this->error('Este método no está disponible en la API', 405);
    }

    /**
     * Actualizar alarma
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'numero_registro' => 'sometimes|string|max:255',
            'fecha_hora' => 'sometimes|date',
            'componente_tipo' => 'sometimes|string|max:255',
            'componente_identificador' => 'sometimes|string|max:255',
            'tipo_alarma_id' => 'sometimes|integer',
            'gravedad' => 'sometimes|in:BAJA,MEDIA,ALTA,CRITICA',
            'descripcion' => 'sometimes|string',
            'estado_atencion' => 'sometimes|in:PENDIENTE,EN_PROCESO,RESUELTA,IGNORADA',
            'requiere_atencion_inmediata' => 'sometimes|boolean',
        ]);

        $alarma = Alarma::findOrFail($id);
        $alarma->update($request->all());

        return $this->success($alarma, 'Alarma actualizada exitosamente');
    }

    /**
     * Eliminar alarma
     */
    public function destroy($id)
    {
        $alarma = Alarma::findOrFail($id);
        $alarma->delete();

        return $this->success(null, 'Alarma eliminada exitosamente');
    }
}