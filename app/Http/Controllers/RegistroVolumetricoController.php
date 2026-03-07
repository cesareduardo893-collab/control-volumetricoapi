<?php

namespace App\Http\Controllers;

use App\Models\RegistroVolumetrico;
use App\Models\Instalacion;
use App\Models\Tanque;
use App\Models\Medidor;
use App\Models\Producto;
use App\Models\Alarma;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RegistroVolumetricoController extends BaseController
{
    /**
     * Constantes para condiciones de referencia
     */
    const TEMP_REF_HIDROCARBUROS = 15.56;
    const TEMP_REF_PETROLIFEROS = 20.00;
    const PRESION_REF = 101.325;
    
    const UMBRAL_DIF_LIQUIDOS = 0.5; // 0.5%
    const UMBRAL_DIF_GASEOSOS = 1.0; // 1.0%

    /**
     * Listar registros volumétricos
     */
    public function index(Request $request)
    {
        $query = RegistroVolumetrico::with([
            'instalacion',
            'tanque',
            'medidor',
            'producto',
            'usuarioRegistro',
            'usuarioValida'
        ]);

        // Filtros obligatorios por normativa
        if ($request->has('instalacion_id')) {
            $query->where('instalacion_id', $request->instalacion_id);
        }

        if ($request->has('fecha_inicio')) {
            $query->where('fecha_operacion', '>=', Carbon::parse($request->fecha_inicio)->startOfDay());
        }

        if ($request->has('fecha_fin')) {
            $query->where('fecha_operacion', '<=', Carbon::parse($request->fecha_fin)->endOfDay());
        }

        // Filtros opcionales
        if ($request->has('tanque_id')) {
            $query->where('tanque_id', $request->tanque_id);
        }

        if ($request->has('medidor_id')) {
            $query->where('medidor_id', $request->medidor_id);
        }

        if ($request->has('producto_id')) {
            $query->where('producto_id', $request->producto_id);
        }

        if ($request->has('tipo_registro')) {
            $query->where('tipo_registro', $request->tipo_registro);
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        // Validar que no exceda el rango de consulta permitido (30 días por normativa)
        if ($request->has('fecha_inicio') && $request->has('fecha_fin')) {
            $dias = Carbon::parse($request->fecha_inicio)->diffInDays(Carbon::parse($request->fecha_fin));
            if ($dias > 30) {
                return $this->sendError('El rango de consulta no puede exceder 30 días', [], 400);
            }
        }

        $registros = $query->orderBy('fecha_operacion', 'desc')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($registros, 'Registros volumétricos obtenidos exitosamente');
    }

    /**
     * Crear registro volumétrico
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'instalacion_id' => 'required|exists:instalaciones,id',
            'tanque_id' => 'required|exists:tanques,id',
            'medidor_id' => 'nullable|exists:medidores,id',
            'producto_id' => 'required|exists:productos,id',
            'dictamen_id' => 'nullable|exists:dictamenes,id',
            'cfdi_id' => 'nullable|exists:cfdis,id',
            'pedimento_id' => 'nullable|exists:pedimentos,id',
            'tipo_registro' => 'required|in:RECEPCION,ENTREGA,TRASPASO,DEVOLUCION,CONSUMO,PERDIDA',
            'fecha_operacion' => 'required|date_format:Y-m-d H:i:s',
            'volumen_inicial' => 'required|numeric|min:0',
            'volumen_final' => 'required|numeric|min:0',
            'volumen_recibido' => 'nullable|numeric|min:0',
            'volumen_entregado' => 'nullable|numeric|min:0',
            'temperatura' => 'required|numeric|between:-50,150',
            'presion' => 'required|numeric|min:0',
            'densidad' => 'nullable|numeric|min:0',
            'api_gravedad' => 'nullable|numeric|min:0|max:100',
            'composicion' => 'nullable|array',
            'composicion.*.componente' => 'required_with:composicion|string',
            'composicion.*.porcentaje' => 'required_with:composicion|numeric|min:0|max:100',
            'observaciones' => 'nullable|string|max:1000',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            // Validar que el tanque pertenezca a la instalación
            $tanque = Tanque::find($request->tanque_id);
            if ($tanque->instalacion_id != $request->instalacion_id) {
                return $this->sendError('El tanque no pertenece a la instalación especificada', [], 422);
            }

            // Validar que el medidor pertenezca a la instalación si se proporciona
            if ($request->has('medidor_id') && $request->medidor_id) {
                $medidor = Medidor::find($request->medidor_id);
                if ($medidor->instalacion_id != $request->instalacion_id) {
                    return $this->sendError('El medidor no pertenece a la instalación especificada', [], 422);
                }
            }

            // Determinar tipo de producto para condiciones de referencia
            $producto = Producto::find($request->producto_id);
            $tempReferencia = $producto->tipo == 'HIDROCARBURO' ? 
                self::TEMP_REF_HIDROCARBUROS : self::TEMP_REF_PETROLIFEROS;

            // Calcular volumen corregido
            $volumenCorregido = $this->calcularVolumenCorregido(
                $request->volumen_final - $request->volumen_inicial,
                $request->temperatura,
                $tempReferencia,
                $request->presion,
                self::PRESION_REF,
                $request->densidad
            );

            // Calcular factor de corrección
            $factorCorreccion = $volumenCorregido / max(($request->volumen_final - $request->volumen_inicial), 0.0001);

            // Crear el registro
            $registro = RegistroVolumetrico::create([
                'instalacion_id' => $request->instalacion_id,
                'tanque_id' => $request->tanque_id,
                'medidor_id' => $request->medidor_id,
                'producto_id' => $request->producto_id,
                'dictamen_id' => $request->dictamen_id,
                'cfdi_id' => $request->cfdi_id,
                'pedimento_id' => $request->pedimento_id,
                'tipo_registro' => $request->tipo_registro,
                'fecha_operacion' => $request->fecha_operacion,
                'volumen_inicial' => $request->volumen_inicial,
                'volumen_final' => $request->volumen_final,
                'volumen_recibido' => $request->volumen_recibido,
                'volumen_entregado' => $request->volumen_entregado,
                'volumen_corregido' => $volumenCorregido,
                'temperatura' => $request->temperatura,
                'presion' => $request->presion,
                'factor_correccion' => $factorCorreccion,
                'densidad' => $request->densidad,
                'api_gravedad' => $request->api_gravedad,
                'composicion' => $request->composicion,
                'observaciones' => $request->observaciones,
                'estado' => 'PENDIENTE',
                'usuario_registro_id' => auth()->id(),
                'detalle_calculo' => [
                    'temperatura_referencia' => $tempReferencia,
                    'presion_referencia' => self::PRESION_REF,
                    'factor_expansion_termica' => $this->calcularFactorExpansionTermica($producto->tipo),
                    'formula_aplicada' => 'ASTM D1250'
                ],
                'metadata' => $request->metadata
            ]);

            // Verificar consistencia volumétrica y generar alarmas si es necesario
            $this->verificarConsistenciaVolumetrica($registro);

            // Actualizar existencia del tanque
            $this->actualizarExistenciaTanque($registro);

            // Registrar en bitácora
            $this->logActivity(
                auth()->id(),
                'operacion_volumetrica',
                'registro_volumen',
                'registros_volumetricos',
                "Registro volumétrico creado: {$registro->tipo_registro} - Volumen: {$volumenCorregido}",
                'registros_volumetricos',
                $registro->id,
                null,
                $registro->toArray()
            );

            DB::commit();

            return $this->sendResponse($registro->load(['instalacion', 'tanque', 'producto']), 
                'Registro volumétrico creado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al crear registro volumétrico', [$e->getMessage()], 500);
        }
    }

    /**
     * Mostrar registro volumétrico
     */
    public function show($id)
    {
        $registro = RegistroVolumetrico::with([
            'instalacion',
            'tanque',
            'medidor',
            'producto',
            'dictamen',
            'cfdi',
            'pedimento',
            'usuarioRegistro',
            'usuarioValida',
            'alarmas'
        ])->find($id);

        if (!$registro) {
            return $this->sendError('Registro volumétrico no encontrado');
        }

        return $this->sendResponse($registro, 'Registro volumétrico obtenido exitosamente');
    }

    /**
     * Actualizar registro volumétrico (solo si está en estado PENDIENTE)
     */
    public function update(Request $request, $id)
    {
        $registro = RegistroVolumetrico::find($id);

        if (!$registro) {
            return $this->sendError('Registro volumétrico no encontrado');
        }

        if ($registro->estado != 'PENDIENTE') {
            return $this->sendError('No se puede modificar un registro que no está en estado PENDIENTE', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'dictamen_id' => 'nullable|exists:dictamenes,id',
            'cfdi_id' => 'nullable|exists:cfdis,id',
            'pedimento_id' => 'nullable|exists:pedimentos,id',
            'observaciones' => 'nullable|string|max:1000',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $registro->toArray();
            
            $registro->update($request->only([
                'dictamen_id', 'cfdi_id', 'pedimento_id', 'observaciones', 'metadata'
            ]));

            $this->logActivity(
                auth()->id(),
                'operacion_volumetrica',
                'actualizacion_registro',
                'registros_volumetricos',
                "Registro volumétrico actualizado ID: {$id}",
                'registros_volumetricos',
                $registro->id,
                $datosAnteriores,
                $registro->toArray()
            );

            DB::commit();

            return $this->sendResponse($registro, 'Registro volumétrico actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al actualizar registro volumétrico', [$e->getMessage()], 500);
        }
    }

    /**
     * Validar registro volumétrico
     */
    public function validar(Request $request, $id)
    {
        $registro = RegistroVolumetrico::find($id);

        if (!$registro) {
            return $this->sendError('Registro volumétrico no encontrado');
        }

        if ($registro->estado != 'PENDIENTE') {
            return $this->sendError('El registro ya ha sido validado', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'observaciones_validacion' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $registro->estado = 'VALIDADO';
            $registro->usuario_valida_id = auth()->id();
            $registro->fecha_validacion = now();
            
            if ($request->has('observaciones_validacion')) {
                $metadata = $registro->metadata ?? [];
                $metadata['observaciones_validacion'] = $request->observaciones_validacion;
                $registro->metadata = $metadata;
            }
            
            $registro->save();

            $this->logActivity(
                auth()->id(),
                'operacion_volumetrica',
                'validacion_registro',
                'registros_volumetricos',
                "Registro volumétrico validado ID: {$id}",
                'registros_volumetricos',
                $registro->id
            );

            DB::commit();

            return $this->sendResponse($registro, 'Registro volumétrico validado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al validar registro volumétrico', [$e->getMessage()], 500);
        }
    }

    /**
     * Marcar registro con alarma
     */
    public function marcarConAlarma(Request $request, $id)
    {
        $registro = RegistroVolumetrico::find($id);

        if (!$registro) {
            return $this->sendError('Registro volumétrico no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'alarma_id' => 'required|exists:alarmas,id',
            'observaciones' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $alarma = Alarma::find($request->alarma_id);
            
            if ($alarma->registro_volumetrico_id && $alarma->registro_volumetrico_id != $id) {
                return $this->sendError('La alarma ya está asociada a otro registro', [], 422);
            }

            $alarma->registro_volumetrico_id = $id;
            $alarma->save();

            $registro->estado = 'CON_ALARMA';
            
            $metadata = $registro->metadata ?? [];
            $metadata['alarma_asociada'] = [
                'id' => $alarma->id,
                'fecha' => now()->toDateTimeString(),
                'observaciones' => $request->observaciones
            ];
            $registro->metadata = $metadata;
            
            $registro->save();

            $this->logActivity(
                auth()->id(),
                'operacion_volumetrica',
                'asociar_alarma',
                'registros_volumetricos',
                "Alarma {$request->alarma_id} asociada al registro {$id}",
                'registros_volumetricos',
                $registro->id
            );

            DB::commit();

            return $this->sendResponse($registro, 'Alarma asociada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al asociar alarma', [$e->getMessage()], 500);
        }
    }

    /**
     * Cancelar registro volumétrico
     */
    public function cancelar(Request $request, $id)
    {
        $registro = RegistroVolumetrico::find($id);

        if (!$registro) {
            return $this->sendError('Registro volumétrico no encontrado');
        }

        if ($registro->estado == 'CANCELADO') {
            return $this->sendError('El registro ya está cancelado', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'motivo_cancelacion' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $registro->toArray();
            
            $registro->estado = 'CANCELADO';
            
            $metadata = $registro->metadata ?? [];
            $metadata['cancelacion'] = [
                'fecha' => now()->toDateTimeString(),
                'usuario_id' => auth()->id(),
                'motivo' => $request->motivo_cancelacion
            ];
            $registro->metadata = $metadata;
            
            $registro->save();

            $this->logActivity(
                auth()->id(),
                'operacion_volumetrica',
                'cancelacion_registro',
                'registros_volumetricos',
                "Registro volumétrico cancelado ID: {$id} - Motivo: {$request->motivo_cancelacion}",
                'registros_volumetricos',
                $registro->id,
                $datosAnteriores,
                $registro->toArray()
            );

            DB::commit();

            return $this->sendResponse($registro, 'Registro volumétrico cancelado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al cancelar registro volumétrico', [$e->getMessage()], 500);
        }
    }

    /**
     * Obtener resumen diario
     */
    public function resumenDiario(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'instalacion_id' => 'required|exists:instalaciones,id',
            'fecha' => 'required|date_format:Y-m-d'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        $fecha = Carbon::parse($request->fecha);
        
        $registros = RegistroVolumetrico::where('instalacion_id', $request->instalacion_id)
            ->whereDate('fecha_operacion', $fecha)
            ->with(['producto', 'tanque'])
            ->get();

        $resumen = [
            'fecha' => $fecha->format('Y-m-d'),
            'instalacion_id' => $request->instalacion_id,
            'total_registros' => $registros->count(),
            'por_tipo' => $registros->groupBy('tipo_registro')
                ->map(function ($items) {
                    return [
                        'cantidad' => $items->count(),
                        'volumen_total' => $items->sum('volumen_corregido')
                    ];
                }),
            'por_producto' => $registros->groupBy('producto.nombre')
                ->map(function ($items) {
                    return [
                        'cantidad' => $items->count(),
                        'volumen_total' => $items->sum('volumen_corregido')
                    ];
                }),
            'por_estado' => $registros->groupBy('estado')
                ->map(function ($items) {
                    return $items->count();
                }),
            'volumen_total' => $registros->sum('volumen_corregido'),
            'registros_con_alarma' => $registros->where('estado', 'CON_ALARMA')->count()
        ];

        return $this->sendResponse($resumen, 'Resumen diario obtenido exitosamente');
    }

    /**
     * Obtener estadísticas mensuales
     */
    public function estadisticasMensuales(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'instalacion_id' => 'required|exists:instalaciones,id',
            'anio' => 'required|integer|min:2020|max:2100',
            'mes' => 'required|integer|min:1|max:12'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        $fechaInicio = Carbon::createFromDate($request->anio, $request->mes, 1)->startOfMonth();
        $fechaFin = $fechaInicio->copy()->endOfMonth();

        $registros = RegistroVolumetrico::where('instalacion_id', $request->instalacion_id)
            ->whereBetween('fecha_operacion', [$fechaInicio, $fechaFin])
            ->with('producto')
            ->get();

        $estadisticas = [
            'periodo' => [
                'anio' => $request->anio,
                'mes' => $request->mes,
                'fecha_inicio' => $fechaInicio->format('Y-m-d'),
                'fecha_fin' => $fechaFin->format('Y-m-d')
            ],
            'instalacion_id' => $request->instalacion_id,
            'resumen_general' => [
                'total_registros' => $registros->count(),
                'volumen_total' => $registros->sum('volumen_corregido'),
                'promedio_diario' => $registros->sum('volumen_corregido') / $fechaInicio->daysInMonth,
                'registros_validados' => $registros->where('estado', 'VALIDADO')->count(),
                'registros_con_alarma' => $registros->where('estado', 'CON_ALARMA')->count()
            ],
            'por_producto' => $registros->groupBy('producto_id')
                ->map(function ($items) {
                    $producto = $items->first()->producto;
                    return [
                        'producto_id' => $producto->id,
                        'producto_nombre' => $producto->nombre,
                        'cantidad' => $items->count(),
                        'volumen_total' => $items->sum('volumen_corregido'),
                        'recepciones' => $items->where('tipo_registro', 'RECEPCION')->sum('volumen_corregido'),
                        'entregas' => $items->where('tipo_registro', 'ENTREGA')->sum('volumen_corregido')
                    ];
                })->values(),
            'tendencia_diaria' => $registros->groupBy(function ($item) {
                    return Carbon::parse($item->fecha_operacion)->format('Y-m-d');
                })
                ->map(function ($items, $fecha) {
                    return [
                        'fecha' => $fecha,
                        'total_registros' => $items->count(),
                        'volumen' => $items->sum('volumen_corregido')
                    ];
                })->values()
        ];

        return $this->sendResponse($estadisticas, 'Estadísticas mensuales obtenidas exitosamente');
    }

    /**
     * Métodos privados de cálculo
     */
    private function calcularVolumenCorregido($volumen, $tempActual, $tempRef, $presActual, $presRef, $densidad = null)
    {
        if ($volumen <= 0) {
            return 0;
        }

        // Factor de corrección por temperatura (ASTM D1250)
        $coeficienteExpansion = $this->calcularCoeficienteExpansion($densidad);
        $factorTemperatura = 1 + ($coeficienteExpansion * ($tempRef - $tempActual));

        // Factor de corrección por presión (compresibilidad)
        $factorPresion = 1 + (($presRef - $presActual) * 0.00001); // Aproximación simple

        return $volumen * $factorTemperatura * $factorPresion;
    }

    private function calcularCoeficienteExpansion($densidad)
    {
        if (!$densidad) {
            return 0.0006; // Valor por defecto para petrolíferos
        }

        // Aproximación basada en API MPMS Chapter 11
        if ($densidad < 0.85) {
            return 0.0008; // Productos ligeros
        } elseif ($densidad < 0.95) {
            return 0.0006; // Productos medios
        } else {
            return 0.0004; // Productos pesados
        }
    }

    private function calcularFactorExpansionTermica($tipoProducto)
    {
        return $tipoProducto == 'HIDROCARBURO' ? 0.0005 : 0.0006;
    }

    private function verificarConsistenciaVolumetrica($registro)
    {
        $instalacion = Instalacion::find($registro->instalacion_id);
        $umbrales = $instalacion->umbrales_alarma ?? [
            'diferencia_maxima_liquidos' => self::UMBRAL_DIF_LIQUIDOS,
            'diferencia_maxima_gaseosos' => self::UMBRAL_DIF_GASEOSOS
        ];

        // Obtener el producto para determinar si es líquido o gaseoso
        $producto = Producto::find($registro->producto_id);
        $esGaseoso = in_array($producto->tipo, ['GAS_NATURAL', 'GAS_LP']);
        
        $umbralMaximo = $esGaseoso ? 
            ($umbrales['diferencia_maxima_gaseosos'] ?? self::UMBRAL_DIF_GASEOSOS) : 
            ($umbrales['diferencia_maxima_liquidos'] ?? self::UMBRAL_DIF_LIQUIDOS);

        // Calcular diferencia esperada vs real
        $diferenciaCalculada = abs(
            ($registro->volumen_final - $registro->volumen_inicial) - 
            ($registro->volumen_recibido - $registro->volumen_entregado)
        );

        if ($registro->volumen_corregido > 0) {
            $porcentajeDiferencia = ($diferenciaCalculada / $registro->volumen_corregido) * 100;

            if ($porcentajeDiferencia > $umbralMaximo) {
                // Crear alarma por inconsistencia volumétrica
                $alarma = Alarma::create([
                    'instalacion_id' => $registro->instalacion_id,
                    'registro_volumetrico_id' => $registro->id,
                    'tipo_alarma' => 'INCONSISTENCIA_VOLUMETRICA',
                    'gravedad' => $porcentajeDiferencia > ($umbralMaximo * 2) ? 'CRITICA' : 'ALTA',
                    'descripcion' => "Diferencia volumétrica del {$porcentajeDiferencia}% supera el umbral permitido ({$umbralMaximo}%)",
                    'detalle' => [
                        'diferencia_calculada' => $diferenciaCalculada,
                        'porcentaje_diferencia' => $porcentajeDiferencia,
                        'umbral_maximo' => $umbralMaximo,
                        'volumen_corregido' => $registro->volumen_corregido,
                        'volumen_inicial' => $registro->volumen_inicial,
                        'volumen_final' => $registro->volumen_final,
                        'volumen_recibido' => $registro->volumen_recibido,
                        'volumen_entregado' => $registro->volumen_entregado
                    ],
                    'diagnostico_automatico' => 'Posible error en medición o fuga en el sistema',
                    'recomendaciones' => 'Verificar calibración de medidores y realizar prueba de integridad del tanque',
                    'fecha_alarma' => now(),
                    'estado' => 'ACTIVA'
                ]);

                $registro->estado = 'CON_ALARMA';
                $registro->save();

                $this->logActivity(
                    null,
                    'sistema',
                    'alarma_generada',
                    'alarmas',
                    "Alarma por inconsistencia volumétrica generada: {$porcentajeDiferencia}%",
                    'alarmas',
                    $alarma->id
                );
            }
        }
    }

    private function actualizarExistenciaTanque($registro)
    {
        $existencia = Existencia::firstOrCreate(
            [
                'tanque_id' => $registro->tanque_id,
                'producto_id' => $registro->producto_id,
                'fecha' => $registro->fecha_operacion->toDateString()
            ],
            [
                'volumen_inicial' => $registro->volumen_inicial,
                'volumen_final' => $registro->volumen_final,
                'volumen_recibido' => 0,
                'volumen_entregado' => 0,
                'volumen_corregido' => $registro->volumen_final,
                'usuario_registro_id' => auth()->id()
            ]
        );

        if ($existencia->wasRecentlyCreated === false) {
            // Actualizar existencia existente
            $volumenRecibido = $existencia->volumen_recibido + 
                ($registro->tipo_registro == 'RECEPCION' ? $registro->volumen_corregido : 0);
            $volumenEntregado = $existencia->volumen_entregado + 
                ($registro->tipo_registro == 'ENTREGA' ? $registro->volumen_corregido : 0);

            $existencia->update([
                'volumen_final' => $registro->volumen_final,
                'volumen_recibido' => $volumenRecibido,
                'volumen_entregado' => $volumenEntregado,
                'volumen_corregido' => $registro->volumen_final
            ]);
        }
    }
}