<?php

namespace App\Http\Controllers;

use App\Models\Existencia;
use App\Models\Tanque;
use App\Models\Producto;
use App\Models\Instalacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ExistenciaController extends BaseController
{
    /**
     * Listar existencias
     */
    public function index(Request $request)
    {
        $query = Existencia::with([
            'tanque.instalacion',
            'producto',
            'usuarioRegistro',
            'usuarioValida'
        ]);

        // Filtros
        if ($request->has('instalacion_id')) {
            $query->whereHas('tanque', function($q) use ($request) {
                $q->where('instalacion_id', $request->instalacion_id);
            });
        }

        if ($request->has('tanque_id')) {
            $query->where('tanque_id', $request->tanque_id);
        }

        if ($request->has('producto_id')) {
            $query->where('producto_id', $request->producto_id);
        }

        if ($request->has('fecha_inicio')) {
            $query->where('fecha', '>=', Carbon::parse($request->fecha_inicio));
        }

        if ($request->has('fecha_fin')) {
            $query->where('fecha', '<=', Carbon::parse($request->fecha_fin));
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->boolean('con_diferencias')) {
            $query->whereRaw('ABS(diferencia_volumen) > tolerancia_maxima');
        }

        if ($request->has('volumen_min')) {
            $query->where('volumen_final', '>=', $request->volumen_min);
        }

        if ($request->has('volumen_max')) {
            $query->where('volumen_final', '<=', $request->volumen_max);
        }

        $existencias = $query->orderBy('fecha', 'desc')
            ->orderBy('tanque_id')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($existencias, 'Existencias obtenidas exitosamente');
    }

    /**
     * Crear existencia (cierre diario)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tanque_id' => 'required|exists:tanques,id',
            'producto_id' => 'required|exists:productos,id',
            'fecha' => 'required|date|before_or_equal:today',
            'volumen_inicial' => 'required|numeric|min:0',
            'volumen_final' => 'required|numeric|min:0',
            'volumen_recibido' => 'nullable|numeric|min:0',
            'volumen_entregado' => 'nullable|numeric|min:0',
            'volumen_medido' => 'nullable|numeric|min:0',
            'temperatura_promedio' => 'nullable|numeric',
            'densidad_promedio' => 'nullable|numeric|min:0',
            'nivel_tanque' => 'nullable|numeric|min:0',
            'observaciones' => 'nullable|string|max:500',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            // Verificar si ya existe registro para esta fecha y tanque
            $existente = Existencia::where('tanque_id', $request->tanque_id)
                ->where('fecha', $request->fecha)
                ->first();

            if ($existente) {
                return $this->sendError('Ya existe un registro de existencia para esta fecha y tanque', [
                    'existencia_id' => $existente->id
                ], 409);
            }

            $tanque = Tanque::find($request->tanque_id);

            // Validar que el volumen final no exceda la capacidad
            if ($request->volumen_final > $tanque->capacidad_operativa) {
                return $this->sendError('El volumen final excede la capacidad operativa del tanque', [], 422);
            }

            // Calcular diferencias
            $volumenRecibido = $request->volumen_recibido ?? 0;
            $volumenEntregado = $request->volumen_entregado ?? 0;
            
            $volumenEsperado = $request->volumen_inicial + $volumenRecibido - $volumenEntregado;
            $diferenciaVolumen = $request->volumen_final - $volumenEsperado;
            
            // Calcular tolerancia (0.5% por defecto)
            $tolerancia = $tanque->tolerancia_diaria ?? 0.5;
            $porcentajeDiferencia = $volumenEsperado > 0 
                ? abs(($diferenciaVolumen / $volumenEsperado) * 100) 
                : 0;

            $estado = 'PENDIENTE';
            if (abs($porcentajeDiferencia) <= $tolerancia) {
                $estado = 'CONFORME';
            } elseif (abs($porcentajeDiferencia) > $tolerancia * 2) {
                $estado = 'CRITICO';
            } else {
                $estado = 'OBSERVADO';
            }

            $existencia = Existencia::create([
                'tanque_id' => $request->tanque_id,
                'producto_id' => $request->producto_id,
                'fecha' => $request->fecha,
                'volumen_inicial' => $request->volumen_inicial,
                'volumen_final' => $request->volumen_final,
                'volumen_recibido' => $volumenRecibido,
                'volumen_entregado' => $volumenEntregado,
                'volumen_esperado' => $volumenEsperado,
                'diferencia_volumen' => $diferenciaVolumen,
                'porcentaje_diferencia' => $porcentajeDiferencia,
                'tolerancia_maxima' => $tolerancia,
                'volumen_medido' => $request->volumen_medido,
                'temperatura_promedio' => $request->temperatura_promedio,
                'densidad_promedio' => $request->densidad_promedio,
                'nivel_tanque' => $request->nivel_tanque,
                'estado' => $estado,
                'observaciones' => $request->observaciones,
                'usuario_registro_id' => auth()->id(),
                'metadata' => $request->metadata
            ]);

            // Generar alarma si la diferencia es significativa
            if ($estado == 'CRITICO') {
                $this->generarAlarmaPorDiferencia($existencia);
            }

            $this->logActivity(
                auth()->id(),
                'inventarios',
                'registro_existencia',
                'existencias',
                "Existencia registrada para tanque {$tanque->codigo} fecha {$request->fecha} - Diferencia: {$porcentajeDiferencia}%",
                'existencias',
                $existencia->id
            );

            DB::commit();

            return $this->sendResponse($existencia->load(['tanque', 'producto']), 
                'Existencia registrada exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al registrar existencia', [$e->getMessage()], 500);
        }
    }

    /**
     * Mostrar existencia
     */
    public function show($id)
    {
        $existencia = Existencia::with([
            'tanque.instalacion',
            'producto',
            'usuarioRegistro',
            'usuarioValida',
            'alarmas'
        ])->find($id);

        if (!$existencia) {
            return $this->sendError('Existencia no encontrada');
        }

        return $this->sendResponse($existencia, 'Existencia obtenida exitosamente');
    }

    /**
     * Validar existencia
     */
    public function validar(Request $request, $id)
    {
        $existencia = Existencia::find($id);

        if (!$existencia) {
            return $this->sendError('Existencia no encontrada');
        }

        if ($existencia->estado == 'VALIDADO') {
            return $this->sendError('La existencia ya está validada', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'observaciones_validacion' => 'nullable|string|max:500',
            'acciones_correctivas' => 'nullable|string|max:1000',
            'nuevo_estado' => 'sometimes|in:CONFORME,OBSERVADO,CRITICO'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $existencia->toArray();

            $existencia->estado = $request->nuevo_estado ?? $existencia->estado;
            $existencia->fecha_validacion = now();
            $existencia->usuario_valida_id = auth()->id();
            
            $metadata = $existencia->metadata ?? [];
            $metadata['validacion'] = [
                'fecha' => now()->toDateTimeString(),
                'usuario_id' => auth()->id(),
                'observaciones' => $request->observaciones_validacion,
                'acciones_correctivas' => $request->acciones_correctivas
            ];
            $existencia->metadata = $metadata;
            
            $existencia->save();

            $this->logActivity(
                auth()->id(),
                'inventarios',
                'validacion_existencia',
                'existencias',
                "Existencia validada ID: {$id} - Estado: {$existencia->estado}",
                'existencias',
                $existencia->id,
                $datosAnteriores,
                $existencia->toArray()
            );

            DB::commit();

            return $this->sendResponse($existencia, 'Existencia validada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al validar existencia', [$e->getMessage()], 500);
        }
    }

    /**
     * Ajustar existencia (corrección manual)
     */
    public function ajustar(Request $request, $id)
    {
        $existencia = Existencia::find($id);

        if (!$existencia) {
            return $this->sendError('Existencia no encontrada');
        }

        if ($existencia->estado == 'VALIDADO') {
            return $this->sendError('No se puede ajustar una existencia validada', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'volumen_final_ajustado' => 'required|numeric|min:0',
            'motivo_ajuste' => 'required|string|max:500',
            'autorizacion' => 'required|string|max:100',
            'observaciones' => 'nullable|string|max:500',
            'archivo_soporte' => 'nullable|file|mimes:pdf,jpg,png|max:5120'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            // Guardar archivo de soporte
            $rutaArchivo = null;
            if ($request->hasFile('archivo_soporte')) {
                $rutaArchivo = $request->file('archivo_soporte')
                    ->store("ajustes/existencias/{$existencia->tanque_id}", 'public');
            }

            $datosAnteriores = $existencia->toArray();

            $diferenciaAjuste = $request->volumen_final_ajustado - $existencia->volumen_final;

            $metadata = $existencia->metadata ?? [];
            $metadata['ajustes'][] = [
                'fecha' => now()->toDateTimeString(),
                'usuario_id' => auth()->id(),
                'volumen_anterior' => $existencia->volumen_final,
                'volumen_nuevo' => $request->volumen_final_ajustado,
                'diferencia' => $diferenciaAjuste,
                'motivo' => $request->motivo_ajuste,
                'autorizacion' => $request->autorizacion,
                'observaciones' => $request->observaciones,
                'archivo' => $rutaArchivo
            ];
            $existencia->metadata = $metadata;
            
            $existencia->volumen_final = $request->volumen_final_ajustado;
            $existencia->diferencia_volumen = $existencia->volumen_final - $existencia->volumen_esperado;
            $existencia->porcentaje_diferencia = $existencia->volumen_esperado > 0 
                ? abs(($existencia->diferencia_volumen / $existencia->volumen_esperado) * 100) 
                : 0;
            $existencia->estado = 'AJUSTADO';
            
            $existencia->save();

            $this->logActivity(
                auth()->id(),
                'inventarios',
                'ajuste_existencia',
                'existencias',
                "Ajuste de existencia ID: {$id} - Diferencia: {$diferenciaAjuste}",
                'existencias',
                $existencia->id,
                $datosAnteriores,
                $existencia->toArray()
            );

            DB::commit();

            return $this->sendResponse($existencia, 'Existencia ajustada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al ajustar existencia', [$e->getMessage()], 500);
        }
    }

    /**
     * Obtener inventario actual
     */
    public function inventarioActual(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'instalacion_id' => 'required|exists:instalaciones,id',
            'fecha' => 'required|date'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        $fecha = Carbon::parse($request->fecha);
        
        $tanques = Tanque::where('instalacion_id', $request->instalacion_id)
            ->where('activo', true)
            ->get();

        $inventario = [];

        foreach ($tanques as $tanque) {
            // Obtener última existencia antes de la fecha
            $existencia = Existencia::where('tanque_id', $tanque->id)
                ->where('fecha', '<=', $fecha)
                ->orderBy('fecha', 'desc')
                ->first();

            $inventario[] = [
                'tanque' => [
                    'id' => $tanque->id,
                    'codigo' => $tanque->codigo,
                    'tipo' => $tanque->tipo,
                    'capacidad' => $tanque->capacidad_operativa
                ],
                'producto' => $existencia && $existencia->producto ? [
                    'id' => $existencia->producto->id,
                    'nombre' => $existencia->producto->nombre,
                    'clave_sat' => $existencia->producto->clave_sat
                ] : null,
                'volumen' => $existencia ? $existencia->volumen_final : 0,
                'porcentaje_ocupacion' => $tanque->capacidad_operativa > 0 
                    ? ($existencia ? ($existencia->volumen_final / $tanque->capacidad_operativa) * 100 : 0)
                    : 0,
                'ultima_actualizacion' => $existencia ? $existencia->fecha : null,
                'estado' => $existencia ? $existencia->estado : 'SIN_REGISTRO'
            ];
        }

        // Calcular totales por producto
        $resumen = [
            'fecha' => $fecha->format('Y-m-d'),
            'instalacion_id' => $request->instalacion_id,
            'total_tanques' => count($inventario),
            'tanques_con_producto' => collect($inventario)->whereNotNull('producto')->count(),
            'volumen_total' => collect($inventario)->sum('volumen'),
            'por_producto' => collect($inventario)
                ->groupBy('producto.nombre')
                ->map(function ($items, $producto) {
                    return [
                        'producto' => $producto ?: 'SIN PRODUCTO',
                        'tanques' => $items->count(),
                        'volumen' => $items->sum('volumen'),
                        'porcentaje' => $items->sum('volumen') / max(collect($inventario)->sum('volumen'), 1) * 100
                    ];
                })->values(),
            'detalle_tanques' => $inventario
        ];

        return $this->sendResponse($resumen, 'Inventario actual obtenido exitosamente');
    }

    /**
     * Obtener histórico de existencias
     */
    public function historico(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tanque_id' => 'required|exists:tanques,id',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        $existencias = Existencia::where('tanque_id', $request->tanque_id)
            ->whereBetween('fecha', [
                Carbon::parse($request->fecha_inicio),
                Carbon::parse($request->fecha_fin)
            ])
            ->orderBy('fecha')
            ->get();

        $tanque = Tanque::find($request->tanque_id);

        $historico = [
            'tanque' => [
                'id' => $tanque->id,
                'codigo' => $tanque->codigo,
                'capacidad' => $tanque->capacidad_operativa
            ],
            'periodo' => [
                'inicio' => $request->fecha_inicio,
                'fin' => $request->fecha_fin
            ],
            'resumen' => [
                'total_registros' => $existencias->count(),
                'volumen_promedio' => $existencias->avg('volumen_final'),
                'volumen_minimo' => $existencias->min('volumen_final'),
                'volumen_maximo' => $existencias->max('volumen_final'),
                'volumen_inicial' => $existencias->first()?->volumen_final,
                'volumen_final' => $existencias->last()?->volumen_final,
                'variacion_periodo' => $existencias->count() > 1 
                    ? $existencias->last()->volumen_final - $existencias->first()->volumen_final 
                    : 0,
                'dias_con_diferencia' => $existencias->where('estado', 'CRITICO')->count(),
                'dias_con_observaciones' => $existencias->where('estado', 'OBSERVADO')->count()
            ],
            'datos_diarios' => $existencias->map(function ($e) {
                return [
                    'fecha' => $e->fecha->format('Y-m-d'),
                    'volumen_inicial' => $e->volumen_inicial,
                    'volumen_final' => $e->volumen_final,
                    'recibido' => $e->volumen_recibido,
                    'entregado' => $e->volumen_entregado,
                    'diferencia' => $e->diferencia_volumen,
                    'porcentaje_diferencia' => $e->porcentaje_diferencia,
                    'estado' => $e->estado,
                    'temperatura' => $e->temperatura_promedio,
                    'densidad' => $e->densidad_promedio
                ];
            }),
            'grafico' => [
                'fechas' => $existencias->pluck('fecha')->map(function ($f) {
                    return $f->format('Y-m-d');
                }),
                'volumenes' => $existencias->pluck('volumen_final'),
                'linea_capacidad' => array_fill(0, $existencias->count(), $tanque->capacidad_operativa)
            ]
        ];

        return $this->sendResponse($historico, 'Histórico de existencias obtenido exitosamente');
    }

    /**
     * Conciliar existencias (cierre de mes)
     */
    public function conciliarMensual(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'instalacion_id' => 'required|exists:instalaciones,id',
            'anio' => 'required|integer|min:2020|max:2100',
            'mes' => 'required|integer|min:1|max:12'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $fechaInicio = Carbon::createFromDate($request->anio, $request->mes, 1)->startOfMonth();
            $fechaFin = $fechaInicio->copy()->endOfMonth();

            $tanques = Tanque::where('instalacion_id', $request->instalacion_id)
                ->where('activo', true)
                ->get();

            $resultados = [];

            foreach ($tanques as $tanque) {
                $existencias = Existencia::where('tanque_id', $tanque->id)
                    ->whereBetween('fecha', [$fechaInicio, $fechaFin])
                    ->orderBy('fecha')
                    ->get();

                if ($existencias->isEmpty()) {
                    continue;
                }

                $primera = $existencias->first();
                $ultima = $existencias->last();

                $resultado = [
                    'tanque_id' => $tanque->id,
                    'tanque_codigo' => $tanque->codigo,
                    'producto' => $primera->producto ? $primera->producto->nombre : 'N/A',
                    'inventario_inicial' => $primera->volumen_inicial,
                    'inventario_final' => $ultima->volumen_final,
                    'total_recibido' => $existencias->sum('volumen_recibido'),
                    'total_entregado' => $existencias->sum('volumen_entregado'),
                    'balance_esperado' => $primera->volumen_inicial + 
                        $existencias->sum('volumen_recibido') - 
                        $existencias->sum('volumen_entregado'),
                    'balance_real' => $ultima->volumen_final,
                    'diferencia_acumulada' => $ultima->volumen_final - (
                        $primera->volumen_inicial + 
                        $existencias->sum('volumen_recibido') - 
                        $existencias->sum('volumen_entregado')
                    ),
                    'dias_con_diferencia' => $existencias->where('estado', 'CRITICO')->count(),
                    'porcentaje_conciliacion' => $this->calcularPorcentajeConciliacion($existencias),
                    'estado' => $this->determinarEstadoConciliacion($existencias)
                ];

                $resultados[] = $resultado;

                // Marcar existencias como conciliadas
                foreach ($existencias as $existencia) {
                    $metadata = $existencia->metadata ?? [];
                    $metadata['conciliacion_mensual'][] = [
                        'fecha' => now()->toDateTimeString(),
                        'anio' => $request->anio,
                        'mes' => $request->mes,
                        'usuario_id' => auth()->id()
                    ];
                    $existencia->metadata = $metadata;
                    $existencia->save();
                }
            }

            $this->logActivity(
                auth()->id(),
                'inventarios',
                'conciliacion_mensual',
                'existencias',
                "Conciliación mensual realizada para instalación {$request->instalacion_id} - {$request->anio}/{$request->mes}",
                'existencias',
                null,
                null,
                ['resultados' => $resultados]
            );

            DB::commit();

            return $this->sendResponse([
                'periodo' => [
                    'anio' => $request->anio,
                    'mes' => $request->mes,
                    'inicio' => $fechaInicio->format('Y-m-d'),
                    'fin' => $fechaFin->format('Y-m-d')
                ],
                'instalacion_id' => $request->instalacion_id,
                'resultados' => $resultados,
                'resumen' => [
                    'total_tanques' => count($resultados),
                    'tanques_conforme' => collect($resultados)->where('estado', 'CONFORME')->count(),
                    'tanques_observado' => collect($resultados)->where('estado', 'OBSERVADO')->count(),
                    'tanques_critico' => collect($resultados)->where('estado', 'CRITICO')->count(),
                    'diferencia_total' => collect($resultados)->sum('diferencia_acumulada')
                ]
            ], 'Conciliación mensual completada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error en conciliación mensual', [$e->getMessage()], 500);
        }
    }

    /**
     * Obtener reporte de mermas
     */
    public function reporteMermas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'instalacion_id' => 'required|exists:instalaciones,id',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        $existencias = Existencia::whereHas('tanque', function($q) use ($request) {
                $q->where('instalacion_id', $request->instalacion_id);
            })
            ->whereBetween('fecha', [
                Carbon::parse($request->fecha_inicio),
                Carbon::parse($request->fecha_fin)
            ])
            ->with(['tanque', 'producto'])
            ->get();

        $mermas = [];

        foreach ($existencias->groupBy('tanque_id') as $tanqueId => $registros) {
            $tanque = $registros->first()->tanque;
            
            $diferencias = $registros->where('diferencia_volumen', '<', 0)->sum('diferencia_volumen');
            $volumenTotal = $registros->avg('volumen_final');
            
            if (abs($diferencias) > 0) {
                $mermas[] = [
                    'tanque' => [
                        'id' => $tanque->id,
                        'codigo' => $tanque->codigo
                    ],
                    'producto' => $tanque->productoActual ? $tanque->productoActual->nombre : 'N/A',
                    'dias_con_merma' => $registros->where('diferencia_volumen', '<', 0)->count(),
                    'volumen_merma' => abs($diferencias),
                    'porcentaje_merma' => $volumenTotal > 0 ? (abs($diferencias) / $volumenTotal) * 100 : 0,
                    'merma_promedio_diaria' => abs($diferencias) / $registros->count(),
                    'detalle_dias' => $registros
                        ->where('diferencia_volumen', '<', 0)
                        ->map(function ($r) {
                            return [
                                'fecha' => $r->fecha->format('Y-m-d'),
                                'merma' => abs($r->diferencia_volumen),
                                'porcentaje' => $r->porcentaje_diferencia,
                                'estado' => $r->estado
                            ];
                        })->values()
                ];
            }
        }

        $resumen = [
            'periodo' => [
                'inicio' => $request->fecha_inicio,
                'fin' => $request->fecha_fin
            ],
            'instalacion_id' => $request->instalacion_id,
            'total_mermas' => collect($mermas)->sum('volumen_merma'),
            'promedio_diario' => collect($mermas)->sum('volumen_merma') / 
                max(Carbon::parse($request->fecha_inicio)->diffInDays(Carbon::parse($request->fecha_fin)), 1),
            'tanques_con_merma' => count($mermas),
            'detalle_tanques' => $mermas
        ];

        return $this->sendResponse($resumen, 'Reporte de mermas obtenido exitosamente');
    }

    /**
     * Métodos privados
     */
    private function generarAlarmaPorDiferencia($existencia)
    {
        $alarma = Alarma::create([
            'instalacion_id' => $existencia->tanque->instalacion_id,
            'tanque_id' => $existencia->tanque_id,
            'tipo_alarma' => 'DIFERENCIA_INVENTARIO',
            'gravedad' => 'CRITICA',
            'descripcion' => "Diferencia crítica en inventario del tanque {$existencia->tanque->codigo}",
            'detalle' => [
                'existencia_id' => $existencia->id,
                'fecha' => $existencia->fecha->format('Y-m-d'),
                'volumen_esperado' => $existencia->volumen_esperado,
                'volumen_real' => $existencia->volumen_final,
                'diferencia' => $existencia->diferencia_volumen,
                'porcentaje' => $existencia->porcentaje_diferencia,
                'tolerancia' => $existencia->tolerancia_maxima
            ],
            'diagnostico_automatico' => 'La diferencia excede el doble de la tolerancia permitida',
            'recomendaciones' => 'Verificar mediciones, posibles fugas o errores en registros de entrada/salida',
            'fecha_alarma' => now(),
            'estado' => 'ACTIVA'
        ]);

        return $alarma;
    }

    private function calcularPorcentajeConciliacion($existencias)
    {
        $totalDias = $existencias->count();
        $diasConforme = $existencias->whereIn('estado', ['CONFORME', 'VALIDADO'])->count();
        
        return $totalDias > 0 ? ($diasConforme / $totalDias) * 100 : 0;
    }

    private function determinarEstadoConciliacion($existencias)
    {
        $criticos = $existencias->where('estado', 'CRITICO')->count();
        $observados = $existencias->where('estado', 'OBSERVADO')->count();
        $total = $existencias->count();

        if ($criticos > 0) {
            return 'CRITICO';
        } elseif ($observados > $total * 0.2) { // Más del 20% observados
            return 'OBSERVADO';
        } else {
            return 'CONFORME';
        }
    }
}