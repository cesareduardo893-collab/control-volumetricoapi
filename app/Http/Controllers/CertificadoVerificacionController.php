<?php

namespace App\Http\Controllers;

use App\Models\CertificadoVerificacion;
use App\Models\Contribuyente;
use App\Models\Instalacion;
use App\Models\Tanque;
use App\Models\Medidor;
use App\Models\Verificador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CertificadoVerificacionController extends BaseController
{
    /**
     * Listar certificados de verificación
     */
    public function index(Request $request)
    {
        $query = CertificadoVerificacion::with([
            'contribuyente',
            'instalacion',
            'verificador',
            'usuarioRegistro'
        ]);

        // Filtros
        if ($request->has('contribuyente_id')) {
            $query->where('contribuyente_id', $request->contribuyente_id);
        }

        if ($request->has('instalacion_id')) {
            $query->where('instalacion_id', $request->instalacion_id);
        }

        if ($request->has('verificador_id')) {
            $query->where('verificador_id', $request->verificador_id);
        }

        if ($request->has('folio')) {
            $query->where('folio', 'LIKE', "%{$request->folio}%");
        }

        if ($request->has('tipo_verificacion')) {
            $query->where('tipo_verificacion', $request->tipo_verificacion);
        }

        if ($request->has('resultado')) {
            $query->where('resultado', $request->resultado);
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('fecha_emision_inicio')) {
            $query->where('fecha_emision', '>=', Carbon::parse($request->fecha_emision_inicio));
        }

        if ($request->has('fecha_emision_fin')) {
            $query->where('fecha_emision', '<=', Carbon::parse($request->fecha_emision_fin));
        }

        if ($request->has('fecha_vencimiento_inicio')) {
            $query->where('fecha_vencimiento', '>=', Carbon::parse($request->fecha_vencimiento_inicio));
        }

        if ($request->has('fecha_vencimiento_fin')) {
            $query->where('fecha_vencimiento', '<=', Carbon::parse($request->fecha_vencimiento_fin));
        }

        if ($request->boolean('vigente')) {
            $query->where('fecha_vencimiento', '>=', now())
                  ->where('estado', 'VIGENTE')
                  ->where('resultado', 'ACREDITADO');
        }

        if ($request->boolean('proximo_vencer')) {
            $query->whereBetween('fecha_vencimiento', [now(), now()->addDays(60)])
                  ->where('estado', 'VIGENTE');
        }

        $certificados = $query->orderBy('fecha_emision', 'desc')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($certificados, 'Certificados de verificación obtenidos exitosamente');
    }

    /**
     * Crear certificado de verificación
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contribuyente_id' => 'required|exists:contribuyentes,id',
            'instalacion_id' => 'required|exists:instalaciones,id',
            'verificador_id' => 'required|exists:verificadores,id',
            'folio' => 'required|string|max:50|unique:certificados_verificacion,folio',
            'tipo_verificacion' => 'required|in:INICIAL,PERIODICA,EXTRAORDINARIA,VERIFICACION_SISTEMA',
            'fecha_verificacion' => 'required|date',
            'fecha_emision' => 'required|date|after_or_equal:fecha_verificacion',
            'fecha_vencimiento' => 'required|date|after:fecha_emision',
            'alcance' => 'required|string|max:1000',
            'normas_aplicables' => 'required|array',
            'normas_aplicables.*' => 'string',
            'equipos_verificados' => 'required|array',
            'equipos_verificados.tanques' => 'nullable|array',
            'equipos_verificados.tanques.*.id' => 'required_with:equipos_verificados.tanques|exists:tanques,id',
            'equipos_verificados.tanques.*.resultado' => 'required_with:equipos_verificados.tanques|in:CONFORME,NO_CONFORME,NO_APLICA',
            'equipos_verificados.medidores' => 'nullable|array',
            'equipos_verificados.medidores.*.id' => 'required_with:equipos_verificados.medidores|exists:medidores,id',
            'equipos_verificados.medidores.*.resultado' => 'required_with:equipos_verificados.medidores|in:CONFORME,NO_CONFORME,NO_APLICA',
            'equipos_verificados.sistemas' => 'nullable|array',
            'equipos_verificados.sistemas.*.nombre' => 'required_with:equipos_verificados.sistemas|string',
            'equipos_verificados.sistemas.*.resultado' => 'required_with:equipos_verificados.sistemas|in:CONFORME,NO_CONFORME,NO_APLICA',
            'puntos_verificacion' => 'required|array',
            'puntos_verificacion.*.punto' => 'required|string',
            'puntos_verificacion.*.descripcion' => 'required|string',
            'puntos_verificacion.*.resultado' => 'required|in:CONFORME,NO_CONFORME,NO_APLICA',
            'puntos_verificacion.*.observaciones' => 'nullable|string',
            'puntos_verificacion.*.evidencia' => 'nullable|string',
            'no_conformidades' => 'nullable|array',
            'no_conformidades.*.descripcion' => 'required_with:no_conformidades|string',
            'no_conformidades.*.clasificacion' => 'required_with:no_conformidades|in:MAYOR,MENOR,OBSERVACION',
            'no_conformidades.*.plazo_solucion' => 'nullable|date',
            'no_conformidades.*.acciones_correctivas' => 'nullable|string',
            'no_conformidades.*.responsable' => 'nullable|string',
            'recomendaciones' => 'nullable|array',
            'recomendaciones.*' => 'string',
            'resultado' => 'required|in:ACREDITADO,NO_ACREDITADO,ACREDITADO_CONDICIONADO',
            'observaciones' => 'nullable|string|max:1000',
            'conclusiones' => 'nullable|string|max:1000',
            'documentos' => 'nullable|array',
            'documentos.*.tipo' => 'required_with:documentos|string',
            'documentos.*.descripcion' => 'required_with:documentos|string',
            'archivo_certificado' => 'nullable|file|mimes:pdf|max:10240',
            'archivos_anexos' => 'nullable|array',
            'archivos_anexos.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,jpg,png|max:10240',
            'estado' => 'required|in:VIGENTE,VENCIDO,SUSPENDIDO,CANCELADO',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            // Validar que la instalación pertenezca al contribuyente
            $instalacion = Instalacion::find($request->instalacion_id);
            if ($instalacion->contribuyente_id != $request->contribuyente_id) {
                return $this->sendError('La instalación no pertenece al contribuyente especificado', [], 422);
            }

            // Guardar archivo principal
            $rutaArchivo = null;
            if ($request->hasFile('archivo_certificado')) {
                $rutaArchivo = $request->file('archivo_certificado')
                    ->store("certificados/verificacion/{$request->contribuyente_id}", 'public');
            }

            // Guardar archivos anexos
            $anexos = [];
            if ($request->hasFile('archivos_anexos')) {
                foreach ($request->file('archivos_anexos') as $index => $archivo) {
                    $ruta = $archivo->store("certificados/verificacion/{$request->contribuyente_id}/anexos", 'public');
                    $anexos[] = [
                        'nombre_original' => $archivo->getClientOriginalName(),
                        'ruta' => $ruta,
                        'tipo' => $archivo->getMimeType(),
                        'tamano' => $archivo->getSize()
                    ];
                }
            }

            $certificado = CertificadoVerificacion::create([
                'contribuyente_id' => $request->contribuyente_id,
                'instalacion_id' => $request->instalacion_id,
                'verificador_id' => $request->verificador_id,
                'folio' => $request->folio,
                'tipo_verificacion' => $request->tipo_verificacion,
                'fecha_verificacion' => $request->fecha_verificacion,
                'fecha_emision' => $request->fecha_emision,
                'fecha_vencimiento' => $request->fecha_vencimiento,
                'alcance' => $request->alcance,
                'normas_aplicables' => $request->normas_aplicables,
                'equipos_verificados' => $request->equipos_verificados,
                'puntos_verificacion' => $request->puntos_verificacion,
                'no_conformidades' => $request->no_conformidades,
                'recomendaciones' => $request->recomendaciones,
                'resultado' => $request->resultado,
                'observaciones' => $request->observaciones,
                'conclusiones' => $request->conclusiones,
                'documentos' => $request->documentos,
                'archivo_certificado' => $rutaArchivo,
                'archivos_anexos' => $anexos,
                'estado' => $request->estado,
                'usuario_registro_id' => auth()->id(),
                'metadata' => $request->metadata
            ]);

            $this->logActivity(
                auth()->id(),
                'certificacion',
                'creacion_certificado_verificacion',
                'certificados_verificacion',
                "Certificado de verificación creado: {$certificado->folio} - Resultado: {$certificado->resultado}",
                'certificados_verificacion',
                $certificado->id
            );

            DB::commit();

            return $this->sendResponse($certificado->load(['contribuyente', 'instalacion', 'verificador']), 
                'Certificado de verificación creado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al crear certificado de verificación', [$e->getMessage()], 500);
        }
    }

    /**
     * Mostrar certificado de verificación
     */
    public function show($id)
    {
        $certificado = CertificadoVerificacion::with([
            'contribuyente',
            'instalacion',
            'verificador',
            'usuarioRegistro',
            'accionesCorrectivas' => function($q) {
                $q->orderBy('fecha_registro', 'desc');
            }
        ])->find($id);

        if (!$certificado) {
            return $this->sendError('Certificado de verificación no encontrado');
        }

        return $this->sendResponse($certificado, 'Certificado de verificación obtenido exitosamente');
    }

    /**
     * Actualizar certificado de verificación
     */
    public function update(Request $request, $id)
    {
        $certificado = CertificadoVerificacion::find($id);

        if (!$certificado) {
            return $this->sendError('Certificado de verificación no encontrado');
        }

        if ($certificado->estado == 'CANCELADO') {
            return $this->sendError('No se puede modificar un certificado cancelado', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'no_conformidades' => 'nullable|array',
            'recomendaciones' => 'nullable|array',
            'observaciones' => 'nullable|string|max:1000',
            'conclusiones' => 'nullable|string|max:1000',
            'documentos' => 'nullable|array',
            'estado' => 'sometimes|in:VIGENTE,VENCIDO,SUSPENDIDO,CANCELADO',
            'archivo_certificado' => 'nullable|file|mimes:pdf|max:10240',
            'archivos_anexos' => 'nullable|array',
            'archivos_anexos.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,jpg,png|max:10240',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $certificado->toArray();

            // Actualizar archivo si se proporciona
            if ($request->hasFile('archivo_certificado')) {
                $rutaArchivo = $request->file('archivo_certificado')
                    ->store("certificados/verificacion/{$certificado->contribuyente_id}", 'public');
                $certificado->archivo_certificado = $rutaArchivo;
            }

            // Actualizar anexos si se proporcionan
            if ($request->hasFile('archivos_anexos')) {
                $anexos = $certificado->archivos_anexos ?? [];
                foreach ($request->file('archivos_anexos') as $archivo) {
                    $ruta = $archivo->store("certificados/verificacion/{$certificado->contribuyente_id}/anexos", 'public');
                    $anexos[] = [
                        'nombre_original' => $archivo->getClientOriginalName(),
                        'ruta' => $ruta,
                        'tipo' => $archivo->getMimeType(),
                        'tamano' => $archivo->getSize(),
                        'fecha' => now()->toDateTimeString()
                    ];
                }
                $certificado->archivos_anexos = $anexos;
            }

            $certificado->no_conformidades = $request->no_conformidades ?? $certificado->no_conformidades;
            $certificado->recomendaciones = $request->recomendaciones ?? $certificado->recomendaciones;
            $certificado->observaciones = $request->observaciones ?? $certificado->observaciones;
            $certificado->conclusiones = $request->conclusiones ?? $certificado->conclusiones;
            $certificado->documentos = $request->documentos ?? $certificado->documentos;
            $certificado->estado = $request->estado ?? $certificado->estado;
            
            if ($request->has('metadata')) {
                $metadata = array_merge($certificado->metadata ?? [], $request->metadata);
                $certificado->metadata = $metadata;
            }
            
            $certificado->save();

            $this->logActivity(
                auth()->id(),
                'certificacion',
                'actualizacion_certificado_verificacion',
                'certificados_verificacion',
                "Certificado de verificación actualizado: {$certificado->folio}",
                'certificados_verificacion',
                $certificado->id,
                $datosAnteriores,
                $certificado->toArray()
            );

            DB::commit();

            return $this->sendResponse($certificado, 'Certificado de verificación actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al actualizar certificado de verificación', [$e->getMessage()], 500);
        }
    }

    /**
     * Cancelar certificado de verificación
     */
    public function cancelar(Request $request, $id)
    {
        $certificado = CertificadoVerificacion::find($id);

        if (!$certificado) {
            return $this->sendError('Certificado de verificación no encontrado');
        }

        if ($certificado->estado == 'CANCELADO') {
            return $this->sendError('El certificado ya está cancelado', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'motivo_cancelacion' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $certificado->toArray();

            $certificado->estado = 'CANCELADO';
            
            $metadata = $certificado->metadata ?? [];
            $metadata['cancelacion'] = [
                'fecha' => now()->toDateTimeString(),
                'usuario_id' => auth()->id(),
                'motivo' => $request->motivo_cancelacion
            ];
            $certificado->metadata = $metadata;
            
            $certificado->save();

            $this->logActivity(
                auth()->id(),
                'certificacion',
                'cancelacion_certificado_verificacion',
                'certificados_verificacion',
                "Certificado de verificación cancelado: {$certificado->folio} - Motivo: {$request->motivo_cancelacion}",
                'certificados_verificacion',
                $certificado->id,
                $datosAnteriores,
                $certificado->toArray()
            );

            DB::commit();

            return $this->sendResponse($certificado, 'Certificado de verificación cancelado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al cancelar certificado de verificación', [$e->getMessage()], 500);
        }
    }

    /**
     * Registrar acción correctiva
     */
    public function registrarAccionCorrectiva(Request $request, $id)
    {
        $certificado = CertificadoVerificacion::find($id);

        if (!$certificado) {
            return $this->sendError('Certificado de verificación no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'no_conformidad_index' => 'required|integer|min:0',
            'accion_tomada' => 'required|string|max:1000',
            'fecha_ejecucion' => 'required|date',
            'responsable' => 'required|string|max:255',
            'evidencia' => 'nullable|string|max:500',
            'archivo_evidencia' => 'nullable|file|mimes:pdf,jpg,png|max:5120',
            'observaciones' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            // Verificar que el índice de no conformidad existe
            if (!isset($certificado->no_conformidades[$request->no_conformidad_index])) {
                return $this->sendError('No conformidad no encontrada', [], 404);
            }

            // Guardar archivo de evidencia
            $rutaArchivo = null;
            if ($request->hasFile('archivo_evidencia')) {
                $rutaArchivo = $request->file('archivo_evidencia')
                    ->store("certificados/verificacion/{$certificado->contribuyente_id}/acciones", 'public');
            }

            $accion = [
                'fecha_registro' => now()->toDateTimeString(),
                'usuario_id' => auth()->id(),
                'no_conformidad_index' => $request->no_conformidad_index,
                'accion_tomada' => $request->accion_tomada,
                'fecha_ejecucion' => $request->fecha_ejecucion,
                'responsable' => $request->responsable,
                'evidencia' => $request->evidencia,
                'archivo' => $rutaArchivo,
                'observaciones' => $request->observaciones
            ];

            $acciones = $certificado->acciones_correctivas ?? [];
            $acciones[] = $accion;
            $certificado->acciones_correctivas = $acciones;

            // Actualizar el estado de la no conformidad
            $noConformidades = $certificado->no_conformidades;
            $noConformidades[$request->no_conformidad_index]['fecha_solucion'] = $request->fecha_ejecucion;
            $noConformidades[$request->no_conformidad_index]['estado'] = 'SOLUCIONADA';
            $certificado->no_conformidades = $noConformidades;

            $certificado->save();

            $this->logActivity(
                auth()->id(),
                'certificacion',
                'registro_accion_correctiva',
                'certificados_verificacion',
                "Acción correctiva registrada para certificado {$certificado->folio}",
                'certificados_verificacion',
                $certificado->id
            );

            DB::commit();

            return $this->sendResponse($accion, 'Acción correctiva registrada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al registrar acción correctiva', [$e->getMessage()], 500);
        }
    }

    /**
     * Descargar archivo del certificado
     */
    public function descargar($id)
    {
        $certificado = CertificadoVerificacion::find($id);

        if (!$certificado) {
            return $this->sendError('Certificado de verificación no encontrado');
        }

        if (!$certificado->archivo_certificado || !Storage::disk('public')->exists($certificado->archivo_certificado)) {
            return $this->sendError('Archivo no encontrado', [], 404);
        }

        $nombreArchivo = "certificado_verificacion_{$certificado->folio}.pdf";

        return Storage::disk('public')->download($certificado->archivo_certificado, $nombreArchivo);
    }

    /**
     * Verificar vigencia del certificado
     */
    public function verificarVigencia($id)
    {
        $certificado = CertificadoVerificacion::find($id);

        if (!$certificado) {
            return $this->sendError('Certificado de verificación no encontrado');
        }

        $hoy = Carbon::now();
        $vigente = $certificado->estado == 'VIGENTE' && 
                   $certificado->fecha_vencimiento >= $hoy && 
                   $certificado->resultado == 'ACREDITADO';

        $diasRestantes = $vigente ? $hoy->diffInDays($certificado->fecha_vencimiento, false) : 0;
        $estadoVigencia = $this->determinarEstadoVigencia($certificado, $hoy);

        $resultado = [
            'certificado_id' => $certificado->id,
            'folio' => $certificado->folio,
            'contribuyente' => $certificado->contribuyente->razon_social,
            'instalacion' => $certificado->instalacion->nombre,
            'fecha_verificacion' => $certificado->fecha_verificacion->format('Y-m-d'),
            'fecha_emision' => $certificado->fecha_emision->format('Y-m-d'),
            'fecha_vencimiento' => $certificado->fecha_vencimiento->format('Y-m-d'),
            'dias_restantes' => $diasRestantes,
            'vigente' => $vigente,
            'estado' => $estadoVigencia,
            'resultado_verificacion' => $certificado->resultado,
            'estado_certificado' => $certificado->estado,
            'fecha_consulta' => $hoy->toDateTimeString(),
            'alertas' => $this->generarAlertasVigencia($certificado, $hoy)
        ];

        return $this->sendResponse($resultado, 'Vigencia del certificado verificada exitosamente');
    }

    /**
     * Obtener estadísticas de certificados
     */
    public function estadisticas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contribuyente_id' => 'required|exists:contribuyentes,id',
            'anio' => 'required|integer|min:2020|max:2100'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        $certificados = CertificadoVerificacion::where('contribuyente_id', $request->contribuyente_id)
            ->whereYear('fecha_emision', $request->anio)
            ->get();

        $hoy = Carbon::now();

        $estadisticas = [
            'contribuyente_id' => $request->contribuyente_id,
            'anio' => $request->anio,
            'fecha_calculo' => $hoy->toDateTimeString(),
            'resumen' => [
                'total_certificados' => $certificados->count(),
                'por_tipo' => $certificados->groupBy('tipo_verificacion')
                    ->map(function ($items) {
                        return [
                            'cantidad' => $items->count(),
                            'acreditados' => $items->where('resultado', 'ACREDITADO')->count()
                        ];
                    }),
                'por_resultado' => $certificados->groupBy('resultado')
                    ->map(function ($items) {
                        return [
                            'cantidad' => $items->count(),
                            'porcentaje' => round(($items->count() / max($certificados->count(), 1)) * 100, 2)
                        ];
                    }),
                'por_estado' => $certificados->groupBy('estado')
                    ->map(function ($items) {
                        return $items->count();
                    })
            ],
            'vigencia' => [
                'vigentes' => $certificados->where('estado', 'VIGENTE')
                    ->where('fecha_vencimiento', '>=', $hoy)
                    ->count(),
                'vencidos' => $certificados->where(function($q) use ($hoy) {
                        return $q->where('fecha_vencimiento', '<', $hoy)
                                 ->orWhere('estado', 'VENCIDO');
                    })->count(),
                'por_vencer_30d' => $certificados->where('estado', 'VIGENTE')
                    ->whereBetween('fecha_vencimiento', [$hoy, $hoy->copy()->addDays(30)])
                    ->count(),
                'por_vencer_60d' => $certificados->where('estado', 'VIGENTE')
                    ->whereBetween('fecha_vencimiento', [$hoy, $hoy->copy()->addDays(60)])
                    ->count()
            ],
            'no_conformidades' => [
                'total' => $certificados->sum(function($c) {
                    return count($c->no_conformidades ?? []);
                }),
                'pendientes' => $certificados->sum(function($c) {
                    $noConformidades = $c->no_conformidades ?? [];
                    return collect($noConformidades)->whereNotIn('estado', ['SOLUCIONADA', 'CERRADA'])->count();
                }),
                'por_clasificacion' => $this->agruparNoConformidades($certificados)
            ],
            'tendencia_mensual' => $certificados->groupBy(function ($item) {
                    return Carbon::parse($item->fecha_emision)->format('Y-m');
                })
                ->map(function ($items, $mes) {
                    return [
                        'mes' => $mes,
                        'total' => $items->count(),
                        'acreditados' => $items->where('resultado', 'ACREDITADO')->count()
                    ];
                })->values()
        ];

        return $this->sendResponse($estadisticas, 'Estadísticas de certificados obtenidas exitosamente');
    }

    /**
     * Renovar certificado
     */
    public function renovar(Request $request, $id)
    {
        $certificadoAnterior = CertificadoVerificacion::find($id);

        if (!$certificadoAnterior) {
            return $this->sendError('Certificado de verificación no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'fecha_verificacion' => 'required|date',
            'fecha_emision' => 'required|date|after_or_equal:fecha_verificacion',
            'folio' => 'required|string|max:50|unique:certificados_verificacion,folio',
            'resultado' => 'required|in:ACREDITADO,NO_ACREDITADO,ACREDITADO_CONDICIONADO',
            'no_conformidades' => 'nullable|array',
            'recomendaciones' => 'nullable|array',
            'observaciones' => 'nullable|string|max:1000',
            'archivo_certificado' => 'nullable|file|mimes:pdf|max:10240'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            // Marcar certificado anterior como renovado
            $metadataAnterior = $certificadoAnterior->metadata ?? [];
            $metadataAnterior['renovacion'] = [
                'fecha' => now()->toDateTimeString(),
                'nuevo_folio' => $request->folio,
                'usuario_id' => auth()->id()
            ];
            $certificadoAnterior->metadata = $metadataAnterior;
            $certificadoAnterior->estado = 'RENOVADO';
            $certificadoAnterior->save();

            // Guardar archivo
            $rutaArchivo = null;
            if ($request->hasFile('archivo_certificado')) {
                $rutaArchivo = $request->file('archivo_certificado')
                    ->store("certificados/verificacion/{$certificadoAnterior->contribuyente_id}", 'public');
            }

            // Crear nuevo certificado
            $nuevoCertificado = CertificadoVerificacion::create([
                'contribuyente_id' => $certificadoAnterior->contribuyente_id,
                'instalacion_id' => $certificadoAnterior->instalacion_id,
                'verificador_id' => $certificadoAnterior->verificador_id,
                'folio' => $request->folio,
                'tipo_verificacion' => 'PERIODICA',
                'fecha_verificacion' => $request->fecha_verificacion,
                'fecha_emision' => $request->fecha_emision,
                'fecha_vencimiento' => Carbon::parse($request->fecha_emision)->addYear(),
                'alcance' => $certificadoAnterior->alcance,
                'normas_aplicables' => $certificadoAnterior->normas_aplicables,
                'equipos_verificados' => $certificadoAnterior->equipos_verificados,
                'puntos_verificacion' => $request->puntos_verificacion ?? $certificadoAnterior->puntos_verificacion,
                'no_conformidades' => $request->no_conformidades,
                'recomendaciones' => $request->recomendaciones,
                'resultado' => $request->resultado,
                'observaciones' => $request->observaciones,
                'archivo_certificado' => $rutaArchivo,
                'estado' => 'VIGENTE',
                'usuario_registro_id' => auth()->id(),
                'metadata' => [
                    'certificado_anterior' => [
                        'id' => $certificadoAnterior->id,
                        'folio' => $certificadoAnterior->folio
                    ]
                ]
            ]);

            $this->logActivity(
                auth()->id(),
                'certificacion',
                'renovacion_certificado',
                'certificados_verificacion',
                "Certificado renovado: {$certificadoAnterior->folio} -> {$nuevoCertificado->folio}",
                'certificados_verificacion',
                $nuevoCertificado->id
            );

            DB::commit();

            return $this->sendResponse([
                'certificado_anterior' => $certificadoAnterior,
                'nuevo_certificado' => $nuevoCertificado
            ], 'Certificado renovado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al renovar certificado', [$e->getMessage()], 500);
        }
    }

    /**
     * Obtener historial de certificados por instalación
     */
    public function historialInstalacion($instalacionId)
    {
        $instalacion = Instalacion::find($instalacionId);

        if (!$instalacion) {
            return $this->sendError('Instalación no encontrada');
        }

        $certificados = CertificadoVerificacion::where('instalacion_id', $instalacionId)
            ->with(['verificador', 'usuarioRegistro'])
            ->orderBy('fecha_emision', 'desc')
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'folio' => $c->folio,
                    'tipo' => $c->tipo_verificacion,
                    'fecha_emision' => $c->fecha_emision->format('Y-m-d'),
                    'fecha_vencimiento' => $c->fecha_vencimiento->format('Y-m-d'),
                    'resultado' => $c->resultado,
                    'estado' => $c->estado,
                    'verificador' => $c->verificador ? $c->verificador->nombre : null,
                    'no_conformidades' => count($c->no_conformidades ?? []),
                    'vigente' => $c->estado == 'VIGENTE' && $c->fecha_vencimiento >= Carbon::now()
                ];
            });

        $resumen = [
            'instalacion' => [
                'id' => $instalacion->id,
                'clave' => $instalacion->clave_instalacion,
                'nombre' => $instalacion->nombre
            ],
            'total_certificados' => $certificados->count(),
            'certificado_vigente' => $certificados->firstWhere('vigente', true),
            'historial' => $certificados
        ];

        return $this->sendResponse($resumen, 'Historial de certificados obtenido exitosamente');
    }

    /**
     * Generar reporte de auditoría
     */
    public function reporteAuditoria(Request $request, $id)
    {
        $certificado = CertificadoVerificacion::with([
            'contribuyente',
            'instalacion',
            'verificador',
            'usuarioRegistro'
        ])->find($id);

        if (!$certificado) {
            return $this->sendError('Certificado de verificación no encontrado');
        }

        $reporte = [
            'encabezado' => [
                'folio' => $certificado->folio,
                'tipo_verificacion' => $certificado->tipo_verificacion,
                'fecha_emision' => $certificado->fecha_emision->format('Y-m-d'),
                'fecha_vencimiento' => $certificado->fecha_vencimiento->format('Y-m-d'),
                'resultado' => $certificado->resultado,
                'estado' => $certificado->estado
            ],
            'contribuyente' => [
                'rfc' => $certificado->contribuyente->rfc,
                'razon_social' => $certificado->contribuyente->razon_social
            ],
            'instalacion' => [
                'clave' => $certificado->instalacion->clave_instalacion,
                'nombre' => $certificado->instalacion->nombre,
                'domicilio' => $certificado->instalacion->domicilio
            ],
            'verificador' => [
                'nombre' => $certificado->verificador->nombre,
                'cedula' => $certificado->verificador->cedula_profesional,
                'empresa' => $certificado->verificador->empresa
            ],
            'alcance' => $certificado->alcance,
            'normas_aplicables' => $certificado->normas_aplicables,
            'equipos_verificados' => $this->formatearEquiposVerificados($certificado->equipos_verificados),
            'puntos_verificacion' => $certificado->puntos_verificacion,
            'no_conformidades' => $certificado->no_conformidades,
            'recomendaciones' => $certificado->recomendaciones,
            'acciones_correctivas' => $certificado->acciones_correctivas,
            'documentos' => $certificado->documentos,
            'observaciones' => $certificado->observaciones,
            'conclusiones' => $certificado->conclusiones,
            'metadata' => $certificado->metadata,
            'fecha_generacion_reporte' => now()->toDateTimeString()
        ];

        return $this->sendResponse($reporte, 'Reporte de auditoría generado exitosamente');
    }

    /**
     * Métodos privados
     */
    private function determinarEstadoVigencia($certificado, $hoy)
    {
        if ($certificado->estado != 'VIGENTE') {
            return 'NO_VIGENTE';
        }

        if ($certificado->fecha_vencimiento < $hoy) {
            return 'VENCIDO';
        }

        $diasRestantes = $hoy->diffInDays($certificado->fecha_vencimiento, false);

        if ($diasRestantes <= 30) {
            return 'POR_VENCER';
        } elseif ($diasRestantes <= 60) {
            return 'VIGENTE_PROXIMO_VENCER';
        } else {
            return 'VIGENTE';
        }
    }

    private function generarAlertasVigencia($certificado, $hoy)
    {
        $alertas = [];

        if ($certificado->estado != 'VIGENTE') {
            $alertas[] = [
                'tipo' => 'ESTADO_NO_VIGENTE',
                'severidad' => 'CRITICA',
                'mensaje' => "El certificado no se encuentra en estado VIGENTE (Estado actual: {$certificado->estado})"
            ];
        }

        if ($certificado->resultado != 'ACREDITADO') {
            $alertas[] = [
                'tipo' => 'RESULTADO_NO_ACREDITADO',
                'severidad' => 'CRITICA',
                'mensaje' => "El certificado no fue acreditado (Resultado: {$certificado->resultado})"
            ];
        }

        if ($certificado->fecha_vencimiento < $hoy) {
            $alertas[] = [
                'tipo' => 'VENCIDO',
                'severidad' => 'CRITICA',
                'mensaje' => "El certificado venció el {$certificado->fecha_vencimiento->format('d/m/Y')}"
            ];
        } else {
            $diasRestantes = $hoy->diffInDays($certificado->fecha_vencimiento, false);
            
            if ($diasRestantes <= 15) {
                $alertas[] = [
                    'tipo' => 'VENCE_INMEDIATO',
                    'severidad' => 'ALTA',
                    'mensaje' => "El certificado vence en {$diasRestantes} días"
                ];
            } elseif ($diasRestantes <= 30) {
                $alertas[] = [
                    'tipo' => 'VENCE_PROXIMO',
                    'severidad' => 'MEDIA',
                    'mensaje' => "El certificado vence en {$diasRestantes} días"
                ];
            } elseif ($diasRestantes <= 60) {
                $alertas[] = [
                    'tipo' => 'VENCE_2MESES',
                    'severidad' => 'BAJA',
                    'mensaje' => "El certificado vence en {$diasRestantes} días (planificar renovación)"
                ];
            }
        }

        // Verificar no conformidades pendientes
        if ($certificado->no_conformidades) {
            $pendientes = collect($certificado->no_conformidades)
                ->whereNotIn('estado', ['SOLUCIONADA', 'CERRADA'])
                ->count();
            
            if ($pendientes > 0) {
                $alertas[] = [
                    'tipo' => 'NC_PENDIENTES',
                    'severidad' => $pendientes > 2 ? 'ALTA' : 'MEDIA',
                    'mensaje' => "Hay {$pendientes} no conformidades pendientes de resolver"
                ];
            }
        }

        return $alertas;
    }

    private function agruparNoConformidades($certificados)
    {
        $clasificaciones = [];

        foreach ($certificados as $certificado) {
            $noConformidades = $certificado->no_conformidades ?? [];
            foreach ($noConformidades as $nc) {
                $clasificacion = $nc['clasificacion'] ?? 'NO_CLASIFICADA';
                if (!isset($clasificaciones[$clasificacion])) {
                    $clasificaciones[$clasificacion] = [
                        'total' => 0,
                        'pendientes' => 0
                    ];
                }
                $clasificaciones[$clasificacion]['total']++;
                if (!isset($nc['estado']) || !in_array($nc['estado'], ['SOLUCIONADA', 'CERRADA'])) {
                    $clasificaciones[$clasificacion]['pendientes']++;
                }
            }
        }

        return $clasificaciones;
    }

    private function formatearEquiposVerificados($equipos)
    {
        $resultado = [];

        if (isset($equipos['tanques'])) {
            $resultado['tanques'] = collect($equipos['tanques'])->map(function ($t) {
                return [
                    'id' => $t['id'],
                    'codigo' => Tanque::find($t['id'])?->codigo,
                    'resultado' => $t['resultado']
                ];
            });
        }

        if (isset($equipos['medidores'])) {
            $resultado['medidores'] = collect($equipos['medidores'])->map(function ($m) {
                return [
                    'id' => $m['id'],
                    'serie' => Medidor::find($m['id'])?->numero_serie,
                    'resultado' => $m['resultado']
                ];
            });
        }

        return $resultado;
    }
}