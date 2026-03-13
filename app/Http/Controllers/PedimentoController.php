<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Pedimento;
use App\Models\Contribuyente;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PedimentoController extends BaseController
{
    /**
     * Listar pedimentos
     */
    public function index(Request $request)
    {
        $query = Pedimento::with([
            'contribuyente',
            'producto',
            'registroVolumetrico'
        ]);

        // Filtros
        if ($request->has('contribuyente_id')) {
            $query->where('contribuyente_id', $request->contribuyente_id);
        }

        if ($request->has('numero_pedimento')) {
            $query->where('numero_pedimento', 'LIKE', "%{$request->numero_pedimento}%");
        }

        if ($request->has('producto_id')) {
            $query->where('producto_id', $request->producto_id);
        }

        if ($request->has('pais_origen')) {
            $query->where('pais_origen', $request->pais_origen);
        }

        if ($request->has('pais_destino')) {
            $query->where('pais_destino', $request->pais_destino);
        }

        if ($request->has('fecha_inicio')) {
            $query->where('fecha_pedimento', '>=', Carbon::parse($request->fecha_inicio));
        }

        if ($request->has('fecha_fin')) {
            $query->where('fecha_pedimento', '<=', Carbon::parse($request->fecha_fin));
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('registro_volumetrico_id')) {
            $query->where('registro_volumetrico_id', $request->registro_volumetrico_id);
        }

        $pedimentos = $query->orderBy('fecha_pedimento', 'desc')
            ->paginate($request->get('per_page', 15));

        return $this->success($pedimentos, 'Pedimentos obtenidos exitosamente');
    }

    /**
     * Crear pedimento
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'numero_pedimento' => 'required|string|max:255|unique:pedimentos,numero_pedimento',
            'contribuyente_id' => 'required|exists:contribuyentes,id',
            'producto_id' => 'required|exists:productos,id',
            'punto_exportacion' => 'nullable|string|max:255',
            'punto_internacion' => 'nullable|string|max:255',
            'pais_destino' => 'required|string|size:3',
            'pais_origen' => 'required|string|size:3',
            'medio_transporte_entrada' => 'required|string|max:255',
            'medio_transporte_salida' => 'nullable|string|max:255',
            'incoterms' => 'required|string|max:10',
            'volumen' => 'required|numeric|min:0',
            'unidad_medida' => 'required|string|max:10',
            'valor_comercial' => 'required|numeric|min:0',
            'moneda' => 'required|string|size:3',
            'fecha_pedimento' => 'required|date',
            'fecha_arribo' => 'nullable|date',
            'fecha_pago' => 'nullable|date',
            'registro_volumetrico_id' => 'nullable|exists:registros_volumetricos,id',
            'estado' => 'required|in:ACTIVO,UTILIZADO,CANCELADO',
            'metadatos_aduana' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $pedimento = Pedimento::create([
                'numero_pedimento' => $request->numero_pedimento,
                'contribuyente_id' => $request->contribuyente_id,
                'producto_id' => $request->producto_id,
                'punto_exportacion' => $request->punto_exportacion,
                'punto_internacion' => $request->punto_internacion,
                'pais_destino' => $request->pais_destino,
                'pais_origen' => $request->pais_origen,
                'medio_transporte_entrada' => $request->medio_transporte_entrada,
                'medio_transporte_salida' => $request->medio_transporte_salida,
                'incoterms' => $request->incoterms,
                'volumen' => $request->volumen,
                'unidad_medida' => $request->unidad_medida,
                'valor_comercial' => $request->valor_comercial,
                'moneda' => $request->moneda,
                'fecha_pedimento' => $request->fecha_pedimento,
                'fecha_arribo' => $request->fecha_arribo,
                'fecha_pago' => $request->fecha_pago,
                'registro_volumetrico_id' => $request->registro_volumetrico_id,
                'estado' => $request->estado,
                'metadatos_aduana' => $request->metadatos_aduana,
            ]);

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'CREACION_PEDIMENTO',
                'Comercio Exterior',
                "Pedimento creado: {$pedimento->numero_pedimento}",
                'pedimentos',
                $pedimento->id
            );

            DB::commit();

            return $this->success($pedimento->load(['contribuyente', 'producto']), 
                'Pedimento creado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al crear pedimento: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mostrar pedimento
     */
    public function show($id)
    {
        $pedimento = Pedimento::with([
            'contribuyente',
            'producto',
            'registroVolumetrico'
        ])->find($id);

        if (!$pedimento) {
            return $this->error('Pedimento no encontrado', 404);
        }

        return $this->success($pedimento, 'Pedimento obtenido exitosamente');
    }

    /**
     * Actualizar pedimento
     */
    public function update(Request $request, $id)
    {
        $pedimento = Pedimento::find($id);

        if (!$pedimento) {
            return $this->error('Pedimento no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'fecha_arribo' => 'nullable|date',
            'fecha_pago' => 'nullable|date',
            'registro_volumetrico_id' => 'nullable|exists:registros_volumetricos,id',
            'estado' => 'sometimes|in:ACTIVO,UTILIZADO,CANCELADO',
            'metadatos_aduana' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $pedimento->toArray();
            $pedimento->update($request->all());

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'ACTUALIZACION_PEDIMENTO',
                'Comercio Exterior',
                "Pedimento actualizado: {$pedimento->numero_pedimento}",
                'pedimentos',
                $pedimento->id,
                $datosAnteriores,
                $pedimento->toArray()
            );

            DB::commit();

            return $this->success($pedimento, 'Pedimento actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al actualizar pedimento: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cancelar pedimento
     */
    public function cancelar(Request $request, $id)
    {
        $pedimento = Pedimento::find($id);

        if (!$pedimento) {
            return $this->error('Pedimento no encontrado', 404);
        }

        if ($pedimento->estado == 'CANCELADO') {
            return $this->error('El pedimento ya está cancelado', 403);
        }

        $validator = Validator::make($request->all(), [
            'motivo_cancelacion' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $pedimento->toArray();

            $pedimento->estado = 'CANCELADO';
            
            $metadatos = $pedimento->metadatos_aduana ?? [];
            $metadatos['cancelacion'] = [
                'fecha' => now()->toDateTimeString(),
                'usuario_id' => auth()->id(),
                'motivo' => $request->motivo_cancelacion,
            ];
            $pedimento->metadatos_aduana = $metadatos;
            
            $pedimento->save();

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'CANCELACION_PEDIMENTO',
                'Comercio Exterior',
                "Pedimento cancelado: {$pedimento->numero_pedimento}",
                'pedimentos',
                $pedimento->id,
                $datosAnteriores,
                $pedimento->toArray()
            );

            DB::commit();

            return $this->success($pedimento, 'Pedimento cancelado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al cancelar pedimento: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Marcar como utilizado
     */
    public function marcarUtilizado(Request $request, $id)
    {
        $pedimento = Pedimento::find($id);

        if (!$pedimento) {
            return $this->error('Pedimento no encontrado', 404);
        }

        if ($pedimento->estado != 'ACTIVO') {
            return $this->error('El pedimento no está en estado ACTIVO', 403);
        }

        $validator = Validator::make($request->all(), [
            'registro_volumetrico_id' => 'required|exists:registros_volumetricos,id',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $pedimento->toArray();

            $pedimento->estado = 'UTILIZADO';
            $pedimento->registro_volumetrico_id = $request->registro_volumetrico_id;
            $pedimento->save();

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'UTILIZACION_PEDIMENTO',
                'Comercio Exterior',
                "Pedimento utilizado: {$pedimento->numero_pedimento}",
                'pedimentos',
                $pedimento->id,
                $datosAnteriores,
                $pedimento->toArray()
            );

            DB::commit();

            return $this->success($pedimento, 'Pedimento marcado como utilizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al marcar pedimento como utilizado: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener resumen de comercio exterior
     */
    public function resumenComercioExterior(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contribuyente_id' => 'required|exists:contribuyentes,id',
            'anio' => 'required|integer|min:2020',
            'mes' => 'nullable|integer|min:1|max:12',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        $query = Pedimento::where('contribuyente_id', $request->contribuyente_id)
            ->whereYear('fecha_pedimento', $request->anio);

        if ($request->has('mes')) {
            $query->whereMonth('fecha_pedimento', $request->mes);
        }

        $pedimentos = $query->with('producto')->get();

        $resumen = [
            'contribuyente_id' => $request->contribuyente_id,
            'periodo' => [
                'anio' => $request->anio,
                'mes' => $request->mes ?? 'TODOS',
            ],
            'totales' => [
                'cantidad_pedimentos' => $pedimentos->count(),
                'volumen_total' => $pedimentos->sum('volumen'),
                'valor_comercial_total' => $pedimentos->sum('valor_comercial'),
            ],
            'por_producto' => $pedimentos->groupBy('producto_id')
                ->map(function ($items) {
                    $producto = $items->first()->producto;
                    return [
                        'producto' => $producto ? $producto->nombre : 'N/A',
                        'cantidad' => $items->count(),
                        'volumen' => $items->sum('volumen'),
                        'valor' => $items->sum('valor_comercial'),
                    ];
                })->values(),
            'por_pais_origen' => $pedimentos->groupBy('pais_origen')
                ->map(function ($items, $pais) {
                    return [
                        'pais' => $pais,
                        'cantidad' => $items->count(),
                        'volumen' => $items->sum('volumen'),
                        'valor' => $items->sum('valor_comercial'),
                    ];
                })->values(),
            'tendencia_mensual' => $pedimentos->groupBy(function ($item) {
                    return $item->fecha_pedimento->format('Y-m');
                })
                ->map(function ($items, $mes) {
                    return [
                        'mes' => $mes,
                        'cantidad' => $items->count(),
                        'volumen' => $items->sum('volumen'),
                        'valor' => $items->sum('valor_comercial'),
                    ];
                })->values(),
        ];

        return $this->success($resumen, 'Resumen de comercio exterior obtenido exitosamente');
    }
}