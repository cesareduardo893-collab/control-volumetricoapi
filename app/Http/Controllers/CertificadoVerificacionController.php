<?php

namespace App\Http\Controllers;

use App\Models\CertificadoVerificacion;
use App\Models\Contribuyente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CertificadoVerificacionController extends BaseController
{
    /**
     * Listar certificados de verificación
     */
    public function index(Request $request)
    {
        $query = CertificadoVerificacion::with(['contribuyente']);

        // Filtros
        if ($request->has('contribuyente_id')) {
            $query->where('contribuyente_id', $request->contribuyente_id);
        }

        if ($request->has('folio')) {
            $query->where('folio', 'LIKE', "%{$request->folio}%");
        }

        if ($request->has('proveedor_rfc')) {
            $query->where('proveedor_rfc', 'LIKE', "%{$request->proveedor_rfc}%");
        }

        if ($request->has('resultado')) {
            $query->where('resultado', $request->resultado);
        }

        if ($request->has('fecha_emision_inicio')) {
            $query->where('fecha_emision', '>=', Carbon::parse($request->fecha_emision_inicio));
        }

        if ($request->has('fecha_emision_fin')) {
            $query->where('fecha_emision', '<=', Carbon::parse($request->fecha_emision_fin));
        }

        if ($request->has('vigente')) {
            $query->where('vigente', $request->boolean('vigente'));
        }

        if ($request->has('requiere_verificacion_extraordinaria')) {
            $query->where('requiere_verificacion_extraordinaria', $request->boolean('requiere_verificacion_extraordinaria'));
        }

        $certificados = $query->orderBy('fecha_emision', 'desc')
            ->paginate($request->get('per_page', 15));

        return $this->success($certificados, 'Certificados de verificación obtenidos exitosamente');
    }

    /**
     * Crear certificado de verificación
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'folio' => 'required|string|max:255|unique:certificados_verificacion,folio',
            'contribuyente_id' => 'required|exists:contribuyentes,id',
            'proveedor_rfc' => 'required|string|size:13',
            'proveedor_nombre' => 'required|string|max:255',
            'fecha_emision' => 'required|date',
            'fecha_inicio_verificacion' => 'required|date|before_or_equal:fecha_emision',
            'fecha_fin_verificacion' => 'required|date|after_or_equal:fecha_inicio_verificacion|before_or_equal:fecha_emision',
            'resultado' => 'required|in:acreditado,no_acreditado',
            'tabla_cumplimiento' => 'required|array',
            'hallazgos' => 'nullable|array',
            'recomendaciones_especificas' => 'nullable|array',
            'observaciones' => 'nullable|string',
            'recomendaciones' => 'nullable|string',
            'archivo_pdf' => 'nullable|string|max:255',
            'archivo_xml' => 'nullable|string|max:255',
            'archivo_json' => 'nullable|string|max:255',
            'archivos_adicionales' => 'nullable|array',
            'vigente' => 'boolean',
            'fecha_caducidad' => 'nullable|date|after:fecha_emision',
            'requiere_verificacion_extraordinaria' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $certificado = CertificadoVerificacion::create([
                'folio' => $request->folio,
                'contribuyente_id' => $request->contribuyente_id,
                'proveedor_rfc' => $request->proveedor_rfc,
                'proveedor_nombre' => $request->proveedor_nombre,
                'fecha_emision' => $request->fecha_emision,
                'fecha_inicio_verificacion' => $request->fecha_inicio_verificacion,
                'fecha_fin_verificacion' => $request->fecha_fin_verificacion,
                'resultado' => $request->resultado,
                'tabla_cumplimiento' => $request->tabla_cumplimiento,
                'hallazgos' => $request->hallazgos,
                'recomendaciones_especificas' => $request->recomendaciones_especificas,
                'observaciones' => $request->observaciones,
                'recomendaciones' => $request->recomendaciones,
                'archivo_pdf' => $request->archivo_pdf,
                'archivo_xml' => $request->archivo_xml,
                'archivo_json' => $request->archivo_json,
                'archivos_adicionales' => $request->archivos_adicionales,
                'vigente' => $request->boolean('vigente', true),
                'fecha_caducidad' => $request->fecha_caducidad,
                'requiere_verificacion_extraordinaria' => $request->boolean('requiere_verificacion_extraordinaria', false),
            ]);

            $this->logActivity(
                auth()->id(),
                'verificacion',
                'CREACION_CERTIFICADO_VERIFICACION',
                'Verificación',
                "Certificado de verificación creado: {$certificado->folio}",
                'certificados_verificacion',
                $certificado->id
            );

            DB::commit();

            return $this->success($certificado->load('contribuyente'), 
                'Certificado de verificación creado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al crear certificado de verificación: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mostrar certificado de verificación
     */
    public function show($id)
    {
        $certificado = CertificadoVerificacion::with(['contribuyente'])->find($id);

        if (!$certificado) {
            return $this->error('Certificado de verificación no encontrado', 404);
        }

        return $this->success($certificado, 'Certificado de verificación obtenido exitosamente');
    }

    /**
     * Actualizar certificado de verificación
     */
    public function update(Request $request, $id)
    {
        $certificado = CertificadoVerificacion::find($id);

        if (!$certificado) {
            return $this->error('Certificado de verificación no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'hallazgos' => 'nullable|array',
            'recomendaciones_especificas' => 'nullable|array',
            'observaciones' => 'nullable|string',
            'recomendaciones' => 'nullable|string',
            'archivo_pdf' => 'nullable|string|max:255',
            'archivo_xml' => 'nullable|string|max:255',
            'archivo_json' => 'nullable|string|max:255',
            'archivos_adicionales' => 'nullable|array',
            'vigente' => 'sometimes|boolean',
            'fecha_caducidad' => 'nullable|date|after:fecha_emision',
            'requiere_verificacion_extraordinaria' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $certificado->toArray();
            $certificado->update($request->all());

            $this->logActivity(
                auth()->id(),
                'verificacion',
                'ACTUALIZACION_CERTIFICADO_VERIFICACION',
                'Verificación',
                "Certificado de verificación actualizado: {$certificado->folio}",
                'certificados_verificacion',
                $certificado->id,
                $datosAnteriores,
                $certificado->toArray()
            );

            DB::commit();

            return $this->success($certificado, 'Certificado de verificación actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al actualizar certificado de verificación: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Verificar vigencia
     */
    public function verificarVigencia($id)
    {
        $certificado = CertificadoVerificacion::find($id);

        if (!$certificado) {
            return $this->error('Certificado de verificación no encontrado', 404);
        }

        $hoy = Carbon::now();
        $vigente = $certificado->vigente && 
                  ($certificado->fecha_caducidad ? Carbon::parse($certificado->fecha_caducidad) >= $hoy : true);

        $diasRestantes = $certificado->fecha_caducidad ? 
            $hoy->diffInDays(Carbon::parse($certificado->fecha_caducidad), false) : null;

        $resultado = [
            'certificado_id' => $certificado->id,
            'folio' => $certificado->folio,
            'fecha_emision' => $certificado->fecha_emision->toDateString(),
            'fecha_caducidad' => $certificado->fecha_caducidad?->toDateString(),
            'vigente' => $vigente,
            'dias_restantes' => $diasRestantes,
            'requiere_verificacion_extraordinaria' => $certificado->requiere_verificacion_extraordinaria,
            'resultado' => $certificado->resultado,
            'proximo_vencer' => $diasRestantes !== null && $diasRestantes <= 30 && $diasRestantes > 0,
        ];

        return $this->success($resultado, 'Vigencia del certificado verificada exitosamente');
    }

    /**
     * Obtener estadísticas
     */
    public function estadisticas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contribuyente_id' => 'required|exists:contribuyentes,id',
            'anio' => 'required|integer|min:2020',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        $certificados = CertificadoVerificacion::where('contribuyente_id', $request->contribuyente_id)
            ->whereYear('fecha_emision', $request->anio)
            ->get();

        $hoy = Carbon::now();

        $estadisticas = [
            'contribuyente_id' => $request->contribuyente_id,
            'anio' => $request->anio,
            'resumen' => [
                'total_certificados' => $certificados->count(),
                'acreditados' => $certificados->where('resultado', 'acreditado')->count(),
                'no_acreditados' => $certificados->where('resultado', 'no_acreditado')->count(),
                'vigentes' => $certificados->filter(function ($c) use ($hoy) {
                    return $c->vigente && (!$c->fecha_caducidad || Carbon::parse($c->fecha_caducidad) >= $hoy);
                })->count(),
                'requieren_verificacion' => $certificados->where('requiere_verificacion_extraordinaria', true)->count(),
            ],
            'tendencia_mensual' => $certificados->groupBy(function ($item) {
                    return $item->fecha_emision->format('Y-m');
                })
                ->map(function ($items, $mes) {
                    return [
                        'mes' => $mes,
                        'total' => $items->count(),
                        'acreditados' => $items->where('resultado', 'acreditado')->count(),
                    ];
                })->values(),
        ];

        return $this->success($estadisticas, 'Estadísticas de certificados obtenidas exitosamente');
    }
}