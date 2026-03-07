<?php

namespace App\Http\Controllers;

use App\Models\Alarma;
use App\Models\Instalacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AlarmaController extends BaseController
{
    /**
     * Listar alarmas
     */
    public function index(Request $request)
    {
        $query = Alarma::with([
            'instalacion',
            'registroVolumetrico',
            'atendidaPor'
        ]);

        // Filtros
        if ($request->has('instalacion_id')) {
            $query->where('instalacion_id', $request->instalacion_id);
        }

        if ($request->has('tipo_alarma')) {
            $query->where('tipo_alarma', $request->tipo_alarma);
        }

        if ($request->has('gravedad')) {
            $query->where('gravedad', $request->gravedad);
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('fecha_inicio')) {
            $query->where('fecha_alarma', '>=', Carbon::parse($request->fecha_inicio));
        }

        if ($request->has('fecha_fin')) {
            $query->where('fecha_alarma', '<=', Carbon::parse($request->fecha_fin));
        }

        // Alarmas activas prioritarias
        if ($request->boolean('prioritarias')) {
            $query->where('estado', 'ACTIVA')
                ->orderByRaw("FIELD(gravedad, 'CRITICA', 'ALTA', 'MEDIA', 'BAJA')");
        }

        $alarmas = $query->orderBy('fecha_alarma', 'desc')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($alarmas, 'Alarmas obtenidas exitosamente');
    }

    /**
     * Mostrar alarma
     */
    public function show($id)
    {
        $alarma = Alarma::with([
            'instalacion',
            'registroVolumetrico',
            'atendidaPor',
            'historialCambios' => function($q) {
                $q->orderBy('created_at', 'desc');
            }
        ])->find($id);

        if (!$alarma) {
            return $this->sendError('Alarma no encontrada');
        }

        return $this->sendResponse($alarma, 'Alarma obtenida exitosamente');
    }

    /**
     * Marcar alarma como atendida
     */
    public function atender(Request $request, $id)
    {
        $alarma = Alarma::find($id);

        if (!$alarma) {
            return $this->sendError('Alarma no encontrada');
        }

        if ($alarma->estado != 'ACTIVA') {
            return $this->sendError('La alarma no está activa', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'accion_tomada' => 'required|string|max:1000',
            'observaciones' => 'nullable|string|max:500',
            'requiere_seguimiento' => 'boolean'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $alarma->toArray();

            $alarma->estado = 'ATENDIDA';
            $alarma->fecha_atencion = now();
            $alarma->atendida_por_id = auth()->id();
            $alarma->accion_tomada = $request->accion_tomada;
            
            $metadata = $alarma->metadata ?? [];
            $metadata['atencion'] = [
                'fecha' => now()->toDateTimeString(),
                'usuario_id' => auth()->id(),
                'usuario_nombre' => auth()->user()->name,
                'observaciones' => $request->observaciones,
                'requiere_seguimiento' => $request->boolean('requiere_seguimiento', false)
            ];
            $alarma->metadata = $metadata;
            
            $alarma->save();

            // Registrar en historial
            $this->registrarHistorial($alarma, 'ATENDIDA', $request->accion_tomada);

            $this->logActivity(
                auth()->id(),
                'gestion_alarmas',
                'atencion_alarma',
                'alarmas',
                "Alarma atendida ID: {$id} - Acción: {$request->accion_tomada}",
                'alarmas',
                $alarma->id,
                $datosAnteriores,
                $alarma->toArray()
            );

            DB::commit();

            return $this->sendResponse($alarma, 'Alarma marcada como atendida exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al atender alarma', [$e->getMessage()], 500);
        }
    }

    /**
     * Marcar alarma como en proceso
     */
    public function marcarEnProceso(Request $request, $id)
    {
        $alarma = Alarma::find($id);

        if (!$alarma) {
            return $this->sendError('Alarma no encontrada');
        }

        if (!in_array($alarma->estado, ['ACTIVA', 'EN_PROCESO'])) {
            return $this->sendError('La alarma no puede marcarse como en proceso', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'comentario' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $alarma->toArray();

            $alarma->estado = 'EN_PROCESO';
            
            $metadata = $alarma->metadata ?? [];
            $metadata['proceso'] = [
                'fecha_inicio' => now()->toDateTimeString(),
                'usuario_id' => auth()->id(),
                'comentario' => $request->comentario
            ];
            $alarma->metadata = $metadata;
            
            $alarma->save();

            // Registrar en historial
            $this->registrarHistorial($alarma, 'EN_PROCESO', $request->comentario ?? 'Inicio de proceso');

            $this->logActivity(
                auth()->id(),
                'gestion_alarmas',
                'proceso_alarma',
                'alarmas',
                "Alarma en proceso ID: {$id}",
                'alarmas',
                $alarma->id,
                $datosAnteriores,
                $alarma->toArray()
            );

            DB::commit();

            return $this->sendResponse($alarma, 'Alarma marcada como en proceso exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al marcar alarma en proceso', [$e->getMessage()], 500);
        }
    }

    /**
     * Cerrar alarma (solución definitiva)
     */
    public function cerrar(Request $request, $id)
    {
        $alarma = Alarma::find($id);

        if (!$alarma) {
            return $this->sendError('Alarma no encontrada');
        }

        if ($alarma->estado == 'CERRADA') {
            return $this->sendError('La alarma ya está cerrada', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'solucion_aplicada' => 'required|string|max:1000',
            'observaciones' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $alarma->toArray();

            $alarma->estado = 'CERRADA';
            $alarma->fecha_cierre = now();
            $alarma->solucion_aplicada = $request->solucion_aplicada;
            
            $metadata = $alarma->metadata ?? [];
            $metadata['cierre'] = [
                'fecha' => now()->toDateTimeString(),
                'usuario_id' => auth()->id(),
                'observaciones' => $request->observaciones
            ];
            $alarma->metadata = $metadata;
            
            $alarma->save();

            // Registrar en historial
            $this->registrarHistorial($alarma, 'CERRADA', $request->solucion_aplicada);

            $this->logActivity(
                auth()->id(),
                'gestion_alarmas',
                'cierre_alarma',
                'alarmas',
                "Alarma cerrada ID: {$id} - Solución: {$request->solucion_aplicada}",
                'alarmas',
                $alarma->id,
                $datosAnteriores,
                $alarma->toArray()
            );

            DB::commit();

            return $this->sendResponse($alarma, 'Alarma cerrada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al cerrar alarma', [$e->getMessage()], 500);
        }
    }

    /**
     * Escalar alarma (a nivel superior)
     */
    public function escalar(Request $request, $id)
    {
        $alarma = Alarma::find($id);

        if (!$alarma) {
            return $this->sendError('Alarma no encontrada');
        }

        $validator = Validator::make($request->all(), [
            'motivo_escalamiento' => 'required|string|max:500',
            'nivel_escalamiento' => 'required|in:SUPERVISOR,ADMINISTRADOR,EXTERNO'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $alarma->toArray();

            $metadata = $alarma->metadata ?? [];
            $metadata['escalamientos'][] = [
                'fecha' => now()->toDateTimeString(),
                'usuario_id' => auth()->id(),
                'nivel' => $request->nivel_escalamiento,
                'motivo' => $request->motivo_escalamiento
            ];
            $alarma->metadata = $metadata;
            
            $alarma->nivel_escalamiento = $request->nivel_escalamiento;
            $alarma->save();

            // Registrar en historial
            $this->registrarHistorial($alarma, 'ESCALADA', "Escalada a {$request->nivel_escalamiento}: {$request->motivo_escalamiento}");

            // Notificar a los responsables según el nivel
            $this->notificarEscalamiento($alarma, $request->nivel_escalamiento);

            $this->logActivity(
                auth()->id(),
                'gestion_alarmas',
                'escalamiento_alarma',
                'alarmas',
                "Alarma escalada ID: {$id} a nivel {$request->nivel_escalamiento}",
                'alarmas',
                $alarma->id,
                $datosAnteriores,
                $alarma->toArray()
            );

            DB::commit();

            return $this->sendResponse($alarma, 'Alarma escalada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al escalar alarma', [$e->getMessage()], 500);
        }
    }

    /**
     * Obtener estadísticas de alarmas
     */
    public function estadisticas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'instalacion_id' => 'nullable|exists:instalaciones,id',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        $query = Alarma::whereBetween('fecha_alarma', [
            Carbon::parse($request->fecha_inicio),
            Carbon::parse($request->fecha_fin)
        ]);

        if ($request->has('instalacion_id')) {
            $query->where('instalacion_id', $request->instalacion_id);
        }

        $alarmas = $query->get();

        $estadisticas = [
            'periodo' => [
                'inicio' => $request->fecha_inicio,
                'fin' => $request->fecha_fin
            ],
            'total_alarmas' => $alarmas->count(),
            'por_estado' => $alarmas->groupBy('estado')
                ->map(function ($items) {
                    return [
                        'cantidad' => $items->count(),
                        'porcentaje' => round(($items->count() / max($alarmas->count(), 1)) * 100, 2)
                    ];
                }),
            'por_gravedad' => $alarmas->groupBy('gravedad')
                ->map(function ($items) {
                    return [
                        'cantidad' => $items->count(),
                        'porcentaje' => round(($items->count() / max($alarmas->count(), 1)) * 100, 2)
                    ];
                }),
            'por_tipo' => $alarmas->groupBy('tipo_alarma')
                ->map(function ($items) {
                    return [
                        'cantidad' => $items->count(),
                        'porcentaje' => round(($items->count() / max($alarmas->count(), 1)) * 100, 2)
                    ];
                }),
            'tiempo_resolucion' => [
                'promedio_horas' => $alarmas->whereNotNull('fecha_atencion')
                    ->avg(function ($alarma) {
                        return $alarma->fecha_atencion->diffInHours($alarma->fecha_alarma);
                    }),
                'minimo_horas' => $alarmas->whereNotNull('fecha_atencion')
                    ->min(function ($alarma) {
                        return $alarma->fecha_atencion->diffInHours($alarma->fecha_alarma);
                    }),
                'maximo_horas' => $alarmas->whereNotNull('fecha_atencion')
                    ->max(function ($alarma) {
                        return $alarma->fecha_atencion->diffInHours($alarma->fecha_alarma);
                    })
            ]
        ];

        if ($request->has('instalacion_id')) {
            $instalacion = Instalacion::find($request->instalacion_id);
            $estadisticas['instalacion'] = [
                'id' => $instalacion->id,
                'nombre' => $instalacion->nombre,
                'clave' => $instalacion->clave_instalacion
            ];
        }

        return $this->sendResponse($estadisticas, 'Estadísticas de alarmas obtenidas exitosamente');
    }

    /**
     * Obtener histograma de alarmas
     */
    public function histograma(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'instalacion_id' => 'required|exists:instalaciones,id',
            'periodo' => 'required|in:DIARIO,SEMANAL,QUINCENAL,MENSUAL',
            'anio' => 'required|integer|min:2020|max:2100',
            'mes' => 'required_if:periodo,MENSUAL|integer|min:1|max:12'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        $query = Alarma::where('instalacion_id', $request->instalacion_id);

        switch ($request->periodo) {
            case 'DIARIO':
                $query->whereYear('fecha_alarma', $request->anio)
                    ->whereMonth('fecha_alarma', $request->mes);
                $agrupacion = 'DAY';
                break;
            case 'SEMANAL':
                $query->whereYear('fecha_alarma', $request->anio);
                $agrupacion = 'WEEK';
                break;
            case 'QUINCENAL':
                $query->whereYear('fecha_alarma', $request->anio);
                $agrupacion = 'FORTNIGHT';
                break;
            case 'MENSUAL':
                $query->whereYear('fecha_alarma', $request->anio);
                $agrupacion = 'MONTH';
                break;
        }

        $alarmas = $query->select(
            DB::raw("{$agrupacion}(fecha_alarma) as periodo"),
            DB::raw('count(*) as total'),
            DB::raw("SUM(CASE WHEN gravedad = 'CRITICA' THEN 1 ELSE 0 END) as criticas"),
            DB::raw("SUM(CASE WHEN gravedad = 'ALTA' THEN 1 ELSE 0 END) as altas"),
            DB::raw("SUM(CASE WHEN gravedad = 'MEDIA' THEN 1 ELSE 0 END) as medias"),
            DB::raw("SUM(CASE WHEN gravedad = 'BAJA' THEN 1 ELSE 0 END) as bajas")
        )->groupBy('periodo')
        ->orderBy('periodo')
        ->get();

        return $this->sendResponse($alarmas, 'Histograma de alarmas obtenido exitosamente');
    }

    /**
     * Métodos privados
     */
    private function registrarHistorial($alarma, $estado, $comentario)
    {
        $historial = $alarma->historialCambios ?? [];
        $historial[] = [
            'fecha' => now()->toDateTimeString(),
            'usuario_id' => auth()->id(),
            'usuario_nombre' => auth()->user()->name,
            'estado_anterior' => $alarma->getOriginal('estado'),
            'estado_nuevo' => $estado,
            'comentario' => $comentario
        ];
        
        $alarma->historialCambios = $historial;
        $alarma->saveQuietly();
    }

    private function notificarEscalamiento($alarma, $nivel)
    {
        // Implementar lógica de notificaciones según el nivel
        // Puede ser email, push notification, SMS, etc.
    }
}