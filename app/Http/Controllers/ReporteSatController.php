<?php

namespace App\Http\Controllers;

use App\Models\ReporteSat;
use App\Models\Instalacion;
use App\Models\RegistroVolumetrico;
use App\Models\Contribuyente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ReporteSatController extends BaseController
{
    /**
     * Listar reportes SAT
     */
    public function index(Request $request)
    {
        $query = ReporteSat::with([
            'instalacion',
            'usuarioGenera'
        ]);

        // Filtros
        if ($request->has('instalacion_id')) {
            $query->where('instalacion_id', $request->instalacion_id);
        }

        if ($request->has('anio')) {
            $query->where('anio', $request->anio);
        }

        if ($request->has('mes')) {
            $query->where('mes', $request->mes);
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('folio_sat')) {
            $query->where('folio_sat', $request->folio_sat);
        }

        $reportes = $query->orderBy('anio', 'desc')
            ->orderBy('mes', 'desc')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($reportes, 'Reportes SAT obtenidos exitosamente');
    }

    /**
     * Generar reporte mensual
     */
    public function generar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'instalacion_id' => 'required|exists:instalaciones,id',
            'anio' => 'required|integer|min:2020|max:2100',
            'mes' => 'required|integer|min:1|max:12'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        // Verificar si ya existe un reporte para el período
        $existente = ReporteSat::where('instalacion_id', $request->instalacion_id)
            ->where('anio', $request->anio)
            ->where('mes', $request->mes)
            ->first();

        if ($existente) {
            return $this->sendError('Ya existe un reporte para este período', [
                'reporte_id' => $existente->id,
                'estado' => $existente->estado
            ], 409);
        }

        try {
            DB::beginTransaction();

            $instalacion = Instalacion::with('contribuyente')->find($request->instalacion_id);
            
            // Obtener datos del período
            $fechaInicio = Carbon::createFromDate($request->anio, $request->mes, 1)->startOfMonth();
            $fechaFin = $fechaInicio->copy()->endOfMonth();

            // Validar que el mes no esté en el futuro
            if ($fechaInicio->isFuture()) {
                return $this->sendError('No se pueden generar reportes de meses futuros', [], 422);
            }

            // Obtener registros volumétricos del período
            $registros = RegistroVolumetrico::where('instalacion_id', $request->instalacion_id)
                ->whereBetween('fecha_operacion', [$fechaInicio, $fechaFin])
                ->with(['producto', 'tanque', 'dictamen', 'cfdi', 'pedimento'])
                ->get();

            if ($registros->isEmpty()) {
                return $this->sendError('No hay registros volumétricos para el período seleccionado', [], 404);
            }

            // Construir datos del reporte
            $datosReporte = $this->construirDatosReporte($instalacion, $registros, $fechaInicio, $fechaFin);

            // Generar archivos en los formatos requeridos por SAT
            $archivos = $this->generarArchivosReporte($datosReporte, $instalacion, $request->anio, $request->mes);

            // Crear registro del reporte
            $reporte = ReporteSat::create([
                'instalacion_id' => $request->instalacion_id,
                'anio' => $request->anio,
                'mes' => $request->mes,
                'fecha_generacion' => now(),
                'usuario_genera_id' => auth()->id(),
                'datos_reporte' => $datosReporte,
                'archivo_pdf' => $archivos['pdf'],
                'archivo_xml' => $archivos['xml'],
                'archivo_json' => $archivos['json'],
                'hash_contenido' => hash('sha256', json_encode($datosReporte)),
                'estado' => 'GENERADO'
            ]);

            $this->logActivity(
                auth()->id(),
                'reportes_sat',
                'generacion_reporte',
                'reportes_sat',
                "Reporte SAT generado para {$instalacion->clave_instalacion} - {$request->anio}/{$request->mes}",
                'reportes_sat',
                $reporte->id
            );

            DB::commit();

            return $this->sendResponse([
                'reporte' => $reporte,
                'resumen' => [
                    'total_registros' => $registros->count(),
                    'volumen_total' => $registros->sum('volumen_corregido'),
                    'por_producto' => $datosReporte['resumen']['por_producto']
                ]
            ], 'Reporte SAT generado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al generar reporte SAT', [$e->getMessage()], 500);
        }
    }

    /**
     * Enviar reporte al SAT
     */
    public function enviar(Request $request, $id)
    {
        $reporte = ReporteSat::find($id);

        if (!$reporte) {
            return $this->sendError('Reporte SAT no encontrado');
        }

        if ($reporte->estado == 'ENVIADO') {
            return $this->sendError('El reporte ya ha sido enviado al SAT', [], 403);
        }

        if ($reporte->estado != 'GENERADO' && $reporte->estado != 'RECHAZADO') {
            return $this->sendError('El reporte no está en estado válido para envío', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'comprobante_firma' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            // Simular envío al SAT (aquí iría la integración real con los servicios del SAT)
            $resultadoEnvio = $this->enviarASAT($reporte);

            $datosAnteriores = $reporte->toArray();

            if ($resultadoEnvio['exitoso']) {
                $reporte->estado = 'ENVIADO';
                $reporte->fecha_envio = now();
                $reporte->folio_sat = $resultadoEnvio['folio_sat'];
                $reporte->usuario_envia_id = auth()->id();
                
                $metadata = $reporte->metadata ?? [];
                $metadata['envio_sat'] = [
                    'fecha' => now()->toDateTimeString(),
                    'respuesta' => $resultadoEnvio['respuesta'],
                    'comprobante' => $request->comprobante_firma
                ];
                $reporte->metadata = $metadata;
                
                $reporte->save();

                $mensaje = 'Reporte enviado al SAT exitosamente';
                $estadoLog = 'envio_exitoso';
            } else {
                $reporte->estado = 'RECHAZADO';
                $reporte->intentos_envio = ($reporte->intentos_envio ?? 0) + 1;
                
                $metadata = $reporte->metadata ?? [];
                $metadata['errores_envio'][] = [
                    'fecha' => now()->toDateTimeString(),
                    'error' => $resultadoEnvio['error']
                ];
                $reporte->metadata = $metadata;
                
                $reporte->save();

                $mensaje = 'Error al enviar reporte al SAT: ' . $resultadoEnvio['error'];
                $estadoLog = 'envio_fallido';
            }

            $this->logActivity(
                auth()->id(),
                'reportes_sat',
                $estadoLog,
                'reportes_sat',
                $mensaje . " - Reporte ID: {$id}",
                'reportes_sat',
                $reporte->id,
                $datosAnteriores,
                $reporte->toArray()
            );

            DB::commit();

            if ($resultadoEnvio['exitoso']) {
                return $this->sendResponse($reporte, $mensaje);
            } else {
                return $this->sendError($mensaje, ['detalle' => $resultadoEnvio['error']], 400);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al enviar reporte al SAT', [$e->getMessage()], 500);
        }
    }

    /**
     * Mostrar reporte
     */
    public function show($id)
    {
        $reporte = ReporteSat::with([
            'instalacion',
            'usuarioGenera',
            'usuarioEnvia'
        ])->find($id);

        if (!$reporte) {
            return $this->sendError('Reporte SAT no encontrado');
        }

        return $this->sendResponse($reporte, 'Reporte SAT obtenido exitosamente');
    }

    /**
     * Descargar archivo del reporte
     */
    public function descargar(Request $request, $id)
    {
        $reporte = ReporteSat::find($id);

        if (!$reporte) {
            return $this->sendError('Reporte SAT no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'formato' => 'required|in:PDF,XML,JSON'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        $campo = 'archivo_' . strtolower($request->formato);
        
        if (!$reporte->$campo || !Storage::exists($reporte->$campo)) {
            return $this->sendError('Archivo no encontrado', [], 404);
        }

        $nombreArchivo = "reporte_sat_{$reporte->instalacion->clave_instalacion}_{$reporte->anio}_{$reporte->mes}." . strtolower($request->formato);

        return Storage::download($reporte->$campo, $nombreArchivo);
    }

    /**
     * Consultar estatus en SAT
     */
    public function consultarEstatus($id)
    {
        $reporte = ReporteSat::find($id);

        if (!$reporte) {
            return $this->sendError('Reporte SAT no encontrado');
        }

        if (!$reporte->folio_sat) {
            return $this->sendError('El reporte no tiene folio SAT asignado', [], 404);
        }

        try {
            // Consultar estatus con el SAT
            $estatusSat = $this->consultarEstatusSAT($reporte->folio_sat);

            return $this->sendResponse([
                'reporte_id' => $reporte->id,
                'folio_sat' => $reporte->folio_sat,
                'estatus_sat' => $estatusSat,
                'fecha_consulta' => now()->toDateTimeString()
            ], 'Estatus consultado exitosamente');

        } catch (\Exception $e) {
            return $this->sendError('Error al consultar estatus en SAT', [$e->getMessage()], 500);
        }
    }

    /**
     * Cancelar reporte
     */
    public function cancelar(Request $request, $id)
    {
        $reporte = ReporteSat::find($id);

        if (!$reporte) {
            return $this->sendError('Reporte SAT no encontrado');
        }

        if ($reporte->estado == 'ENVIADO') {
            return $this->sendError('No se puede cancelar un reporte ya enviado al SAT', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'motivo_cancelacion' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $reporte->toArray();

            $reporte->estado = 'CANCELADO';
            
            $metadata = $reporte->metadata ?? [];
            $metadata['cancelacion'] = [
                'fecha' => now()->toDateTimeString(),
                'usuario_id' => auth()->id(),
                'motivo' => $request->motivo_cancelacion
            ];
            $reporte->metadata = $metadata;
            
            $reporte->save();

            $this->logActivity(
                auth()->id(),
                'reportes_sat',
                'cancelacion_reporte',
                'reportes_sat',
                "Reporte SAT cancelado ID: {$id} - Motivo: {$request->motivo_cancelacion}",
                'reportes_sat',
                $reporte->id,
                $datosAnteriores,
                $reporte->toArray()
            );

            DB::commit();

            return $this->sendResponse($reporte, 'Reporte SAT cancelado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al cancelar reporte SAT', [$e->getMessage()], 500);
        }
    }

    /**
     * Obtener resumen de envíos
     */
    public function resumenEnvios(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contribuyente_id' => 'required|exists:contribuyentes,id',
            'anio' => 'required|integer|min:2020|max:2100'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        $reportes = ReporteSat::whereHas('instalacion', function($q) use ($request) {
                $q->where('contribuyente_id', $request->contribuyente_id);
            })
            ->where('anio', $request->anio)
            ->with('instalacion')
            ->get();

        $resumen = [
            'contribuyente_id' => $request->contribuyente_id,
            'anio' => $request->anio,
            'total_reportes' => $reportes->count(),
            'por_mes' => $reportes->groupBy('mes')
                ->map(function ($items) {
                    return [
                        'cantidad' => $items->count(),
                        'enviados' => $items->where('estado', 'ENVIADO')->count(),
                        'pendientes' => $items->where('estado', 'GENERADO')->count()
                    ];
                }),
            'por_estado' => $reportes->groupBy('estado')
                ->map(function ($items) {
                    return $items->count();
                }),
            'cumplimiento' => [
                'meses_requeridos' => 12,
                'meses_generados' => $reportes->count(),
                'meses_enviados' => $reportes->where('estado', 'ENVIADO')->count(),
                'porcentaje_cumplimiento' => round(($reportes->where('estado', 'ENVIADO')->count() / 12) * 100, 2)
            ]
        ];

        return $this->sendResponse($resumen, 'Resumen de envíos obtenido exitosamente');
    }

    /**
     * Métodos privados
     */
    private function construirDatosReporte($instalacion, $registros, $fechaInicio, $fechaFin)
    {
        $productos = $registros->groupBy('producto_id');

        return [
            'encabezado' => [
                'rfc_contribuyente' => $instalacion->contribuyente->rfc,
                'razon_social' => $instalacion->contribuyente->razon_social,
                'instalacion' => [
                    'clave' => $instalacion->clave_instalacion,
                    'nombre' => $instalacion->nombre,
                    'tipo' => $instalacion->tipo,
                    'domicilio' => $instalacion->domicilio,
                    'permiso_cre' => $instalacion->permiso_cre
                ],
                'periodo' => [
                    'anio' => $fechaInicio->year,
                    'mes' => $fechaInicio->month,
                    'fecha_inicio' => $fechaInicio->format('Y-m-d'),
                    'fecha_fin' => $fechaFin->format('Y-m-d')
                ],
                'fecha_generacion' => now()->toIso8601String(),
                'version_formato' => '1.0'
            ],
            'resumen' => [
                'total_registros' => $registros->count(),
                'volumen_total' => $registros->sum('volumen_corregido'),
                'por_producto' => $productos->map(function ($items) {
                    $producto = $items->first()->producto;
                    return [
                        'producto_id' => $producto->id,
                        'clave_sat' => $producto->clave_sat,
                        'nombre' => $producto->nombre,
                        'tipo' => $producto->tipo,
                        'volumen_total' => $items->sum('volumen_corregido'),
                        'cantidad_operaciones' => $items->count(),
                        'recepciones' => $items->where('tipo_registro', 'RECEPCION')->sum('volumen_corregido'),
                        'entregas' => $items->where('tipo_registro', 'ENTREGA')->sum('volumen_corregido')
                    ];
                })->values()
            ],
            'detalle_por_dia' => $registros->groupBy(function ($item) {
                    return $item->fecha_operacion->format('Y-m-d');
                })
                ->map(function ($items, $fecha) {
                    return [
                        'fecha' => $fecha,
                        'total_registros' => $items->count(),
                        'volumen_total' => $items->sum('volumen_corregido'),
                        'operaciones' => $items->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'hora' => $item->fecha_operacion->format('H:i:s'),
                                'tipo' => $item->tipo_registro,
                                'producto' => $item->producto->nombre,
                                'tanque' => $item->tanque->codigo,
                                'volumen_corregido' => $item->volumen_corregido,
                                'temperatura' => $item->temperatura,
                                'presion' => $item->presion,
                                'densidad' => $item->densidad,
                                'cfdi_uuid' => $item->cfdi ? $item->cfdi->uuid : null,
                                'pedimento' => $item->pedimento ? $item->pedimento->numero : null,
                                'estado' => $item->estado
                            ];
                        })
                    ];
                })->values(),
            'cfdi_asociados' => $registros->whereNotNull('cfdi_id')
                ->groupBy('cfdi_id')
                ->map(function ($items) {
                    $cfdi = $items->first()->cfdi;
                    return [
                        'uuid' => $cfdi->uuid,
                        'rfc_emisor' => $cfdi->rfc_emisor,
                        'rfc_receptor' => $cfdi->rfc_receptor,
                        'fecha' => $cfdi->fecha,
                        'total' => $cfdi->total,
                        'volumen_asociado' => $items->sum('volumen_corregido')
                    ];
                })->values(),
            'pedimentos_asociados' => $registros->whereNotNull('pedimento_id')
                ->groupBy('pedimento_id')
                ->map(function ($items) {
                    $pedimento = $items->first()->pedimento;
                    return [
                        'numero' => $pedimento->numero,
                        'fecha' => $pedimento->fecha_pedimento,
                        'aduana' => $pedimento->aduana,
                        'volumen_asociado' => $items->sum('volumen_corregido')
                    ];
                })->values(),
            'alertas' => [
                'total_registros_con_alarma' => $registros->where('estado', 'CON_ALARMA')->count(),
                'registros_con_alarma' => $registros->where('estado', 'CON_ALARMA')
                    ->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'fecha' => $item->fecha_operacion->toIso8601String(),
                            'producto' => $item->producto->nombre,
                            'volumen' => $item->volumen_corregido
                        ];
                    })->values()
            ],
            'fiscal' => [
                'total_cfdis' => $registros->whereNotNull('cfdi_id')->count(),
                'total_pedimentos' => $registros->whereNotNull('pedimento_id')->count(),
                'volumen_con_cfdi' => $registros->whereNotNull('cfdi_id')->sum('volumen_corregido'),
                'volumen_con_pedimento' => $registros->whereNotNull('pedimento_id')->sum('volumen_corregido')
            ],
            'integridad' => [
                'hash_datos' => hash('sha256', json_encode($registros->toArray())),
                'fecha_creacion' => now()->toIso8601String()
            ]
        ];
    }

    private function generarArchivosReporte($datos, $instalacion, $anio, $mes)
    {
        $basePath = "reportes_sat/{$instalacion->contribuyente->rfc}/{$instalacion->clave_instalacion}/{$anio}/{$mes}";
        
        // Crear directorio si no existe
        Storage::makeDirectory($basePath);

        // Generar JSON
        $jsonPath = "{$basePath}/reporte.json";
        Storage::put($jsonPath, json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Generar XML (formato SAT)
        $xmlPath = "{$basePath}/reporte.xml";
        $xml = $this->generarXMLSAT($datos, $instalacion);
        Storage::put($xmlPath, $xml);

        // Generar PDF
        $pdfPath = "{$basePath}/reporte.pdf";
        $pdf = $this->generarPDFReporte($datos, $instalacion);
        Storage::put($pdfPath, $pdf);

        return [
            'json' => $jsonPath,
            'xml' => $xmlPath,
            'pdf' => $pdfPath
        ];
    }

    private function generarXMLSAT($datos, $instalacion)
    {
        // Implementar generación de XML según especificaciones del SAT
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        
        $xml->startDocument('1.0', 'UTF-8');
        
        // Raíz del documento
        $xml->startElement('ReporteVolumetrico');
        $xml->writeAttribute('xmlns', 'http://www.sat.gob.mx/reportevolumetrico');
        $xml->writeAttribute('version', '1.0');
        
        // Encabezado
        $xml->startElement('Encabezado');
        $xml->writeElement('RFC', $datos['encabezado']['rfc_contribuyente']);
        $xml->writeElement('RazonSocial', $datos['encabezado']['razon_social']);
        $xml->writeElement('InstalacionClave', $datos['encabezado']['instalacion']['clave']);
        $xml->writeElement('InstalacionNombre', $datos['encabezado']['instalacion']['nombre']);
        $xml->writeElement('PeriodoAnio', $datos['encabezado']['periodo']['anio']);
        $xml->writeElement('PeriodoMes', $datos['encabezado']['periodo']['mes']);
        $xml->endElement(); // Encabezado
        
        // Resumen
        $xml->startElement('Resumen');
        $xml->writeElement('TotalRegistros', $datos['resumen']['total_registros']);
        $xml->writeElement('VolumenTotal', number_format($datos['resumen']['volumen_total'], 4, '.', ''));
        
        foreach ($datos['resumen']['por_producto'] as $producto) {
            $xml->startElement('Producto');
            $xml->writeElement('ClaveSAT', $producto['clave_sat']);
            $xml->writeElement('Nombre', $producto['nombre']);
            $xml->writeElement('VolumenTotal', number_format($producto['volumen_total'], 4, '.', ''));
            $xml->writeElement('Recepciones', number_format($producto['recepciones'], 4, '.', ''));
            $xml->writeElement('Entregas', number_format($producto['entregas'], 4, '.', ''));
            $xml->endElement();
        }
        
        $xml->endElement(); // Resumen
        
        // Operaciones diarias
        $xml->startElement('OperacionesDiarias');
        foreach ($datos['detalle_por_dia'] as $dia) {
            $xml->startElement('Dia');
            $xml->writeAttribute('fecha', $dia['fecha']);
            $xml->writeElement('TotalRegistros', $dia['total_registros']);
            $xml->writeElement('VolumenTotal', number_format($dia['volumen_total'], 4, '.', ''));
            
            foreach ($dia['operaciones'] as $operacion) {
                $xml->startElement('Operacion');
                $xml->writeElement('Hora', $operacion['hora']);
                $xml->writeElement('Tipo', $operacion['tipo']);
                $xml->writeElement('Producto', $operacion['producto']);
                $xml->writeElement('Tanque', $operacion['tanque']);
                $xml->writeElement('Volumen', number_format($operacion['volumen_corregido'], 4, '.', ''));
                if ($operacion['cfdi_uuid']) {
                    $xml->writeElement('CFDI_UUID', $operacion['cfdi_uuid']);
                }
                $xml->endElement();
            }
            
            $xml->endElement(); // Dia
        }
        $xml->endElement(); // OperacionesDiarias
        
        $xml->endElement(); // ReporteVolumetrico
        $xml->endDocument();
        
        return $xml->outputMemory();
    }

    private function generarPDFReporte($datos, $instalacion)
    {
        // Implementar generación de PDF con dompdf
        $pdf = \PDF::loadView('reportes.sat-pdf', [
            'datos' => $datos,
            'instalacion' => $instalacion,
            'fecha_generacion' => now()
        ]);
        
        return $pdf->output();
    }

    private function enviarASAT($reporte)
    {
        try {
            // Aquí iría la implementación real del web service del SAT
            // Por ahora simulamos una respuesta exitosa
            
            // Simular llamada a WS del SAT
            $exitoso = true; // Simulado
            $folioSAT = 'SAT-' . date('Ymd') . '-' . str_pad($reporte->id, 10, '0', STR_PAD_LEFT);
            
            return [
                'exitoso' => $exitoso,
                'folio_sat' => $exitoso ? $folioSAT : null,
                'respuesta' => $exitoso ? ['codigo' => '00', 'mensaje' => 'Recibido correctamente'] : null,
                'error' => $exitoso ? null : 'Error de conexión con el SAT'
            ];
            
        } catch (\Exception $e) {
            return [
                'exitoso' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function consultarEstatusSAT($folioSAT)
    {
        // Simular consulta de estatus en SAT
        $estatusPosibles = [
            'RECIBIDO',
            'EN_PROCESO',
            'VALIDADO',
            'RECHAZADO',
            'ACEPTADO'
        ];
        
        return [
            'folio_sat' => $folioSAT,
            'estatus' => $estatusPosibles[array_rand($estatusPosibles)],
            'fecha_consulta' => now()->toIso8601String(),
            'detalle' => 'Consulta simulada para desarrollo'
        ];
    }
}