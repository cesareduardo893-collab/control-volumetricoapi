<?php

namespace App\Http\Controllers;

use App\Models\ReporteSat;
use App\Models\Instalacion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
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

        if ($request->has('usuario_genera_id')) {
            $query->where('usuario_genera_id', $request->usuario_genera_id);
        }

        if ($request->has('folio')) {
            $query->where('folio', 'LIKE', "%{$request->folio}%");
        }

        if ($request->has('periodo')) {
            $query->where('periodo', $request->periodo);
        }

        if ($request->has('tipo_reporte')) {
            $query->where('tipo_reporte', $request->tipo_reporte);
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('fecha_generacion_inicio')) {
            $query->where('fecha_generacion', '>=', Carbon::parse($request->fecha_generacion_inicio));
        }

        if ($request->has('fecha_generacion_fin')) {
            $query->where('fecha_generacion', '<=', Carbon::parse($request->fecha_generacion_fin));
        }

        $reportes = $query->orderBy('fecha_generacion', 'desc')
            ->paginate($request->get('per_page', 15));

        return $this->success($reportes, 'Reportes SAT obtenidos exitosamente');
    }

    /**
     * Crear reporte SAT
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'instalacion_id' => 'required|exists:instalaciones,id',
            'usuario_genera_id' => 'required|exists:users,id',
            'folio' => 'required|string|max:255|unique:reportes_sat,folio',
            'periodo' => 'required|string|size:7',
            'tipo_reporte' => 'required|in:MENSUAL,ANUAL,ESPECIAL',
            'ruta_xml' => 'nullable|string|max:255',
            'ruta_pdf' => 'nullable|string|max:255',
            'hash_sha256' => 'nullable|string|size:64',
            'cadena_original' => 'nullable|string',
            'sello_digital' => 'nullable|string',
            'certificado_sat' => 'nullable|string|max:255',
            'fecha_firma' => 'nullable|date',
            'datos_firma' => 'nullable|array',
            'folio_firma' => 'nullable|string|size:36',
            'estado' => 'required|in:PENDIENTE,GENERADO,FIRMADO,ENVIADO,ACEPTADO,RECHAZADO,ERROR,REQUIERE_REENVIO',
            'fecha_generacion' => 'nullable|date',
            'fecha_envio' => 'nullable|date',
            'acuse_sat' => 'nullable|string|max:255',
            'mensaje_respuesta' => 'nullable|string',
            'detalle_respuesta' => 'nullable|array',
            'datos_reporte' => 'nullable|array',
            'detalle_errores' => 'nullable|array',
            'numero_intentos' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $reporte = ReporteSat::create([
                'instalacion_id' => $request->instalacion_id,
                'usuario_genera_id' => $request->usuario_genera_id,
                'folio' => $request->folio,
                'periodo' => $request->periodo,
                'tipo_reporte' => $request->tipo_reporte,
                'ruta_xml' => $request->ruta_xml,
                'ruta_pdf' => $request->ruta_pdf,
                'hash_sha256' => $request->hash_sha256,
                'cadena_original' => $request->cadena_original,
                'sello_digital' => $request->sello_digital,
                'certificado_sat' => $request->certificado_sat,
                'fecha_firma' => $request->fecha_firma,
                'datos_firma' => $request->datos_firma,
                'folio_firma' => $request->folio_firma,
                'estado' => $request->estado,
                'fecha_generacion' => $request->fecha_generacion,
                'fecha_envio' => $request->fecha_envio,
                'acuse_sat' => $request->acuse_sat,
                'mensaje_respuesta' => $request->mensaje_respuesta,
                'detalle_respuesta' => $request->detalle_respuesta,
                'datos_reporte' => $request->datos_reporte,
                'detalle_errores' => $request->detalle_errores,
                'numero_intentos' => $request->numero_intentos ?? 0,
            ]);

            $this->logActivity(
                auth()->id(),
                'reportes_sat',
                'CREACION_REPORTE_SAT',
                'Reportes SAT',
                "Reporte SAT creado: {$reporte->folio}",
                'reportes_sat',
                $reporte->id
            );

            DB::commit();

            return $this->success($reporte->load(['instalacion', 'usuarioGenera']), 
                'Reporte SAT creado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al crear reporte SAT: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mostrar reporte SAT
     */
    public function show($id)
    {
        $reporte = ReporteSat::with([
            'instalacion',
            'usuarioGenera'
        ])->find($id);

        if (!$reporte) {
            return $this->error('Reporte SAT no encontrado', 404);
        }

        return $this->success($reporte, 'Reporte SAT obtenido exitosamente');
    }

    /**
     * Actualizar reporte SAT
     */
    public function update(Request $request, $id)
    {
        $reporte = ReporteSat::find($id);

        if (!$reporte) {
            return $this->error('Reporte SAT no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'ruta_xml' => 'nullable|string|max:255',
            'ruta_pdf' => 'nullable|string|max:255',
            'hash_sha256' => 'nullable|string|size:64',
            'cadena_original' => 'nullable|string',
            'sello_digital' => 'nullable|string',
            'certificado_sat' => 'nullable|string|max:255',
            'fecha_firma' => 'nullable|date',
            'datos_firma' => 'nullable|array',
            'folio_firma' => 'nullable|string|size:36',
            'estado' => 'sometimes|in:PENDIENTE,GENERADO,FIRMADO,ENVIADO,ACEPTADO,RECHAZADO,ERROR,REQUIERE_REENVIO',
            'fecha_envio' => 'nullable|date',
            'acuse_sat' => 'nullable|string|max:255',
            'mensaje_respuesta' => 'nullable|string',
            'detalle_respuesta' => 'nullable|array',
            'detalle_errores' => 'nullable|array',
            'numero_intentos' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $reporte->toArray();
            $reporte->update($request->all());

            $this->logActivity(
                auth()->id(),
                'reportes_sat',
                'ACTUALIZACION_REPORTE_SAT',
                'Reportes SAT',
                "Reporte SAT actualizado: {$reporte->folio}",
                'reportes_sat',
                $reporte->id,
                $datosAnteriores,
                $reporte->toArray()
            );

            DB::commit();

            return $this->success($reporte, 'Reporte SAT actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al actualizar reporte SAT: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Enviar reporte al SAT
     */
    public function enviar(Request $request, $id)
    {
        $reporte = ReporteSat::find($id);

        if (!$reporte) {
            return $this->error('Reporte SAT no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'fecha_envio' => 'required|date',
            'acuse_sat' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $reporte->toArray();

            $reporte->estado = 'ENVIADO';
            $reporte->fecha_envio = $request->fecha_envio;
            $reporte->acuse_sat = $request->acuse_sat;
            $reporte->numero_intentos = ($reporte->numero_intentos ?? 0) + 1;
            $reporte->save();

            $this->logActivity(
                auth()->id(),
                'reportes_sat',
                'ENVIO_REPORTE_SAT',
                'Reportes SAT',
                "Reporte SAT enviado: {$reporte->folio}",
                'reportes_sat',
                $reporte->id,
                $datosAnteriores,
                $reporte->toArray()
            );

            DB::commit();

            return $this->success($reporte, 'Reporte SAT enviado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al enviar reporte SAT: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Firmar reporte
     */
    public function firmar(Request $request, $id)
    {
        $reporte = ReporteSat::find($id);

        if (!$reporte) {
            return $this->error('Reporte SAT no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'cadena_original' => 'required|string',
            'sello_digital' => 'required|string',
            'certificado_sat' => 'required|string|max:255',
            'fecha_firma' => 'required|date',
            'folio_firma' => 'required|string|size:36',
            'datos_firma' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $reporte->toArray();

            $reporte->estado = 'FIRMADO';
            $reporte->cadena_original = $request->cadena_original;
            $reporte->sello_digital = $request->sello_digital;
            $reporte->certificado_sat = $request->certificado_sat;
            $reporte->fecha_firma = $request->fecha_firma;
            $reporte->folio_firma = $request->folio_firma;
            $reporte->datos_firma = $request->datos_firma;
            $reporte->save();

            $this->logActivity(
                auth()->id(),
                'reportes_sat',
                'FIRMA_REPORTE_SAT',
                'Reportes SAT',
                "Reporte SAT firmado: {$reporte->folio}",
                'reportes_sat',
                $reporte->id,
                $datosAnteriores,
                $reporte->toArray()
            );

            DB::commit();

            return $this->success($reporte, 'Reporte SAT firmado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al firmar reporte SAT: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cancelar reporte
     */
    public function cancelar(Request $request, $id)
    {
        $reporte = ReporteSat::find($id);

        if (!$reporte) {
            return $this->error('Reporte SAT no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'motivo_cancelacion' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $reporte->toArray();

            $reporte->estado = 'RECHAZADO';
            
            $errores = $reporte->detalle_errores ?? [];
            $errores[] = [
                'fecha' => now()->toDateTimeString(),
                'tipo' => 'CANCELACION',
                'descripcion' => $request->motivo_cancelacion,
                'usuario_id' => auth()->id(),
            ];
            $reporte->detalle_errores = $errores;
            
            $reporte->save();

            $this->logActivity(
                auth()->id(),
                'reportes_sat',
                'CANCELACION_REPORTE_SAT',
                'Reportes SAT',
                "Reporte SAT cancelado: {$reporte->folio}",
                'reportes_sat',
                $reporte->id,
                $datosAnteriores,
                $reporte->toArray()
            );

            DB::commit();

            return $this->success($reporte, 'Reporte SAT cancelado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al cancelar reporte SAT: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener historial de envíos
     */
    public function historialEnvios(Request $request, $instalacionId)
    {
        $instalacion = Instalacion::find($instalacionId);

        if (!$instalacion) {
            return $this->error('Instalación no encontrada', 404);
        }

        $validator = Validator::make($request->all(), [
            'anio' => 'required|integer|min:2020',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        $reportes = ReporteSat::where('instalacion_id', $instalacionId)
            ->whereYear('fecha_generacion', $request->anio)
            ->orderBy('periodo')
            ->get();

        $historial = [
            'instalacion' => [
                'id' => $instalacion->id,
                'clave' => $instalacion->clave_instalacion,
                'nombre' => $instalacion->nombre,
            ],
            'anio' => $request->anio,
            'resumen' => [
                'total_reportes' => $reportes->count(),
                'enviados' => $reportes->where('estado', 'ENVIADO')->count(),
                'aceptados' => $reportes->where('estado', 'ACEPTADO')->count(),
                'rechazados' => $reportes->where('estado', 'RECHAZADO')->count(),
                'pendientes' => $reportes->whereIn('estado', ['PENDIENTE', 'GENERADO', 'FIRMADO'])->count(),
            ],
            'por_mes' => $reportes->groupBy('periodo')
                ->map(function ($items, $periodo) {
                    $reporte = $items->first();
                    return [
                        'periodo' => $periodo,
                        'folio' => $reporte->folio,
                        'estado' => $reporte->estado,
                        'fecha_envio' => $reporte->fecha_envio?->toDateString(),
                        'acuse_sat' => $reporte->acuse_sat,
                    ];
                })->values(),
        ];

        return $this->success($historial, 'Historial de envíos obtenido exitosamente');
    }
}