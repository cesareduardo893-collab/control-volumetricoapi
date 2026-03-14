<?php

namespace App\Http\Controllers;

use App\Models\RegistroVolumetrico;
use App\Models\Instalacion;
use App\Models\Tanque;
use App\Models\Medidor;
use App\Models\Producto;
use App\Models\Alarma;
use App\Models\Bitacora;
use App\Models\Dictamen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RegistroVolumetricoController extends BaseController
{
    /**
     * Constantes para condiciones de referencia
     */
    const TEMP_REFERENCIA = 20.00;
    const PRESION_REFERENCIA = 101.325;

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
            'usuarioValida',
            'dictamen'
        ]);

        // Filtros
        if ($request->has('instalacion_id')) {
            $query->where('instalacion_id', $request->instalacion_id);
        }

        if ($request->has('tanque_id')) {
            $query->where('tanque_id', $request->tanque_id);
        }

        if ($request->has('medidor_id')) {
            $query->where('medidor_id', $request->medidor_id);
        }

        if ($request->has('producto_id')) {
            $query->where('producto_id', $request->producto_id);
        }

        if ($request->has('numero_registro')) {
            $query->where('numero_registro', 'LIKE', "%{$request->numero_registro}%");
        }

        if ($request->has('fecha')) {
            $query->whereDate('fecha', $request->fecha);
        }

        if ($request->has('fecha_inicio')) {
            $query->where('fecha', '>=', Carbon::parse($request->fecha_inicio));
        }

        if ($request->has('fecha_fin')) {
            $query->where('fecha', '<=', Carbon::parse($request->fecha_fin));
        }

        if ($request->has('tipo_registro')) {
            $query->where('tipo_registro', $request->tipo_registro);
        }

        if ($request->has('operacion')) {
            $query->where('operacion', $request->operacion);
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('documento_fiscal_uuid')) {
            $query->where('documento_fiscal_uuid', $request->documento_fiscal_uuid);
        }

        if ($request->has('rfc_contraparte')) {
            $query->where('rfc_contraparte', $request->rfc_contraparte);
        }

        $registros = $query->orderBy('fecha', 'desc')
            ->orderBy('hora_inicio', 'desc')
            ->paginate($request->get('per_page', 15));

        return $this->success($registros, 'Registros volumétricos obtenidos exitosamente');
    }

    /**
     * Crear registro volumétrico
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'numero_registro' => 'required|string|max:255|unique:registros_volumetricos,numero_registro',
            'instalacion_id' => 'required|exists:instalaciones,id',
            'tanque_id' => 'required|exists:tanques,id',
            'medidor_id' => 'nullable|exists:medidores,id',
            'producto_id' => 'required|exists:productos,id',
            'usuario_registro_id' => 'required|exists:users,id',
            'usuario_valida_id' => 'nullable|exists:users,id',
            'fecha' => 'required|date',
            'hora_inicio' => 'required|date_format:H:i:s',
            'hora_fin' => 'required|date_format:H:i:s|after:hora_inicio',
            'volumen_inicial' => 'required|numeric|min:0',
            'volumen_final' => 'required|numeric|min:0',
            'volumen_operacion' => 'required|numeric|min:0',
            'temperatura_inicial' => 'required|numeric',
            'temperatura_final' => 'required|numeric',
            'presion_inicial' => 'nullable|numeric|min:0',
            'presion_final' => 'nullable|numeric|min:0',
            'densidad' => 'required|numeric|min:0',
            'volumen_corregido' => 'required|numeric|min:0',
            'factor_correccion' => 'required|numeric|min:0',
            'detalle_correccion' => 'nullable|array',
            'masa' => 'nullable|numeric|min:0',
            'poder_calorifico' => 'nullable|numeric|min:0',
            'energia_total' => 'nullable|numeric|min:0',
            'tipo_registro' => 'required|in:operacion,acumulado,existencias',
            'operacion' => 'required|in:recepcion,entrega,inventario_inicial,inventario_final,venta',
            'rfc_contraparte' => 'nullable|string|size:13',
            'documento_fiscal_uuid' => 'nullable|string|size:36',
            'folio_fiscal' => 'nullable|string|max:255',
            'tipo_cfdi' => 'nullable|string|max:255',
            'estado' => 'required|in:PENDIENTE,PROCESADO,VALIDADO,ERROR,CANCELADO,CON_ALARMA',
            'fecha_validacion' => 'nullable|date',
            'validaciones_realizadas' => 'nullable|array',
            'inconsistencias_detectadas' => 'nullable|array',
            'porcentaje_diferencia' => 'nullable|numeric|min:0|max:100',
            'observaciones' => 'nullable|string',
            'errores' => 'nullable|string',
            'dictamen_id' => 'nullable|exists:dictamenes,id',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            // Validar tanque activo
            $tanque = Tanque::find($request->tanque_id);
            if (!$tanque || !$tanque->activo) {
                return $this->error('El tanque no está activo', 422);
            }

            // Validar medidor si se especifica
            if ($request->has('medidor_id') && $request->medidor_id) {
                $medidor = Medidor::find($request->medidor_id);
                if (!$medidor || !$medidor->activo) {
                    return $this->error('El medidor no está activo', 422);
                }
            }

            $registro = RegistroVolumetrico::create([
                'numero_registro' => $request->numero_registro,
                'instalacion_id' => $request->instalacion_id,
                'tanque_id' => $request->tanque_id,
                'medidor_id' => $request->medidor_id,
                'producto_id' => $request->producto_id,
                'usuario_registro_id' => $request->usuario_registro_id,
                'usuario_valida_id' => $request->usuario_valida_id,
                'fecha' => $request->fecha,
                'hora_inicio' => $request->hora_inicio,
                'hora_fin' => $request->hora_fin,
                'volumen_inicial' => $request->volumen_inicial,
                'volumen_final' => $request->volumen_final,
                'volumen_operacion' => $request->volumen_operacion,
                'temperatura_inicial' => $request->temperatura_inicial,
                'temperatura_final' => $request->temperatura_final,
                'presion_inicial' => $request->presion_inicial,
                'presion_final' => $request->presion_final,
                'densidad' => $request->densidad,
                'volumen_corregido' => $request->volumen_corregido,
                'factor_correccion' => $request->factor_correccion,
                'detalle_correccion' => $request->detalle_correccion,
                'masa' => $request->masa,
                'poder_calorifico' => $request->poder_calorifico,
                'energia_total' => $request->energia_total,
                'tipo_registro' => $request->tipo_registro,
                'operacion' => $request->operacion,
                'rfc_contraparte' => $request->rfc_contraparte,
                'documento_fiscal_uuid' => $request->documento_fiscal_uuid,
                'folio_fiscal' => $request->folio_fiscal,
                'tipo_cfdi' => $request->tipo_cfdi,
                'estado' => $request->estado,
                'fecha_validacion' => $request->fecha_validacion,
                'validaciones_realizadas' => $request->validaciones_realizadas,
                'inconsistencias_detectadas' => $request->inconsistencias_detectadas,
                'porcentaje_diferencia' => $request->porcentaje_diferencia,
                'observaciones' => $request->observaciones,
                'errores' => $request->errores,
                'dictamen_id' => $request->dictamen_id,
            ]);

            // Verificar consistencia volumétrica
            $this->verificarConsistencia($registro);

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_OPERACIONES,
                'CREACION_REGISTRO_VOLUMETRICO',
                'Registros Volumétricos',
                "Registro volumétrico creado: {$registro->numero_registro}",
                'registros_volumetricos',
                $registro->id
            );

            DB::commit();

            return $this->success($registro->load(['instalacion', 'tanque', 'producto', 'usuarioRegistro']), 
                'Registro volumétrico creado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al crear registro volumétrico: ' . $e->getMessage(), 500);
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
            'usuarioRegistro',
            'usuarioValida',
            'dictamen'
        ])->find($id);

        if (!$registro) {
            return $this->error('Registro volumétrico no encontrado', 404);
        }

        return $this->success($registro, 'Registro volumétrico obtenido exitosamente');
    }

    /**
     * Validar registro volumétrico
     */
    public function validar(Request $request, $id)
    {
        $registro = RegistroVolumetrico::find($id);

        if (!$registro) {
            return $this->error('Registro volumétrico no encontrado', 404);
        }

        if ($registro->estado == 'VALIDADO') {
            return $this->error('El registro ya está validado', 403);
        }

        $validator = Validator::make($request->all(), [
            'observaciones_validacion' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $registro->toArray();

            $registro->estado = 'VALIDADO';
            $registro->usuario_valida_id = auth()->id();
            $registro->fecha_validacion = now();

            $validaciones = $registro->validaciones_realizadas ?? [];
            $validaciones[] = [
                'fecha' => now()->toDateTimeString(),
                'usuario_id' => auth()->id(),
                'observaciones' => $request->observaciones_validacion,
            ];
            $registro->validaciones_realizadas = $validaciones;

            $registro->save();

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_OPERACIONES,
                'VALIDACION_REGISTRO_VOLUMETRICO',
                'Registros Volumétricos',
                "Registro volumétrico validado: {$registro->numero_registro}",
                'registros_volumetricos',
                $registro->id,
                $datosAnteriores,
                $registro->toArray()
            );

            DB::commit();

            return $this->success($registro, 'Registro volumétrico validado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al validar registro volumétrico: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cancelar registro volumétrico
     */
    public function cancelar(Request $request, $id)
    {
        $registro = RegistroVolumetrico::find($id);

        if (!$registro) {
            return $this->error('Registro volumétrico no encontrado', 404);
        }

        if ($registro->estado == 'CANCELADO') {
            return $this->error('El registro ya está cancelado', 403);
        }

        $validator = Validator::make($request->all(), [
            'motivo_cancelacion' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $registro->toArray();

            $registro->estado = 'CANCELADO';
            
            $inconsistencias = $registro->inconsistencias_detectadas ?? [];
            $inconsistencias[] = [
                'tipo' => 'CANCELACION',
                'fecha' => now()->toDateTimeString(),
                'usuario_id' => auth()->id(),
                'descripcion' => $request->motivo_cancelacion,
            ];
            $registro->inconsistencias_detectadas = $inconsistencias;
            
            $registro->save();

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_OPERACIONES,
                'CANCELACION_REGISTRO_VOLUMETRICO',
                'Registros Volumétricos',
                "Registro volumétrico cancelado: {$registro->numero_registro}",
                'registros_volumetricos',
                $registro->id,
                $datosAnteriores,
                $registro->toArray()
            );

            DB::commit();

            return $this->success($registro, 'Registro volumétrico cancelado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al cancelar registro volumétrico: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener resumen diario
     */
    public function resumenDiario(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'instalacion_id' => 'required|exists:instalaciones,id',
            'fecha' => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        $registros = RegistroVolumetrico::where('instalacion_id', $request->instalacion_id)
            ->whereDate('fecha', $request->fecha)
            ->with('producto')
            ->get();

        $resumen = [
            'fecha' => $request->fecha,
            'instalacion_id' => $request->instalacion_id,
            'total_registros' => $registros->count(),
            'volumen_total' => $registros->sum('volumen_corregido'),
            'por_operacion' => $registros->groupBy('operacion')
                ->map(function ($items) {
                    return [
                        'cantidad' => $items->count(),
                        'volumen' => $items->sum('volumen_corregido'),
                    ];
                }),
            'por_producto' => $registros->groupBy('producto.nombre')
                ->map(function ($items) {
                    return [
                        'cantidad' => $items->count(),
                        'volumen' => $items->sum('volumen_corregido'),
                    ];
                }),
            'por_estado' => $registros->groupBy('estado')
                ->map(function ($items) {
                    return $items->count();
                }),
        ];

        return $this->success($resumen, 'Resumen diario obtenido exitosamente');
    }

    /**
     * Obtener estadísticas mensuales
     */
    public function estadisticasMensuales(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'instalacion_id' => 'required|exists:instalaciones,id',
            'anio' => 'required|integer|min:2020',
            'mes' => 'required|integer|min:1|max:12',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        $fechaInicio = Carbon::createFromDate($request->anio, $request->mes, 1)->startOfMonth();
        $fechaFin = $fechaInicio->copy()->endOfMonth();

        $registros = RegistroVolumetrico::where('instalacion_id', $request->instalacion_id)
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->with('producto')
            ->get();

        $estadisticas = [
            'periodo' => [
                'anio' => $request->anio,
                'mes' => $request->mes,
                'inicio' => $fechaInicio->toDateString(),
                'fin' => $fechaFin->toDateString(),
            ],
            'instalacion_id' => $request->instalacion_id,
            'resumen' => [
                'total_registros' => $registros->count(),
                'volumen_total' => $registros->sum('volumen_corregido'),
                'promedio_diario' => $fechaInicio->daysInMonth > 0 
                    ? $registros->sum('volumen_corregido') / $fechaInicio->daysInMonth
                    : 0,
                'registros_validados' => $registros->where('estado', 'VALIDADO')->count(),
                'registros_con_alarma' => $registros->where('estado', 'CON_ALARMA')->count(),
            ],
            'por_producto' => $registros->groupBy('producto_id')
                ->map(function ($items) {
                    $producto = $items->first()->producto;
                    return [
                        'producto_id' => $producto->id,
                        'producto_nombre' => $producto->nombre,
                        'cantidad' => $items->count(),
                        'volumen_total' => $items->sum('volumen_corregido'),
                        'recepciones' => $items->where('operacion', 'recepcion')->sum('volumen_corregido'),
                        'entregas' => $items->where('operacion', 'entrega')->sum('volumen_corregido'),
                        'ventas' => $items->where('operacion', 'venta')->sum('volumen_corregido'),
                    ];
                })->values(),
            'tendencia_diaria' => $registros->groupBy('fecha')
                ->map(function ($items, $fecha) {
                    return [
                        'fecha' => $fecha,
                        'registros' => $items->count(),
                        'volumen' => $items->sum('volumen_corregido'),
                    ];
                })->values(),
        ];

        return $this->success($estadisticas, 'Estadísticas mensuales obtenidas exitosamente');
    }

    /**
     * Asociar dictamen
     */
    public function asociarDictamen(Request $request, $id)
    {
        $registro = RegistroVolumetrico::find($id);

        if (!$registro) {
            return $this->error('Registro volumétrico no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'dictamen_id' => 'required|exists:dictamenes,id',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $registro->toArray();
            $registro->dictamen_id = $request->dictamen_id;
            $registro->save();

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_OPERACIONES,
                'ASOCIACION_DICTAMEN',
                'Registros Volumétricos',
                "Dictamen asociado a registro {$registro->numero_registro}",
                'registros_volumetricos',
                $registro->id,
                $datosAnteriores,
                $registro->toArray()
            );

            DB::commit();

            return $this->success($registro->load('dictamen'), 'Dictamen asociado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al asociar dictamen: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Métodos privados
     */
    private function verificarConsistencia($registro)
    {
        // Calcular volumen esperado basado en operaciones
        $volumenEsperado = $registro->volumen_final - $registro->volumen_inicial;
        
        if ($registro->volumen_operacion > 0) {
            $diferencia = abs($volumenEsperado - $registro->volumen_operacion);
            $porcentajeDiferencia = ($diferencia / $registro->volumen_operacion) * 100;
            
            $registro->porcentaje_diferencia = $porcentajeDiferencia;
            
            // Si la diferencia es mayor al 5%, marcar como inconsistente
            if ($porcentajeDiferencia > 5) {
                $inconsistencias = $registro->inconsistencias_detectadas ?? [];
                $inconsistencias[] = [
                    'tipo' => 'DIFERENCIA_VOLUMEN',
                    'fecha' => now()->toDateTimeString(),
                    'descripcion' => "Diferencia del {$porcentajeDiferencia}% entre volumen operado y variación de inventario",
                    'volumen_esperado' => $volumenEsperado,
                    'volumen_operacion' => $registro->volumen_operacion,
                    'diferencia' => $diferencia,
                ];
                $registro->inconsistencias_detectadas = $inconsistencias;
                
                if ($registro->estado != 'CANCELADO') {
                    $registro->estado = 'CON_ALARMA';
                }
            }
            
            $registro->save();
        }
    }
}