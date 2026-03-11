<?php

namespace App\Http\Controllers;

use App\Models\Existencia;
use App\Models\Tanque;
use App\Models\Producto;
use App\Models\Alarma;
use App\Models\MovimientoDia;
use App\Models\User;
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
        ])->whereNull('deleted_at');

        // Filtros
        if ($request->has('tanque_id')) {
            $query->where('tanque_id', $request->tanque_id);
        }

        if ($request->has('producto_id')) {
            $query->where('producto_id', $request->producto_id);
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

        if ($request->has('tipo_movimiento')) {
            $query->where('tipo_movimiento', $request->tipo_movimiento);
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('numero_registro')) {
            $query->where('numero_registro', 'LIKE', "%{$request->numero_registro}%");
        }

        $existencias = $query->orderBy('fecha', 'desc')
            ->orderBy('hora', 'desc')
            ->paginate($request->get('per_page', 15));

        return $this->success($existencias, 'Existencias obtenidas exitosamente');
    }

    /**
     * Crear existencia
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'numero_registro' => 'required|string|max:255|unique:existencias,numero_registro',
            'tanque_id' => 'required|exists:tanques,id',
            'producto_id' => 'required|exists:productos,id',
            'fecha' => 'required|date',
            'hora' => 'required|date_format:H:i:s',
            'volumen_medido' => 'required|numeric|min:0',
            'temperatura' => 'required|numeric',
            'presion' => 'nullable|numeric|min:0',
            'densidad' => 'nullable|numeric|min:0',
            'volumen_corregido' => 'required|numeric|min:0',
            'factor_correccion_temperatura' => 'required|numeric|min:0',
            'factor_correccion_presion' => 'required|numeric|min:0',
            'volumen_disponible' => 'required|numeric|min:0',
            'volumen_agua' => 'required|numeric|min:0',
            'volumen_sedimentos' => 'required|numeric|min:0',
            'volumen_inicial_dia' => 'nullable|numeric|min:0',
            'volumen_calculado' => 'nullable|numeric|min:0',
            'diferencia_volumen' => 'nullable|numeric',
            'porcentaje_diferencia' => 'nullable|numeric|min:0|max:100',
            'detalle_calculo' => 'nullable|array',
            'tipo_registro' => 'required|in:inicial,operacion,final',
            'tipo_movimiento' => 'required|in:INICIAL,RECEPCION,ENTREGA,VENTA,TRASPASO,AJUSTE,INVENTARIO',
            'documento_referencia' => 'nullable|string|max:255',
            'rfc_contraparte' => 'nullable|string|size:13',
            'observaciones' => 'nullable|string',
            'usuario_registro_id' => 'required|exists:users,id',
            'usuario_valida_id' => 'nullable|exists:users,id',
            'fecha_validacion' => 'nullable|date',
            'estado' => 'required|in:PENDIENTE,VALIDADO,EN_REVISION,CON_ALARMA',
            'movimientos_dia' => 'nullable|array',
            'movimientos_dia.*.tipo_movimiento' => 'required|in:INICIAL,RECEPCION,ENTREGA,VENTA,TRASPASO,AJUSTE,INVENTARIO',
            'movimientos_dia.*.volumen' => 'required|numeric|min:0',
            'movimientos_dia.*.temperatura' => 'nullable|numeric',
            'movimientos_dia.*.presion' => 'nullable|numeric|min:0',
            'movimientos_dia.*.densidad' => 'nullable|numeric|min:0',
            'movimientos_dia.*.volumen_corregido' => 'required|numeric|min:0',
            'movimientos_dia.*.documento_referencia' => 'nullable|string|max:255',
            'movimientos_dia.*.rfc_contraparte' => 'nullable|string|size:13',
            'movimientos_dia.*.observaciones' => 'nullable|string',
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

            // Validar producto activo
            $producto = Producto::find($request->producto_id);
            if (!$producto || !$producto->activo) {
                return $this->error('El producto no está activo', 422);
            }

            $existencia = Existencia::create([
                'numero_registro' => $request->numero_registro,
                'tanque_id' => $request->tanque_id,
                'producto_id' => $request->producto_id,
                'fecha' => $request->fecha,
                'hora' => $request->hora,
                'volumen_medido' => $request->volumen_medido,
                'temperatura' => $request->temperatura,
                'presion' => $request->presion,
                'densidad' => $request->densidad,
                'volumen_corregido' => $request->volumen_corregido,
                'factor_correccion_temperatura' => $request->factor_correccion_temperatura,
                'factor_correccion_presion' => $request->factor_correccion_presion,
                'volumen_disponible' => $request->volumen_disponible,
                'volumen_agua' => $request->volumen_agua,
                'volumen_sedimentos' => $request->volumen_sedimentos,
                'volumen_inicial_dia' => $request->volumen_inicial_dia,
                'volumen_calculado' => $request->volumen_calculado,
                'diferencia_volumen' => $request->diferencia_volumen,
                'porcentaje_diferencia' => $request->porcentaje_diferencia,
                'detalle_calculo' => $request->detalle_calculo,
                'tipo_registro' => $request->tipo_registro,
                'tipo_movimiento' => $request->tipo_movimiento,
                'documento_referencia' => $request->documento_referencia,
                'rfc_contraparte' => $request->rfc_contraparte,
                'observaciones' => $request->observaciones,
                'usuario_registro_id' => $request->usuario_registro_id,
                'usuario_valida_id' => $request->usuario_valida_id,
                'fecha_validacion' => $request->fecha_validacion,
                'estado' => $request->estado,
            ]);

            // Registrar movimientos del día si existen
            if ($request->has('movimientos_dia') && is_array($request->movimientos_dia)) {
                foreach ($request->movimientos_dia as $movimiento) {
                    MovimientoDia::create([
                        'existencia_id' => $existencia->id,
                        'tipo_movimiento' => $movimiento['tipo_movimiento'],
                        'volumen' => $movimiento['volumen'],
                        'temperatura' => $movimiento['temperatura'] ?? null,
                        'presion' => $movimiento['presion'] ?? null,
                        'densidad' => $movimiento['densidad'] ?? null,
                        'volumen_corregido' => $movimiento['volumen_corregido'],
                        'documento_referencia' => $movimiento['documento_referencia'] ?? null,
                        'rfc_contraparte' => $movimiento['rfc_contraparte'] ?? null,
                        'observaciones' => $movimiento['observaciones'] ?? null,
                        'usuario_id' => auth()->id(),
                    ]);
                }
            }

            $this->logActivity(
                auth()->id(),
                'operaciones_cotidianas',
                'CREACION_EXISTENCIA',
                'Inventarios',
                "Existencia creada: {$existencia->numero_registro}",
                'existencias',
                $existencia->id
            );

            DB::commit();

            return $this->success($existencia->load(['tanque', 'producto', 'usuarioRegistro', 'movimientosDia']), 
                'Existencia creada exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al crear existencia: ' . $e->getMessage(), 500);
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
            'movimientosDia'
        ])->find($id);

        if (!$existencia) {
            return $this->error('Existencia no encontrada', 404);
        }

        return $this->success($existencia, 'Existencia obtenida exitosamente');
    }

    /**
     * Validar existencia
     */
    public function validar(Request $request, $id)
    {
        $existencia = Existencia::find($id);

        if (!$existencia) {
            return $this->error('Existencia no encontrada', 404);
        }

        if ($existencia->estado == 'VALIDADO') {
            return $this->error('La existencia ya está validada', 403);
        }

        $validator = Validator::make($request->all(), [
            'observaciones_validacion' => 'nullable|string',
            'acciones_correctivas' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $existencia->toArray();

            $existencia->estado = 'VALIDADO';
            $existencia->usuario_valida_id = auth()->id();
            $existencia->fecha_validacion = now();
            
            $detalle = $existencia->detalle_calculo ?? [];
            $detalle['validacion'] = [
                'fecha' => now()->toDateTimeString(),
                'usuario_id' => auth()->id(),
                'observaciones' => $request->observaciones_validacion,
                'acciones_correctivas' => $request->acciones_correctivas,
            ];
            $existencia->detalle_calculo = $detalle;
            
            $existencia->save();

            $this->logActivity(
                auth()->id(),
                'operaciones_cotidianas',
                'VALIDACION_EXISTENCIA',
                'Inventarios',
                "Existencia validada: {$existencia->numero_registro}",
                'existencias',
                $existencia->id,
                $datosAnteriores,
                $existencia->toArray()
            );

            DB::commit();

            return $this->success($existencia, 'Existencia validada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al validar existencia: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener inventario actual por tanque
     */
    public function inventarioActual($tanqueId)
    {
        $tanque = Tanque::with('producto')->find($tanqueId);

        if (!$tanque) {
            return $this->error('Tanque no encontrado', 404);
        }

        // Obtener última existencia
        $ultimaExistencia = Existencia::where('tanque_id', $tanqueId)
            ->orderBy('fecha', 'desc')
            ->orderBy('hora', 'desc')
            ->first();

        $inventario = [
            'tanque' => [
                'id' => $tanque->id,
                'identificador' => $tanque->identificador,
                'capacidad_operativa' => $tanque->capacidad_operativa,
            ],
            'producto' => $tanque->producto ? [
                'id' => $tanque->producto->id,
                'nombre' => $tanque->producto->nombre,
                'clave_sat' => $tanque->producto->clave_sat,
            ] : null,
            'ultima_existencia' => $ultimaExistencia ? [
                'fecha' => $ultimaExistencia->fecha,
                'hora' => $ultimaExistencia->hora,
                'volumen_corregido' => $ultimaExistencia->volumen_corregido,
                'volumen_disponible' => $ultimaExistencia->volumen_disponible,
                'temperatura' => $ultimaExistencia->temperatura,
                'densidad' => $ultimaExistencia->densidad,
                'porcentaje_ocupacion' => $tanque->capacidad_operativa > 0 
                    ? round(($ultimaExistencia->volumen_corregido / $tanque->capacidad_operativa) * 100, 2)
                    : 0,
            ] : null,
            'fecha_consulta' => now()->toDateTimeString(),
        ];

        return $this->success($inventario, 'Inventario actual obtenido exitosamente');
    }

    /**
     * Obtener histórico de existencias
     */
    public function historico(Request $request, $tanqueId)
    {
        $tanque = Tanque::find($tanqueId);

        if (!$tanque) {
            return $this->error('Tanque no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        $existencias = Existencia::where('tanque_id', $tanqueId)
            ->whereBetween('fecha', [
                Carbon::parse($request->fecha_inicio),
                Carbon::parse($request->fecha_fin)
            ])
            ->orderBy('fecha')
            ->orderBy('hora')
            ->get();

        $historico = [
            'tanque' => [
                'id' => $tanque->id,
                'identificador' => $tanque->identificador,
            ],
            'periodo' => [
                'inicio' => $request->fecha_inicio,
                'fin' => $request->fecha_fin,
            ],
            'resumen' => [
                'total_registros' => $existencias->count(),
                'volumen_promedio' => $existencias->avg('volumen_corregido'),
                'volumen_minimo' => $existencias->min('volumen_corregido'),
                'volumen_maximo' => $existencias->max('volumen_corregido'),
                'volumen_inicial' => $existencias->first()?->volumen_corregido,
                'volumen_final' => $existencias->last()?->volumen_corregido,
            ],
            'datos' => $existencias->map(function ($e) {
                return [
                    'fecha' => $e->fecha,
                    'hora' => $e->hora,
                    'volumen_corregido' => $e->volumen_corregido,
                    'temperatura' => $e->temperatura,
                    'densidad' => $e->densidad,
                    'tipo_movimiento' => $e->tipo_movimiento,
                    'estado' => $e->estado,
                ];
            }),
        ];

        return $this->success($historico, 'Histórico de existencias obtenido exitosamente');
    }

    /**
     * Obtener reporte de mermas
     */
    public function reporteMermas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'instalacion_id' => 'required|exists:instalaciones,id',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        $existencias = Existencia::whereHas('tanque', function($q) use ($request) {
                $q->where('instalacion_id', $request->instalacion_id);
            })
            ->whereBetween('fecha', [
                Carbon::parse($request->fecha_inicio),
                Carbon::parse($request->fecha_fin)
            ])
            ->where('diferencia_volumen', '<', 0)
            ->whereNotNull('diferencia_volumen')
            ->with(['tanque', 'producto'])
            ->get();

        $mermas = [
            'periodo' => [
                'inicio' => $request->fecha_inicio,
                'fin' => $request->fecha_fin,
            ],
            'instalacion_id' => $request->instalacion_id,
            'resumen' => [
                'total_mermas' => abs($existencias->sum('diferencia_volumen')),
                'promedio_diario' => $existencias->count() > 0 
                    ? abs($existencias->sum('diferencia_volumen')) / $existencias->count()
                    : 0,
                'registros_con_merma' => $existencias->count(),
            ],
            'por_tanque' => $existencias->groupBy('tanque_id')
                ->map(function ($items) {
                    $tanque = $items->first()->tanque;
                    return [
                        'tanque_id' => $tanque->id,
                        'identificador' => $tanque->identificador,
                        'producto' => $items->first()->producto->nombre,
                        'total_merma' => abs($items->sum('diferencia_volumen')),
                        'dias_con_merma' => $items->count(),
                        'merma_promedio' => abs($items->avg('diferencia_volumen')),
                    ];
                })->values(),
        ];

        return $this->success($mermas, 'Reporte de mermas obtenido exitosamente');
    }

    /**
     * Obtener existencias por fecha
     */
    public function porFecha(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date',
            'instalacion_id' => 'nullable|exists:instalaciones,id',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        $query = Existencia::whereDate('fecha', $request->fecha)
            ->with(['tanque.instalacion', 'producto']);

        if ($request->has('instalacion_id')) {
            $query->whereHas('tanque', function($q) use ($request) {
                $q->where('instalacion_id', $request->instalacion_id);
            });
        }

        $existencias = $query->orderBy('tanque_id')
            ->orderBy('hora', 'desc')
            ->get()
            ->groupBy('tanque_id');

        return $this->success($existencias, 'Existencias por fecha obtenidas exitosamente');
    }
}