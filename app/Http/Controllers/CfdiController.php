<?php

namespace App\Http\Controllers;

use App\Models\Cfdi;
use App\Models\Contribuyente;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class CfdiController extends BaseController
{
    /**
     * Listar CFDI
     */
    public function index(Request $request)
    {
        $query = Cfdi::with(['contribuyente', 'producto']);

        // Filtros
        if ($request->has('contribuyente_id')) {
            $query->where('contribuyente_id', $request->contribuyente_id);
        }

        if ($request->has('rfc_emisor')) {
            $query->where('rfc_emisor', 'LIKE', "%{$request->rfc_emisor}%");
        }

        if ($request->has('rfc_receptor')) {
            $query->where('rfc_receptor', 'LIKE', "%{$request->rfc_receptor}%");
        }

        if ($request->has('uuid')) {
            $query->where('uuid', 'LIKE', "%{$request->uuid}%");
        }

        if ($request->has('folio')) {
            $query->where('folio', 'LIKE', "%{$request->folio}%");
        }

        if ($request->has('tipo_comprobante')) {
            $query->where('tipo_comprobante', $request->tipo_comprobante);
        }

        if ($request->has('uso_cfdi')) {
            $query->where('uso_cfdi', $request->uso_cfdi);
        }

        if ($request->has('metodo_pago')) {
            $query->where('metodo_pago', $request->metodo_pago);
        }

        if ($request->has('forma_pago')) {
            $query->where('forma_pago', $request->forma_pago);
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('fecha_inicio')) {
            $query->where('fecha', '>=', Carbon::parse($request->fecha_inicio));
        }

        if ($request->has('fecha_fin')) {
            $query->where('fecha', '<=', Carbon::parse($request->fecha_fin));
        }

        if ($request->has('monto_min')) {
            $query->where('total', '>=', $request->monto_min);
        }

        if ($request->has('monto_max')) {
            $query->where('total', '<=', $request->monto_max);
        }

        if ($request->boolean('relacionado')) {
            $query->whereNotNull('uuid_relacionado');
        }

        if ($request->boolean('cancelado')) {
            $query->where('estado', 'CANCELADO');
        }

        $cfdis = $query->orderBy('fecha', 'desc')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($cfdis, 'CFDI obtenidos exitosamente');
    }

    /**
     * Crear CFDI (registro manual)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contribuyente_id' => 'required|exists:contribuyentes,id',
            'producto_id' => 'nullable|exists:productos,id',
            'uuid' => 'required|string|size:36|unique:cfdis,uuid',
            'folio' => 'nullable|string|max:50',
            'serie' => 'nullable|string|max:20',
            'rfc_emisor' => 'required|string|size:13',
            'nombre_emisor' => 'required|string|max:255',
            'rfc_receptor' => 'required|string|size:13',
            'nombre_receptor' => 'required|string|max:255',
            'tipo_comprobante' => 'required|in:I,E,T,P,N',
            'uso_cfdi' => 'required|string|max:10',
            'metodo_pago' => 'required|in:PUE,PPD',
            'forma_pago' => 'required|string|max:10',
            'moneda' => 'required|string|size:3',
            'tipo_cambio' => 'nullable|numeric|min:0',
            'fecha' => 'required|date',
            'fecha_certificacion' => 'nullable|date',
            'numero_certificado' => 'nullable|string|max:50',
            'subtotal' => 'required|numeric|min:0',
            'descuento' => 'nullable|numeric|min:0',
            'iva' => 'required|numeric|min:0',
            'ieps' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'conceptos' => 'required|array|min:1',
            'conceptos.*.clave_prod_serv' => 'required|string|max:20',
            'conceptos.*.clave_unidad' => 'required|string|max:10',
            'conceptos.*.descripcion' => 'required|string|max:255',
            'conceptos.*.cantidad' => 'required|numeric|min:0',
            'conceptos.*.valor_unitario' => 'required|numeric|min:0',
            'conceptos.*.importe' => 'required|numeric|min:0',
            'conceptos.*.descuento' => 'nullable|numeric|min:0',
            'conceptos.*.producto_id' => 'nullable|exists:productos,id',
            'impuestos' => 'nullable|array',
            'impuestos.retenciones' => 'nullable|array',
            'impuestos.retenciones.*.impuesto' => 'required_with:impuestos.retenciones|string',
            'impuestos.retenciones.*.importe' => 'required_with:impuestos.retenciones|numeric',
            'impuestos.traslados' => 'nullable|array',
            'impuestos.traslados.*.impuesto' => 'required_with:impuestos.traslados|string',
            'impuestos.traslados.*.tasa' => 'required_with:impuestos.traslados|numeric',
            'impuestos.traslados.*.importe' => 'required_with:impuestos.traslados|numeric',
            'uuid_relacionado' => 'nullable|string|size:36',
            'tipo_relacion' => 'nullable|string|max:10',
            'archivo_xml' => 'nullable|file|mimes:xml|max:5120',
            'archivo_pdf' => 'nullable|file|mimes:pdf|max:5120',
            'estado' => 'required|in:VIGENTE,CANCELADO',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            // Guardar archivos
            $rutaXml = null;
            if ($request->hasFile('archivo_xml')) {
                $rutaXml = $request->file('archivo_xml')
                    ->store("cfdi/{$request->contribuyente_id}/xml", 'public');
            }

            $rutaPdf = null;
            if ($request->hasFile('archivo_pdf')) {
                $rutaPdf = $request->file('archivo_pdf')
                    ->store("cfdi/{$request->contribuyente_id}/pdf", 'public');
            }

            $cfdi = Cfdi::create([
                'contribuyente_id' => $request->contribuyente_id,
                'producto_id' => $request->producto_id,
                'uuid' => $request->uuid,
                'folio' => $request->folio,
                'serie' => $request->serie,
                'rfc_emisor' => $request->rfc_emisor,
                'nombre_emisor' => $request->nombre_emisor,
                'rfc_receptor' => $request->rfc_receptor,
                'nombre_receptor' => $request->nombre_receptor,
                'tipo_comprobante' => $request->tipo_comprobante,
                'uso_cfdi' => $request->uso_cfdi,
                'metodo_pago' => $request->metodo_pago,
                'forma_pago' => $request->forma_pago,
                'moneda' => $request->moneda,
                'tipo_cambio' => $request->tipo_cambio,
                'fecha' => $request->fecha,
                'fecha_certificacion' => $request->fecha_certificacion,
                'numero_certificado' => $request->numero_certificado,
                'subtotal' => $request->subtotal,
                'descuento' => $request->descuento,
                'iva' => $request->iva,
                'ieps' => $request->ieps,
                'total' => $request->total,
                'conceptos' => $request->conceptos,
                'impuestos' => $request->impuestos,
                'uuid_relacionado' => $request->uuid_relacionado,
                'tipo_relacion' => $request->tipo_relacion,
                'archivo_xml' => $rutaXml,
                'archivo_pdf' => $rutaPdf,
                'estado' => $request->estado,
                'metadata' => $request->metadata
            ]);

            $this->logActivity(
                auth()->id(),
                'fiscal',
                'registro_cfdi',
                'cfdis',
                "CFDI registrado: {$cfdi->uuid} - Total: {$cfdi->total}",
                'cfdis',
                $cfdi->id
            );

            DB::commit();

            return $this->sendResponse($cfdi, 'CFDI registrado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al registrar CFDI', [$e->getMessage()], 500);
        }
    }

    /**
     * Importar CFDI desde SAT
     */
    public function importarDesdeSat(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contribuyente_id' => 'required|exists:contribuyentes,id',
            'rfc' => 'required|string|size:13',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'tipo' => 'required|in:EMITIDOS,RECIBIDOS'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            // Simular consulta al SAT (en producción usar el web service real)
            $cfdisImportados = $this->simularConsultaSat(
                $request->rfc,
                $request->fecha_inicio,
                $request->fecha_fin,
                $request->tipo
            );

            $importados = 0;
            $errores = [];

            DB::beginTransaction();

            foreach ($cfdisImportados as $datosCfdi) {
                try {
                    // Verificar si ya existe
                    $existente = Cfdi::where('uuid', $datosCfdi['uuid'])->first();
                    
                    if (!$existente) {
                        Cfdi::create([
                            'contribuyente_id' => $request->contribuyente_id,
                            'uuid' => $datosCfdi['uuid'],
                            'folio' => $datosCfdi['folio'] ?? null,
                            'serie' => $datosCfdi['serie'] ?? null,
                            'rfc_emisor' => $datosCfdi['rfc_emisor'],
                            'nombre_emisor' => $datosCfdi['nombre_emisor'],
                            'rfc_receptor' => $datosCfdi['rfc_receptor'],
                            'nombre_receptor' => $datosCfdi['nombre_receptor'],
                            'tipo_comprobante' => $datosCfdi['tipo_comprobante'],
                            'uso_cfdi' => $datosCfdi['uso_cfdi'] ?? '??',
                            'metodo_pago' => $datosCfdi['metodo_pago'] ?? 'PUE',
                            'forma_pago' => $datosCfdi['forma_pago'] ?? '??',
                            'moneda' => $datosCfdi['moneda'] ?? 'MXN',
                            'tipo_cambio' => $datosCfdi['tipo_cambio'] ?? 1,
                            'fecha' => $datosCfdi['fecha'],
                            'fecha_certificacion' => $datosCfdi['fecha_certificacion'] ?? null,
                            'subtotal' => $datosCfdi['subtotal'],
                            'iva' => $datosCfdi['iva'] ?? 0,
                            'total' => $datosCfdi['total'],
                            'conceptos' => $datosCfdi['conceptos'] ?? [],
                            'estado' => 'VIGENTE',
                            'metadata' => [
                                'importado_sat' => true,
                                'fecha_importacion' => now()->toDateTimeString()
                            ]
                        ]);
                        $importados++;
                    }
                } catch (\Exception $e) {
                    $errores[] = [
                        'uuid' => $datosCfdi['uuid'] ?? 'desconocido',
                        'error' => $e->getMessage()
                    ];
                }
            }

            $this->logActivity(
                auth()->id(),
                'fiscal',
                'importacion_cfdi_sat',
                'cfdis',
                "Importación SAT: {$importados} CFDI importados, " . count($errores) . " errores",
                'cfdis',
                null,
                null,
                ['resultado' => ['importados' => $importados, 'errores' => $errores]]
            );

            DB::commit();

            return $this->sendResponse([
                'total_procesados' => count($cfdisImportados),
                'importados' => $importados,
                'errores' => $errores
            ], 'Importación desde SAT completada');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error en importación desde SAT', [$e->getMessage()], 500);
        }
    }

    /**
     * Mostrar CFDI
     */
    public function show($id)
    {
        $cfdi = Cfdi::with([
            'contribuyente',
            'producto',
            'registrosVolumetricos' => function($q) {
                $q->latest()->limit(10);
            }
        ])->find($id);

        if (!$cfdi) {
            return $this->sendError('CFDI no encontrado');
        }

        return $this->sendResponse($cfdi, 'CFDI obtenido exitosamente');
    }

    /**
     * Cancelar CFDI
     */
    public function cancelar(Request $request, $id)
    {
        $cfdi = Cfdi::find($id);

        if (!$cfdi) {
            return $this->sendError('CFDI no encontrado');
        }

        if ($cfdi->estado == 'CANCELADO') {
            return $this->sendError('El CFDI ya está cancelado', [], 403);
        }

        // Verificar si tiene registros volumétricos asociados
        $tieneRegistros = $cfdi->registrosVolumetricos()->exists();
        
        if ($tieneRegistros) {
            return $this->sendError('No se puede cancelar el CFDI porque tiene registros volumétricos asociados', [], 409);
        }

        $validator = Validator::make($request->all(), [
            'motivo_cancelacion' => 'required|string|max:500',
            'folio_sustitucion' => 'nullable|string|size:36'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $cfdi->toArray();

            $cfdi->estado = 'CANCELADO';
            
            $metadata = $cfdi->metadata ?? [];
            $metadata['cancelacion'] = [
                'fecha' => now()->toDateTimeString(),
                'usuario_id' => auth()->id(),
                'motivo' => $request->motivo_cancelacion,
                'folio_sustitucion' => $request->folio_sustitucion
            ];
            $cfdi->metadata = $metadata;
            
            $cfdi->save();

            $this->logActivity(
                auth()->id(),
                'fiscal',
                'cancelacion_cfdi',
                'cfdis',
                "CFDI cancelado: {$cfdi->uuid} - Motivo: {$request->motivo_cancelacion}",
                'cfdis',
                $cfdi->id,
                $datosAnteriores,
                $cfdi->toArray()
            );

            DB::commit();

            return $this->sendResponse($cfdi, 'CFDI cancelado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al cancelar CFDI', [$e->getMessage()], 500);
        }
    }

    /**
     * Descargar XML
     */
    public function descargarXml($id)
    {
        $cfdi = Cfdi::find($id);

        if (!$cfdi) {
            return $this->sendError('CFDI no encontrado');
        }

        if (!$cfdi->archivo_xml || !Storage::disk('public')->exists($cfdi->archivo_xml)) {
            return $this->sendError('Archivo XML no encontrado', [], 404);
        }

        $nombreArchivo = "cfdi_{$cfdi->uuid}.xml";

        return Storage::disk('public')->download($cfdi->archivo_xml, $nombreArchivo);
    }

    /**
     * Descargar PDF
     */
    public function descargarPdf($id)
    {
        $cfdi = Cfdi::find($id);

        if (!$cfdi) {
            return $this->sendError('CFDI no encontrado');
        }

        if (!$cfdi->archivo_pdf || !Storage::disk('public')->exists($cfdi->archivo_pdf)) {
            return $this->sendError('Archivo PDF no encontrado', [], 404);
        }

        $nombreArchivo = "cfdi_{$cfdi->uuid}.pdf";

        return Storage::disk('public')->download($cfdi->archivo_pdf, $nombreArchivo);
    }

    /**
     * Verificar estatus en SAT
     */
    public function verificarEstatusSat($id)
    {
        $cfdi = Cfdi::find($id);

        if (!$cfdi) {
            return $this->sendError('CFDI no encontrado');
        }

        try {
            // Simular consulta de estatus en SAT
            $estatus = $this->simularEstatusSat($cfdi->uuid);

            // Actualizar si es necesario
            if ($estatus['estado'] != $cfdi->estado) {
                $datosAnteriores = $cfdi->toArray();
                $cfdi->estado = $estatus['estado'];
                
                $metadata = $cfdi->metadata ?? [];
                $metadata['verificaciones_sat'][] = [
                    'fecha' => now()->toDateTimeString(),
                    'estatus' => $estatus
                ];
                $cfdi->metadata = $metadata;
                
                $cfdi->save();

                $this->logActivity(
                    auth()->id(),
                    'fiscal',
                    'actualizacion_estatus_sat',
                    'cfdis',
                    "Estatus SAT actualizado para CFDI {$cfdi->uuid}: {$estatus['estado']}",
                    'cfdis',
                    $cfdi->id,
                    $datosAnteriores,
                    $cfdi->toArray()
                );
            }

            return $this->sendResponse([
                'cfdi_id' => $cfdi->id,
                'uuid' => $cfdi->uuid,
                'estatus_sat' => $estatus,
                'fecha_consulta' => now()->toDateTimeString()
            ], 'Estatus SAT verificado exitosamente');

        } catch (\Exception $e) {
            return $this->sendError('Error al verificar estatus en SAT', [$e->getMessage()], 500);
        }
    }

    /**
     * Obtener resumen fiscal
     */
    public function resumenFiscal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contribuyente_id' => 'required|exists:contribuyentes,id',
            'anio' => 'required|integer|min:2020|max:2100',
            'mes' => 'nullable|integer|min:1|max:12'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        $query = Cfdi::where('contribuyente_id', $request->contribuyente_id)
            ->whereYear('fecha', $request->anio);

        if ($request->has('mes')) {
            $query->whereMonth('fecha', $request->mes);
        }

        $cfdis = $query->get();

        $resumen = [
            'contribuyente_id' => $request->contribuyente_id,
            'periodo' => [
                'anio' => $request->anio,
                'mes' => $request->mes ?? 'TODOS'
            ],
            'totales' => [
                'cantidad_cfdis' => $cfdis->count(),
                'subtotal' => $cfdis->sum('subtotal'),
                'iva' => $cfdis->sum('iva'),
                'ieps' => $cfdis->sum('ieps'),
                'total' => $cfdis->sum('total')
            ],
            'por_tipo' => $cfdis->groupBy('tipo_comprobante')
                ->map(function ($items) {
                    return [
                        'cantidad' => $items->count(),
                        'total' => $items->sum('total')
                    ];
                }),
            'por_estado' => $cfdis->groupBy('estado')
                ->map(function ($items) {
                    return $items->count();
                }),
            'por_mes' => $cfdis->groupBy(function ($item) {
                    return Carbon::parse($item->fecha)->format('Y-m');
                })
                ->map(function ($items, $mes) {
                    return [
                        'mes' => $mes,
                        'cantidad' => $items->count(),
                        'total' => $items->sum('total')
                    ];
                })->values(),
            'top_emisores' => $cfdis->groupBy('rfc_emisor')
                ->map(function ($items) {
                    $primer = $items->first();
                    return [
                        'rfc' => $primer->rfc_emisor,
                        'nombre' => $primer->nombre_emisor,
                        'cantidad' => $items->count(),
                        'total' => $items->sum('total')
                    ];
                })
                ->sortByDesc('total')
                ->take(10)
                ->values()
        ];

        return $this->sendResponse($resumen, 'Resumen fiscal obtenido exitosamente');
    }

    /**
     * Conciliar con registros volumétricos
     */
    public function conciliar(Request $request, $id)
    {
        $cfdi = Cfdi::find($id);

        if (!$cfdi) {
            return $this->sendError('CFDI no encontrado');
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

            // Verificar que el registro no tenga ya un CFDI asociado
            if ($registro->cfdi_id) {
                return $this->sendError('El registro volumétrico ya tiene un CFDI asociado', [], 409);
            }

            // Verificar consistencia de montos/volúmenes
            $producto = $registro->producto;
            $volumen = $registro->volumen_corregido;
            
            // Buscar en conceptos del CFDI
            $conceptoRelacionado = null;
            foreach ($cfdi->conceptos as $concepto) {
                if ($concepto['producto_id'] ?? null == $producto->id) {
                    $conceptoRelacionado = $concepto;
                    break;
                }
            }

            $datosAnteriores = $registro->toArray();
            
            $registro->cfdi_id = $cfdi->id;
            
            $metadata = $registro->metadata ?? [];
            $metadata['conciliacion_cfdi'] = [
                'fecha' => now()->toDateTimeString(),
                'usuario_id' => auth()->id(),
                'cfdi_uuid' => $cfdi->uuid,
                'concepto' => $conceptoRelacionado
            ];
            $registro->metadata = $metadata;
            
            $registro->save();

            // Actualizar también el CFDI
            $cfdiMetadata = $cfdi->metadata ?? [];
            $cfdiMetadata['conciliaciones'][] = [
                'fecha' => now()->toDateTimeString(),
                'registro_volumetrico_id' => $registro->id,
                'volumen' => $volumen
            ];
            $cfdi->metadata = $cfdiMetadata;
            $cfdi->save();

            $this->logActivity(
                auth()->id(),
                'fiscal',
                'conciliacion_cfdi',
                'cfdis',
                "CFDI {$cfdi->uuid} conciliado con registro volumétrico {$registro->id}",
                'cfdis',
                $cfdi->id,
                null,
                ['registro_id' => $registro->id, 'volumen' => $volumen]
            );

            DB::commit();

            return $this->sendResponse([
                'cfdi' => $cfdi,
                'registro' => $registro
            ], 'Conciliación realizada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al conciliar CFDI', [$e->getMessage()], 500);
        }
    }

    /**
     * Métodos privados
     */
    private function simularConsultaSat($rfc, $fechaInicio, $fechaFin, $tipo)
    {
        // Simular respuesta del SAT
        $cfdis = [];
        $dias = Carbon::parse($fechaInicio)->diffInDays(Carbon::parse($fechaFin));
        
        for ($i = 0; $i < min($dias, 10); $i++) {
            $fecha = Carbon::parse($fechaInicio)->addDays($i);
            $uuid = sprintf(
                '%s-%s-%s-%s-%s',
                substr(md5($fecha . $i), 0, 8),
                substr(md5($fecha . $i . '1'), 0, 4),
                substr(md5($fecha . $i . '2'), 0, 4),
                substr(md5($fecha . $i . '3'), 0, 4),
                substr(md5($fecha . $i . '4'), 0, 12)
            );
            
            $monto = rand(1000, 100000) / 100;
            
            $cfdis[] = [
                'uuid' => $uuid,
                'folio' => 'F' . str_pad($i + 1, 10, '0', STR_PAD_LEFT),
                'serie' => $tipo == 'EMITIDOS' ? 'E' : 'R',
                'rfc_emisor' => $tipo == 'EMITIDOS' ? $rfc : 'XAXX010101000',
                'nombre_emisor' => $tipo == 'EMITIDOS' ? 'MI EMPRESA SA DE CV' : 'PROVEEDOR SA DE CV',
                'rfc_receptor' => $tipo == 'EMITIDOS' ? 'XAXX010101000' : $rfc,
                'nombre_receptor' => $tipo == 'EMITIDOS' ? 'CLIENTE SA DE CV' : 'MI EMPRESA SA DE CV',
                'tipo_comprobante' => 'I',
                'uso_cfdi' => 'G03',
                'metodo_pago' => 'PUE',
                'forma_pago' => '01',
                'moneda' => 'MXN',
                'fecha' => $fecha->format('Y-m-d H:i:s'),
                'fecha_certificacion' => $fecha->format('Y-m-d H:i:s'),
                'subtotal' => $monto,
                'iva' => $monto * 0.16,
                'total' => $monto * 1.16,
                'conceptos' => [
                    [
                        'clave_prod_serv' => '15101500',
                        'clave_unidad' => 'LTR',
                        'descripcion' => 'GASOLINA MAGNA',
                        'cantidad' => rand(100, 1000),
                        'valor_unitario' => 20,
                        'importe' => rand(2000, 20000)
                    ]
                ]
            ];
        }
        
        return $cfdis;
    }

    private function simularEstatusSat($uuid)
    {
        // Simular diferentes estados
        $estados = ['VIGENTE', 'VIGENTE', 'VIGENTE', 'CANCELADO'];
        $codigos = ['VIG-001', 'CAN-001'];
        
        $estado = $estados[array_rand($estados)];
        
        return [
            'estado' => $estado,
            'codigo' => $estado == 'VIGENTE' ? $codigos[0] : $codigos[1],
            'mensaje' => $estado == 'VIGENTE' ? 'Comprobante vigente' : 'Comprobante cancelado',
            'fecha_consulta' => now()->toIso8601String()
        ];
    }
}