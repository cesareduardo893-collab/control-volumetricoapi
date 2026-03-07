<?php

namespace App\Http\Controllers;

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
        $query = Pedimento::with(['contribuyente', 'producto']);

        // Filtros
        if ($request->has('contribuyente_id')) {
            $query->where('contribuyente_id', $request->contribuyente_id);
        }

        if ($request->has('numero_pedimento')) {
            $query->where('numero_pedimento', 'LIKE', "%{$request->numero_pedimento}%");
        }

        if ($request->has('aduana')) {
            $query->where('aduana', $request->aduana);
        }

        if ($request->has('tipo_operacion')) {
            $query->where('tipo_operacion', $request->tipo_operacion);
        }

        if ($request->has('regimen')) {
            $query->where('regimen', $request->regimen);
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

        $pedimentos = $query->orderBy('fecha_pedimento', 'desc')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($pedimentos, 'Pedimentos obtenidos exitosamente');
    }

    /**
     * Crear pedimento
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contribuyente_id' => 'required|exists:contribuyentes,id',
            'producto_id' => 'required|exists:productos,id',
            'numero_pedimento' => 'required|string|max:50|unique:pedimentos,numero_pedimento',
            'aduana' => 'required|string|max:100',
            'seccion_aduana' => 'nullable|string|max:100',
            'tipo_operacion' => 'required|in:IMPORTACION,EXPORTACION',
            'regimen' => 'required|string|max:50',
            'fecha_pedimento' => 'required|date',
            'fecha_pago' => 'nullable|date',
            'fecha_entrada_salida' => 'nullable|date',
            'fecha_rectificacion' => 'nullable|date',
            'patente' => 'required|string|max:50',
            'agente_aduanal' => 'required|string|max:255',
            'rfc_agente' => 'required|string|size:13',
            'importador_exportador' => 'required|string|max:255',
            'rfc_importador_exportador' => 'required|string|size:13',
            'domicilio' => 'required|string|max:500',
            'pais_origen' => 'required_if:tipo_operacion,IMPORTACION|string|size:3',
            'pais_destino' => 'required_if:tipo_operacion,EXPORTACION|string|size:3',
            'pais_procedencia' => 'nullable|string|size:3',
            'medio_transporte' => 'required|in:MARITIMO,AEREO,TERRESTRE,FERROVIARIO,MULTIMODAL',
            'identificacion_transporte' => 'nullable|string|max:100',
            'contenedor' => 'nullable|string|max:50',
            'bl_numero' => 'nullable|string|max:50',
            'guia_numero' => 'nullable|string|max:50',
            'incoterm' => 'required|string|max:10',
            'moneda' => 'required|string|size:3',
            'tipo_cambio' => 'nullable|numeric|min:0',
            'valor_aduana' => 'required|numeric|min:0',
            'valor_comercial' => 'required|numeric|min:0',
            'flete' => 'nullable|numeric|min:0',
            'seguro' => 'nullable|numeric|min:0',
            'embalaje' => 'nullable|numeric|min:0',
            'otros_gastos' => 'nullable|numeric|min:0',
            'cantidad' => 'required|numeric|min:0',
            'unidad_medida' => 'required|in:KGM,TNE,LTR,MTQ,MTK,MT,NO',
            'unidad_comercial' => 'nullable|string|max:50',
            'cantidad_comercial' => 'nullable|numeric|min:0',
            'peso_bruto' => 'required|numeric|min:0',
            'peso_neto' => 'required|numeric|min:0',
            'bultos' => 'nullable|integer|min:0',
            'tipo_bulto' => 'nullable|string|max:50',
            'marcas' => 'nullable|string|max:500',
            'numero_conocimiento' => 'nullable|string|max:50',
            'fraccion_arancelaria' => 'required|string|max:20',
            'nico' => 'nullable|string|max:50',
            'permiso_previo' => 'nullable|string|max:50',
            'numero_permiso' => 'nullable|string|max:50',
            'fecha_vencimiento_permiso' => 'nullable|date',
            'contribuciones' => 'required|array',
            'contribuciones.iva' => 'required|numeric|min:0',
            'contribuciones.ieps' => 'nullable|numeric|min:0',
            'contribuciones.arancel' => 'required|numeric|min:0',
            'contribuciones.dta' => 'nullable|numeric|min:0',
            'contribuciones.total' => 'required|numeric|min:0',
            'observaciones' => 'nullable|string|max:1000',
            'archivo_pedimento' => 'nullable|file|mimes:pdf|max:10240',
            'archivo_documentos' => 'nullable|array',
            'archivo_documentos.*' => 'file|mimes:pdf,jpg,png|max:5120',
            'estado' => 'required|in:ACTIVO,RECTIFICADO,CANCELADO',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            // Guardar archivos
            $rutaArchivo = null;
            if ($request->hasFile('archivo_pedimento')) {
                $rutaArchivo = $request->file('archivo_pedimento')
                    ->store("pedimentos/{$request->contribuyente_id}/principal", 'public');
            }

            $documentos = [];
            if ($request->hasFile('archivo_documentos')) {
                foreach ($request->file('archivo_documentos') as $index => $archivo) {
                    $ruta = $archivo->store("pedimentos/{$request->contribuyente_id}/documentos", 'public');
                    $documentos[] = [
                        'nombre_original' => $archivo->getClientOriginalName(),
                        'ruta' => $ruta,
                        'tipo' => $archivo->getMimeType(),
                        'tamano' => $archivo->getSize()
                    ];
                }
            }

            $pedimento = Pedimento::create([
                'contribuyente_id' => $request->contribuyente_id,
                'producto_id' => $request->producto_id,
                'numero_pedimento' => $request->numero_pedimento,
                'aduana' => $request->aduana,
                'seccion_aduana' => $request->seccion_aduana,
                'tipo_operacion' => $request->tipo_operacion,
                'regimen' => $request->regimen,
                'fecha_pedimento' => $request->fecha_pedimento,
                'fecha_pago' => $request->fecha_pago,
                'fecha_entrada_salida' => $request->fecha_entrada_salida,
                'fecha_rectificacion' => $request->fecha_rectificacion,
                'patente' => $request->patente,
                'agente_aduanal' => $request->agente_aduanal,
                'rfc_agente' => $request->rfc_agente,
                'importador_exportador' => $request->importador_exportador,
                'rfc_importador_exportador' => $request->rfc_importador_exportador,
                'domicilio' => $request->domicilio,
                'pais_origen' => $request->pais_origen,
                'pais_destino' => $request->pais_destino,
                'pais_procedencia' => $request->pais_procedencia,
                'medio_transporte' => $request->medio_transporte,
                'identificacion_transporte' => $request->identificacion_transporte,
                'contenedor' => $request->contenedor,
                'bl_numero' => $request->bl_numero,
                'guia_numero' => $request->guia_numero,
                'incoterm' => $request->incoterm,
                'moneda' => $request->moneda,
                'tipo_cambio' => $request->tipo_cambio,
                'valor_aduana' => $request->valor_aduana,
                'valor_comercial' => $request->valor_comercial,
                'flete' => $request->flete,
                'seguro' => $request->seguro,
                'embalaje' => $request->embalaje,
                'otros_gastos' => $request->otros_gastos,
                'cantidad' => $request->cantidad,
                'unidad_medida' => $request->unidad_medida,
                'unidad_comercial' => $request->unidad_comercial,
                'cantidad_comercial' => $request->cantidad_comercial,
                'peso_bruto' => $request->peso_bruto,
                'peso_neto' => $request->peso_neto,
                'bultos' => $request->bultos,
                'tipo_bulto' => $request->tipo_bulto,
                'marcas' => $request->marcas,
                'numero_conocimiento' => $request->numero_conocimiento,
                'fraccion_arancelaria' => $request->fraccion_arancelaria,
                'nico' => $request->nico,
                'permiso_previo' => $request->permiso_previo,
                'numero_permiso' => $request->numero_permiso,
                'fecha_vencimiento_permiso' => $request->fecha_vencimiento_permiso,
                'contribuciones' => $request->contribuciones,
                'observaciones' => $request->observaciones,
                'archivo_pedimento' => $rutaArchivo,
                'archivo_documentos' => $documentos,
                'estado' => $request->estado,
                'metadata' => $request->metadata
            ]);

            $this->logActivity(
                auth()->id(),
                'comercio_exterior',
                'creacion_pedimento',
                'pedimentos',
                "Pedimento creado: {$pedimento->numero_pedimento} - {$pedimento->tipo_operacion}",
                'pedimentos',
                $pedimento->id
            );

            DB::commit();

            return $this->sendResponse($pedimento->load(['contribuyente', 'producto']), 
                'Pedimento creado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al crear pedimento', [$e->getMessage()], 500);
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
            'registrosVolumetricos' => function($q) {
                $q->latest()->limit(10);
            }
        ])->find($id);

        if (!$pedimento) {
            return $this->sendError('Pedimento no encontrado');
        }

        return $this->sendResponse($pedimento, 'Pedimento obtenido exitosamente');
    }

    /**
     * Actualizar pedimento
     */
    public function update(Request $request, $id)
    {
        $pedimento = Pedimento::find($id);

        if (!$pedimento) {
            return $this->sendError('Pedimento no encontrado');
        }

        if ($pedimento->estado == 'CANCELADO') {
            return $this->sendError('No se puede modificar un pedimento cancelado', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'observaciones' => 'nullable|string|max:1000',
            'estado' => 'sometimes|in:ACTIVO,RECTIFICADO,CANCELADO',
            'archivo_pedimento' => 'nullable|file|mimes:pdf|max:10240',
            'archivo_documentos' => 'nullable|array',
            'archivo_documentos.*' => 'file|mimes:pdf,jpg,png|max:5120',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $pedimento->toArray();

            // Actualizar archivo si se proporciona
            if ($request->hasFile('archivo_pedimento')) {
                $rutaArchivo = $request->file('archivo_pedimento')
                    ->store("pedimentos/{$pedimento->contribuyente_id}/principal", 'public');
                $pedimento->archivo_pedimento = $rutaArchivo;
            }

            // Actualizar documentos si se proporcionan
            if ($request->hasFile('archivo_documentos')) {
                $documentos = $pedimento->archivo_documentos ?? [];
                foreach ($request->file('archivo_documentos') as $archivo) {
                    $ruta = $archivo->store("pedimentos/{$pedimento->contribuyente_id}/documentos", 'public');
                    $documentos[] = [
                        'nombre_original' => $archivo->getClientOriginalName(),
                        'ruta' => $ruta,
                        'tipo' => $archivo->getMimeType(),
                        'tamano' => $archivo->getSize(),
                        'fecha' => now()->toDateTimeString()
                    ];
                }
                $pedimento->archivo_documentos = $documentos;
            }

            $pedimento->observaciones = $request->observaciones ?? $pedimento->observaciones;
            $pedimento->estado = $request->estado ?? $pedimento->estado;
            
            if ($request->has('metadata')) {
                $metadata = array_merge($pedimento->metadata ?? [], $request->metadata);
                $pedimento->metadata = $metadata;
            }
            
            $pedimento->save();

            $this->logActivity(
                auth()->id(),
                'comercio_exterior',
                'actualizacion_pedimento',
                'pedimentos',
                "Pedimento actualizado: {$pedimento->numero_pedimento}",
                'pedimentos',
                $pedimento->id,
                $datosAnteriores,
                $pedimento->toArray()
            );

            DB::commit();

            return $this->sendResponse($pedimento, 'Pedimento actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al actualizar pedimento', [$e->getMessage()], 500);
        }
    }

    /**
     * Cancelar pedimento
     */
    public function cancelar(Request $request, $id)
    {
        $pedimento = Pedimento::find($id);

        if (!$pedimento) {
            return $this->sendError('Pedimento no encontrado');
        }

        if ($pedimento->estado == 'CANCELADO') {
            return $this->sendError('El pedimento ya está cancelado', [], 403);
        }

        // Verificar si tiene registros volumétricos asociados
        $tieneRegistros = $pedimento->registrosVolumetricos()->exists();
        
        if ($tieneRegistros) {
            return $this->sendError('No se puede cancelar el pedimento porque tiene registros volumétricos asociados', [], 409);
        }

        $validator = Validator::make($request->all(), [
            'motivo_cancelacion' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $pedimento->toArray();

            $pedimento->estado = 'CANCELADO';
            
            $metadata = $pedimento->metadata ?? [];
            $metadata['cancelacion'] = [
                'fecha' => now()->toDateTimeString(),
                'usuario_id' => auth()->id(),
                'motivo' => $request->motivo_cancelacion
            ];
            $pedimento->metadata = $metadata;
            
            $pedimento->save();

            $this->logActivity(
                auth()->id(),
                'comercio_exterior',
                'cancelacion_pedimento',
                'pedimentos',
                "Pedimento cancelado: {$pedimento->numero_pedimento} - Motivo: {$request->motivo_cancelacion}",
                'pedimentos',
                $pedimento->id,
                $datosAnteriores,
                $pedimento->toArray()
            );

            DB::commit();

            return $this->sendResponse($pedimento, 'Pedimento cancelado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al cancelar pedimento', [$e->getMessage()], 500);
        }
    }

    /**
     * Descargar archivo del pedimento
     */
    public function descargar($id)
    {
        $pedimento = Pedimento::find($id);

        if (!$pedimento) {
            return $this->sendError('Pedimento no encontrado');
        }

        if (!$pedimento->archivo_pedimento || !Storage::disk('public')->exists($pedimento->archivo_pedimento)) {
            return $this->sendError('Archivo no encontrado', [], 404);
        }

        $nombreArchivo = "pedimento_{$pedimento->numero_pedimento}.pdf";

        return Storage::disk('public')->download($pedimento->archivo_pedimento, $nombreArchivo);
    }

    /**
     * Conciliar con registros volumétricos
     */
    public function conciliar(Request $request, $id)
    {
        $pedimento = Pedimento::find($id);

        if (!$pedimento) {
            return $this->sendError('Pedimento no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'registro_volumetrico_id' => 'required|exists:registros_volumetricos,id'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $registro = RegistroVolumetrico::find($request->registro_volumetrico_id);

            // Verificar que el registro no tenga ya un pedimento asociado
            if ($registro->pedimento_id) {
                return $this->sendError('El registro volumétrico ya tiene un pedimento asociado', [], 409);
            }

            // Verificar que el producto coincida
            if ($registro->producto_id != $pedimento->producto_id) {
                return $this->sendError('El producto del registro no coincide con el del pedimento', [], 422);
            }

            $datosAnteriores = $registro->toArray();
            
            $registro->pedimento_id = $pedimento->id;
            
            $metadata = $registro->metadata ?? [];
            $metadata['conciliacion_pedimento'] = [
                'fecha' => now()->toDateTimeString(),
                'usuario_id' => auth()->id(),
                'pedimento' => $pedimento->numero_pedimento
            ];
            $registro->metadata = $metadata;
            
            $registro->save();

            // Actualizar también el pedimento
            $pedimentoMetadata = $pedimento->metadata ?? [];
            $pedimentoMetadata['conciliaciones'][] = [
                'fecha' => now()->toDateTimeString(),
                'registro_volumetrico_id' => $registro->id,
                'volumen' => $registro->volumen_corregido
            ];
            $pedimento->metadata = $pedimentoMetadata;
            $pedimento->save();

            $this->logActivity(
                auth()->id(),
                'comercio_exterior',
                'conciliacion_pedimento',
                'pedimentos',
                "Pedimento {$pedimento->numero_pedimento} conciliado con registro volumétrico {$registro->id}",
                'pedimentos',
                $pedimento->id,
                null,
                ['registro_id' => $registro->id, 'volumen' => $registro->volumen_corregido]
            );

            DB::commit();

            return $this->sendResponse([
                'pedimento' => $pedimento,
                'registro' => $registro
            ], 'Conciliación realizada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al conciliar pedimento', [$e->getMessage()], 500);
        }
    }

    /**
     * Obtener resumen de comercio exterior
     */
    public function resumenComercioExterior(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contribuyente_id' => 'required|exists:contribuyentes,id',
            'anio' => 'required|integer|min:2020|max:2100',
            'mes' => 'nullable|integer|min:1|max:12'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        $query = Pedimento::where('contribuyente_id', $request->contribuyente_id)
            ->whereYear('fecha_pedimento', $request->anio);

        if ($request->has('mes')) {
            $query->whereMonth('fecha_pedimento', $request->mes);
        }

        $pedimentos = $query->get();

        $resumen = [
            'contribuyente_id' => $request->contribuyente_id,
            'periodo' => [
                'anio' => $request->anio,
                'mes' => $request->mes ?? 'TODOS'
            ],
            'totales' => [
                'cantidad_pedimentos' => $pedimentos->count(),
                'valor_total_importaciones' => $pedimentos->where('tipo_operacion', 'IMPORTACION')->sum('valor_aduana'),
                'valor_total_exportaciones' => $pedimentos->where('tipo_operacion', 'EXPORTACION')->sum('valor_aduana'),
                'volumen_total_importaciones' => $pedimentos->where('tipo_operacion', 'IMPORTACION')->sum('cantidad'),
                'volumen_total_exportaciones' => $pedimentos->where('tipo_operacion', 'EXPORTACION')->sum('cantidad'),
                'contribuciones_totales' => $pedimentos->sum('contribuciones.total')
            ],
            'por_tipo' => [
                'importaciones' => $pedimentos->where('tipo_operacion', 'IMPORTACION')->count(),
                'exportaciones' => $pedimentos->where('tipo_operacion', 'EXPORTACION')->count()
            ],
            'por_aduana' => $pedimentos->groupBy('aduana')
                ->map(function ($items) {
                    return [
                        'cantidad' => $items->count(),
                        'valor' => $items->sum('valor_aduana')
                    ];
                }),
            'por_producto' => $pedimentos->groupBy('producto_id')
                ->map(function ($items) {
                    $producto = $items->first()->producto;
                    return [
                        'producto' => $producto ? $producto->nombre : 'N/A',
                        'cantidad' => $items->count(),
                        'volumen' => $items->sum('cantidad'),
                        'valor' => $items->sum('valor_aduana')
                    ];
                })->values(),
            'por_pais' => $pedimentos->groupBy('pais_origen')
                ->map(function ($items, $pais) {
                    return [
                        'pais' => $pais,
                        'cantidad' => $items->count(),
                        'valor' => $items->sum('valor_aduana')
                    ];
                })->values(),
            'tendencia_mensual' => $pedimentos->groupBy(function ($item) {
                    return Carbon::parse($item->fecha_pedimento)->format('Y-m');
                })
                ->map(function ($items, $mes) {
                    return [
                        'mes' => $mes,
                        'importaciones' => $items->where('tipo_operacion', 'IMPORTACION')->count(),
                        'exportaciones' => $items->where('tipo_operacion', 'EXPORTACION')->count(),
                        'valor' => $items->sum('valor_aduana')
                    ];
                })->values()
        ];

        return $this->sendResponse($resumen, 'Resumen de comercio exterior obtenido exitosamente');
    }
}