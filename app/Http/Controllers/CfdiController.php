<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Cfdi;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CfdiController extends BaseController
{
    /**
     * Listar CFDI
     */
    public function index(Request $request)
    {
        $query = Cfdi::with(['producto', 'registroVolumetrico']);

        // Filtros
        if ($request->has('uuid')) {
            $query->where('uuid', $request->uuid);
        }

        if ($request->has('rfc_emisor')) {
            $query->where('rfc_emisor', 'LIKE', "%{$request->rfc_emisor}%");
        }

        if ($request->has('rfc_receptor')) {
            $query->where('rfc_receptor', 'LIKE', "%{$request->rfc_receptor}%");
        }

        if ($request->has('tipo_operacion')) {
            $query->where('tipo_operacion', $request->tipo_operacion);
        }

        if ($request->has('producto_id')) {
            $query->where('producto_id', $request->producto_id);
        }

        if ($request->has('fecha_inicio')) {
            $query->where('fecha_emision', '>=', Carbon::parse($request->fecha_inicio));
        }

        if ($request->has('fecha_fin')) {
            $query->where('fecha_emision', '<=', Carbon::parse($request->fecha_fin));
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('registro_volumetrico_id')) {
            $query->where('registro_volumetrico_id', $request->registro_volumetrico_id);
        }

        $cfdis = $query->orderBy('fecha_emision', 'desc')
            ->paginate($request->get('per_page', 15));

        return $this->success($cfdis, 'CFDI obtenidos exitosamente');
    }

    /**
     * Crear CFDI
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'uuid' => 'required|string|size:36|unique:cfdi,uuid',
            'rfc_emisor' => 'required|string|size:13',
            'nombre_emisor' => 'nullable|string|max:255',
            'rfc_receptor' => 'required|string|size:13',
            'nombre_receptor' => 'nullable|string|max:255',
            'tipo_operacion' => 'required|in:adquisicion,enajenacion,servicio',
            'producto_id' => 'nullable|exists:productos,id',
            'volumen' => 'nullable|numeric|min:0',
            'unidad_medida' => 'nullable|string|max:10',
            'precio_unitario' => 'nullable|numeric|min:0',
            'subtotal' => 'required|numeric|min:0',
            'iva' => 'required|numeric|min:0',
            'ieps' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'tipo_servicio' => 'nullable|string|max:255',
            'descripcion_servicio' => 'nullable|string',
            'fecha_emision' => 'required|date',
            'fecha_certificacion' => 'nullable|date',
            'registro_volumetrico_id' => 'nullable|exists:registros_volumetricos,id',
            'xml' => 'nullable|string',
            'ruta_xml' => 'nullable|string|max:255',
            'metadatos' => 'nullable|array',
            'estado' => 'required|in:VIGENTE,CANCELADO',
            'fecha_cancelacion' => 'nullable|date',
            'uuid_relacionado' => 'nullable|string|size:36',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $cfdi = Cfdi::create([
                'uuid' => $request->uuid,
                'rfc_emisor' => $request->rfc_emisor,
                'nombre_emisor' => $request->nombre_emisor,
                'rfc_receptor' => $request->rfc_receptor,
                'nombre_receptor' => $request->nombre_receptor,
                'tipo_operacion' => $request->tipo_operacion,
                'producto_id' => $request->producto_id,
                'volumen' => $request->volumen,
                'unidad_medida' => $request->unidad_medida,
                'precio_unitario' => $request->precio_unitario,
                'subtotal' => $request->subtotal,
                'iva' => $request->iva,
                'ieps' => $request->ieps,
                'total' => $request->total,
                'tipo_servicio' => $request->tipo_servicio,
                'descripcion_servicio' => $request->descripcion_servicio,
                'fecha_emision' => $request->fecha_emision,
                'fecha_certificacion' => $request->fecha_certificacion,
                'registro_volumetrico_id' => $request->registro_volumetrico_id,
                'xml' => $request->xml,
                'ruta_xml' => $request->ruta_xml,
                'metadatos' => $request->metadatos,
                'estado' => $request->estado,
                'fecha_cancelacion' => $request->fecha_cancelacion,
                'uuid_relacionado' => $request->uuid_relacionado,
            ]);

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'CREACION_CFDI',
                'Fiscal',
                "CFDI creado: {$cfdi->uuid}",
                'cfdi',
                $cfdi->id
            );

            DB::commit();

            return $this->success($cfdi->load('producto'), 'CFDI creado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al crear CFDI: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mostrar CFDI
     */
    public function show($id)
    {
        $cfdi = Cfdi::with([
            'producto',
            'registroVolumetrico'
        ])->find($id);

        if (!$cfdi) {
            return $this->error('CFDI no encontrado', 404);
        }

        return $this->success($cfdi, 'CFDI obtenido exitosamente');
    }

    /**
     * Cancelar CFDI
     */
    public function cancelar(Request $request, $id)
    {
        $cfdi = Cfdi::find($id);

        if (!$cfdi) {
            return $this->error('CFDI no encontrado', 404);
        }

        if ($cfdi->estado == 'CANCELADO') {
            return $this->error('El CFDI ya está cancelado', 403);
        }

        $validator = Validator::make($request->all(), [
            'motivo_cancelacion' => 'required|string',
            'uuid_relacionado' => 'nullable|string|size:36',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $cfdi->toArray();

            $cfdi->estado = 'CANCELADO';
            $cfdi->fecha_cancelacion = now();
            $cfdi->uuid_relacionado = $request->uuid_relacionado;

            $metadatos = $cfdi->metadatos ?? [];
            $metadatos['cancelacion'] = [
                'fecha' => now()->toDateTimeString(),
                'usuario_id' => auth()->id(),
                'motivo' => $request->motivo_cancelacion,
            ];
            $cfdi->metadatos = $metadatos;

            $cfdi->save();

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'CANCELACION_CFDI',
                'Fiscal',
                "CFDI cancelado: {$cfdi->uuid}",
                'cfdi',
                $cfdi->id,
                $datosAnteriores,
                $cfdi->toArray()
            );

            DB::commit();

            return $this->success($cfdi, 'CFDI cancelado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al cancelar CFDI: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener por RFC
     */
    public function porRfc(Request $request, $rfc)
    {
        $validator = Validator::make($request->all(), [
            'tipo' => 'required|in:emisor,receptor',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        $query = Cfdi::where($request->tipo == 'emisor' ? 'rfc_emisor' : 'rfc_receptor', $rfc)
            ->whereBetween('fecha_emision', [
                Carbon::parse($request->fecha_inicio),
                Carbon::parse($request->fecha_fin)
            ])
            ->with('producto');

        $cfdis = $query->orderBy('fecha_emision', 'desc')
            ->paginate($request->get('per_page', 15));

        $resumen = [
            'rfc' => $rfc,
            'tipo' => $request->tipo,
            'periodo' => [
                'inicio' => $request->fecha_inicio,
                'fin' => $request->fecha_fin,
            ],
            'totales' => [
                'cantidad' => $cfdis->total(),
                'subtotal' => $query->sum('subtotal'),
                'iva' => $query->sum('iva'),
                'ieps' => $query->sum('ieps'),
                'total' => $query->sum('total'),
            ],
            'cfdis' => $cfdis,
        ];

        return $this->success($resumen, 'CFDI por RFC obtenidos exitosamente');
    }

    /**
     * Obtener resumen fiscal
     */
    public function resumenFiscal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contribuyente_rfc' => 'required|string|size:13',
            'anio' => 'required|integer|min:2020',
            'mes' => 'nullable|integer|min:1|max:12',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        $query = Cfdi::where('rfc_emisor', $request->contribuyente_rfc)
            ->whereYear('fecha_emision', $request->anio);

        if ($request->has('mes')) {
            $query->whereMonth('fecha_emision', $request->mes);
        }

        $cfdis = $query->get();

        $resumen = [
            'contribuyente_rfc' => $request->contribuyente_rfc,
            'periodo' => [
                'anio' => $request->anio,
                'mes' => $request->mes ?? 'TODOS',
            ],
            'totales' => [
                'cantidad_cfdis' => $cfdis->count(),
                'subtotal' => $cfdis->sum('subtotal'),
                'iva' => $cfdis->sum('iva'),
                'ieps' => $cfdis->sum('ieps'),
                'total' => $cfdis->sum('total'),
            ],
            'por_tipo_operacion' => $cfdis->groupBy('tipo_operacion')
                ->map(function ($items) {
                    return [
                        'cantidad' => $items->count(),
                        'total' => $items->sum('total'),
                    ];
                }),
            'por_estado' => $cfdis->groupBy('estado')
                ->map(function ($items) {
                    return $items->count();
                }),
            'tendencia_mensual' => $cfdis->groupBy(function ($item) {
                    return $item->fecha_emision->format('Y-m');
                })
                ->map(function ($items, $mes) {
                    return [
                        'mes' => $mes,
                        'cantidad' => $items->count(),
                        'total' => $items->sum('total'),
                    ];
                })->values(),
        ];

        return $this->success($resumen, 'Resumen fiscal obtenido exitosamente');
    }
}