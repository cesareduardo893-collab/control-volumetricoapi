<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Dictamen;
use App\Models\Contribuyente;
use App\Models\Instalacion;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DictamenController extends BaseController
{
    /**
     * Listar dictámenes
     */
    public function index(Request $request)
    {
        $query = Dictamen::with([
            'contribuyente',
            'instalacion',
            'producto'
        ]);

        // Filtros
        if ($request->has('contribuyente_id')) {
            $query->where('contribuyente_id', $request->contribuyente_id);
        }

        if ($request->has('instalacion_id')) {
            $query->where('instalacion_id', $request->instalacion_id);
        }

        if ($request->has('producto_id')) {
            $query->where('producto_id', $request->producto_id);
        }

        if ($request->has('folio')) {
            $query->where('folio', 'LIKE', "%{$request->folio}%");
        }

        if ($request->has('numero_lote')) {
            $query->where('numero_lote', 'LIKE', "%{$request->numero_lote}%");
        }

        if ($request->has('laboratorio_rfc')) {
            $query->where('laboratorio_rfc', 'LIKE', "%{$request->laboratorio_rfc}%");
        }

        if ($request->has('fecha_emision_inicio')) {
            $query->where('fecha_emision', '>=', Carbon::parse($request->fecha_emision_inicio));
        }

        if ($request->has('fecha_emision_fin')) {
            $query->where('fecha_emision', '<=', Carbon::parse($request->fecha_emision_fin));
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->boolean('vigente')) {
            $query->where('estado', 'VIGENTE');
        }

        $dictamenes = $query->orderBy('fecha_emision', 'desc')
            ->paginate($request->get('per_page', 15));

        return $this->success($dictamenes, 'Dictámenes obtenidos exitosamente');
    }

    /**
     * Crear dictamen
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'folio' => 'required|string|max:255|unique:dictamenes,folio',
            'numero_lote' => 'required|string|max:255|unique:dictamenes,numero_lote',
            'contribuyente_id' => 'required|exists:contribuyentes,id',
            'laboratorio_rfc' => 'required|string|size:13',
            'laboratorio_nombre' => 'required|string|max:255',
            'laboratorio_numero_acreditacion' => 'required|string|max:255',
            'fecha_emision' => 'required|date',
            'fecha_toma_muestra' => 'required|date|before_or_equal:fecha_emision',
            'fecha_pruebas' => 'required|date|before_or_equal:fecha_emision',
            'fecha_resultados' => 'required|date|before_or_equal:fecha_emision',
            'instalacion_id' => 'nullable|exists:instalaciones,id',
            'ubicacion_muestra' => 'nullable|string|max:255',
            'producto_id' => 'required|exists:productos,id',
            'volumen_muestra' => 'required|numeric|min:0',
            'unidad_medida_muestra' => 'required|string|max:10',
            'metodo_muestreo' => 'required|string|max:255',
            'metodo_ensayo' => 'required|string|max:255',
            'metodos_aplicados' => 'nullable|array',
            'densidad_api' => 'nullable|numeric|min:0|max:100',
            'azufre' => 'nullable|numeric|min:0|max:100',
            'clasificacion_azufre' => 'nullable|string|max:255',
            'clasificacion_api' => 'nullable|string|max:255',
            'composicion_molar' => 'nullable|array',
            'propiedades_fisicas' => 'nullable|array',
            'propiedades_quimicas' => 'nullable|array',
            'poder_calorifico' => 'nullable|numeric|min:0',
            'poder_calorifico_superior' => 'nullable|numeric|min:0',
            'poder_calorifico_inferior' => 'nullable|numeric|min:0',
            'octanaje_ron' => 'nullable|numeric|min:0|max:120',
            'octanaje_mon' => 'nullable|numeric|min:0|max:120',
            'indice_octano' => 'nullable|numeric|min:0|max:120',
            'contiene_bioetanol' => 'boolean',
            'porcentaje_bioetanol' => 'nullable|numeric|min:0|max:100',
            'contiene_biodiesel' => 'boolean',
            'porcentaje_biodiesel' => 'nullable|numeric|min:0|max:100',
            'contiene_bioturbosina' => 'boolean',
            'porcentaje_bioturbosina' => 'nullable|numeric|min:0|max:100',
            'fame' => 'nullable|numeric|min:0',
            'porcentaje_propano' => 'nullable|numeric|min:0|max:100',
            'porcentaje_butano' => 'nullable|numeric|min:0|max:100',
            'propano_normalizado' => 'nullable|numeric|min:0|max:100',
            'butano_normalizado' => 'nullable|numeric|min:0|max:100',
            'composicion_normalizada' => 'nullable|array',
            'archivo_pdf' => 'nullable|string|max:255',
            'archivo_xml' => 'nullable|string|max:255',
            'archivo_json' => 'nullable|string|max:255',
            'archivos_adicionales' => 'nullable|array',
            'estado' => 'required|in:VIGENTE,CADUCADO,CANCELADO',
            'observaciones' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $dictamen = Dictamen::create([
                'folio' => $request->folio,
                'numero_lote' => $request->numero_lote,
                'contribuyente_id' => $request->contribuyente_id,
                'laboratorio_rfc' => $request->laboratorio_rfc,
                'laboratorio_nombre' => $request->laboratorio_nombre,
                'laboratorio_numero_acreditacion' => $request->laboratorio_numero_acreditacion,
                'fecha_emision' => $request->fecha_emision,
                'fecha_toma_muestra' => $request->fecha_toma_muestra,
                'fecha_pruebas' => $request->fecha_pruebas,
                'fecha_resultados' => $request->fecha_resultados,
                'instalacion_id' => $request->instalacion_id,
                'ubicacion_muestra' => $request->ubicacion_muestra,
                'producto_id' => $request->producto_id,
                'volumen_muestra' => $request->volumen_muestra,
                'unidad_medida_muestra' => $request->unidad_medida_muestra,
                'metodo_muestreo' => $request->metodo_muestreo,
                'metodo_ensayo' => $request->metodo_ensayo,
                'metodos_aplicados' => $request->metodos_aplicados,
                'densidad_api' => $request->densidad_api,
                'azufre' => $request->azufre,
                'clasificacion_azufre' => $request->clasificacion_azufre,
                'clasificacion_api' => $request->clasificacion_api,
                'composicion_molar' => $request->composicion_molar,
                'propiedades_fisicas' => $request->propiedades_fisicas,
                'propiedades_quimicas' => $request->propiedades_quimicas,
                'poder_calorifico' => $request->poder_calorifico,
                'poder_calorifico_superior' => $request->poder_calorifico_superior,
                'poder_calorifico_inferior' => $request->poder_calorifico_inferior,
                'octanaje_ron' => $request->octanaje_ron,
                'octanaje_mon' => $request->octanaje_mon,
                'indice_octano' => $request->indice_octano,
                'contiene_bioetanol' => $request->boolean('contiene_bioetanol', false),
                'porcentaje_bioetanol' => $request->porcentaje_bioetanol,
                'contiene_biodiesel' => $request->boolean('contiene_biodiesel', false),
                'porcentaje_biodiesel' => $request->porcentaje_biodiesel,
                'contiene_bioturbosina' => $request->boolean('contiene_bioturbosina', false),
                'porcentaje_bioturbosina' => $request->porcentaje_bioturbosina,
                'fame' => $request->fame,
                'porcentaje_propano' => $request->porcentaje_propano,
                'porcentaje_butano' => $request->porcentaje_butano,
                'propano_normalizado' => $request->propano_normalizado,
                'butano_normalizado' => $request->butano_normalizado,
                'composicion_normalizada' => $request->composicion_normalizada,
                'archivo_pdf' => $request->archivo_pdf,
                'archivo_xml' => $request->archivo_xml,
                'archivo_json' => $request->archivo_json,
                'archivos_adicionales' => $request->archivos_adicionales,
                'estado' => $request->estado,
                'observaciones' => $request->observaciones,
            ]);

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'CREACION_DICTAMEN',
                'Calidad',
                "Dictamen creado: {$dictamen->folio}",
                'dictamenes',
                $dictamen->id
            );

            DB::commit();

            return $this->success($dictamen->load(['contribuyente', 'producto', 'instalacion']), 
                'Dictamen creado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al crear dictamen: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mostrar dictamen
     */
    public function show($id)
    {
        $dictamen = Dictamen::with([
            'contribuyente',
            'instalacion',
            'producto',
            'registrosVolumetricos' => function($q) {
                $q->latest()->limit(10);
            }
        ])->find($id);

        if (!$dictamen) {
            return $this->error('Dictamen no encontrado', 404);
        }

        return $this->success($dictamen, 'Dictamen obtenido exitosamente');
    }

    /**
     * Actualizar dictamen
     */
    public function update(Request $request, $id)
    {
        $dictamen = Dictamen::find($id);

        if (!$dictamen) {
            return $this->error('Dictamen no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'observaciones' => 'nullable|string',
            'estado' => 'sometimes|in:VIGENTE,CADUCADO,CANCELADO',
            'archivo_pdf' => 'nullable|string|max:255',
            'archivo_xml' => 'nullable|string|max:255',
            'archivo_json' => 'nullable|string|max:255',
            'archivos_adicionales' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $dictamen->toArray();
            $dictamen->update($request->all());

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'ACTUALIZACION_DICTAMEN',
                'Calidad',
                "Dictamen actualizado: {$dictamen->folio}",
                'dictamenes',
                $dictamen->id,
                $datosAnteriores,
                $dictamen->toArray()
            );

            DB::commit();

            return $this->success($dictamen, 'Dictamen actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al actualizar dictamen: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cancelar dictamen
     */
    public function cancelar(Request $request, $id)
    {
        $dictamen = Dictamen::find($id);

        if (!$dictamen) {
            return $this->error('Dictamen no encontrado', 404);
        }

        if ($dictamen->estado == 'CANCELADO') {
            return $this->error('El dictamen ya está cancelado', 403);
        }

        $validator = Validator::make($request->all(), [
            'motivo_cancelacion' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $dictamen->toArray();

            $dictamen->estado = 'CANCELADO';
            $dictamen->observaciones = $request->motivo_cancelacion;
            $dictamen->save();

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'CANCELACION_DICTAMEN',
                'Calidad',
                "Dictamen cancelado: {$dictamen->folio}",
                'dictamenes',
                $dictamen->id,
                $datosAnteriores,
                $dictamen->toArray()
            );

            DB::commit();

            return $this->success($dictamen, 'Dictamen cancelado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al cancelar dictamen: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Verificar vigencia
     */
    public function verificarVigencia($id)
    {
        $dictamen = Dictamen::find($id);

        if (!$dictamen) {
            return $this->error('Dictamen no encontrado', 404);
        }

        $hoy = Carbon::now();
        $vigente = $dictamen->estado == 'VIGENTE';
        $fechaVencimiento = $dictamen->fecha_emision->copy()->addYear();

        $diasVigencia = $vigente ? $hoy->diffInDays($fechaVencimiento, false) : 0;

        $resultado = [
            'dictamen_id' => $dictamen->id,
            'folio' => $dictamen->folio,
            'fecha_emision' => $dictamen->fecha_emision->toDateString(),
            'fecha_vencimiento' => $fechaVencimiento->toDateString(),
            'vigente' => $vigente,
            'dias_transcurridos' => $vigente ? $hoy->diffInDays($dictamen->fecha_emision) : 0,
            'dias_restantes' => $diasVigencia,
            'porcentaje_vigencia' => $vigente && $diasVigencia > 0 ? round(($diasVigencia / 365) * 100, 2) : 0,
            'estado' => $dictamen->estado,
            'proximo_vencer' => $diasVigencia <= 30 && $diasVigencia > 0,
        ];

        return $this->success($resultado, 'Vigencia del dictamen verificada exitosamente');
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

        $dictamenes = Dictamen::where('contribuyente_id', $request->contribuyente_id)
            ->whereYear('fecha_emision', $request->anio)
            ->with('producto')
            ->get();

        $estadisticas = [
            'contribuyente_id' => $request->contribuyente_id,
            'anio' => $request->anio,
            'resumen' => [
                'total_dictamenes' => $dictamenes->count(),
                'vigentes' => $dictamenes->where('estado', 'VIGENTE')->count(),
                'caducados' => $dictamenes->where('estado', 'CADUCADO')->count(),
                'cancelados' => $dictamenes->where('estado', 'CANCELADO')->count(),
            ],
            'por_producto' => $dictamenes->groupBy('producto_id')
                ->map(function ($items) {
                    $producto = $items->first()->producto;
                    return [
                        'producto' => $producto ? $producto->nombre : 'N/A',
                        'cantidad' => $items->count(),
                        'vigentes' => $items->where('estado', 'VIGENTE')->count(),
                    ];
                })->values(),
            'tendencia_mensual' => $dictamenes->groupBy(function ($item) {
                    return $item->fecha_emision->format('Y-m');
                })
                ->map(function ($items, $mes) {
                    return [
                        'mes' => $mes,
                        'total' => $items->count(),
                    ];
                })->values(),
        ];

        return $this->success($estadisticas, 'Estadísticas de dictámenes obtenidas exitosamente');
    }

    /**
     * Obtener por producto
     */
    public function porProducto($productoId)
    {
        $producto = Producto::find($productoId);

        if (!$producto) {
            return $this->error('Producto no encontrado', 404);
        }

        $dictamenes = Dictamen::where('producto_id', $productoId)
            ->where('estado', 'VIGENTE')
            ->with('contribuyente')
            ->orderBy('fecha_emision', 'desc')
            ->get();

        return $this->success([
            'producto' => [
                'id' => $producto->id,
                'nombre' => $producto->nombre,
                'clave_sat' => $producto->clave_sat,
            ],
            'dictamenes' => $dictamenes,
        ], 'Dictámenes por producto obtenidos exitosamente');
    }
}