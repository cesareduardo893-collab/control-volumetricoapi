<?php

namespace App\Http\Controllers;

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
            'producto',
            'laboratorio',
            'tecnico'
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

        if ($request->has('laboratorio_id')) {
            $query->where('laboratorio_id', $request->laboratorio_id);
        }

        if ($request->has('folio')) {
            $query->where('folio', 'LIKE', "%{$request->folio}%");
        }

        if ($request->has('tipo_dictamen')) {
            $query->where('tipo_dictamen', $request->tipo_dictamen);
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
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

        if ($request->has('fecha_vencimiento_inicio')) {
            $query->where('fecha_vencimiento', '>=', Carbon::parse($request->fecha_vencimiento_inicio));
        }

        if ($request->has('fecha_vencimiento_fin')) {
            $query->where('fecha_vencimiento', '<=', Carbon::parse($request->fecha_vencimiento_fin));
        }

        if ($request->boolean('vigente')) {
            $query->where('fecha_vencimiento', '>=', now())
                  ->where('estado', 'EMITIDO')
                  ->where('resultado', 'CONFORME');
        }

        if ($request->boolean('proximo_vencer')) {
            $query->whereBetween('fecha_vencimiento', [now(), now()->addDays(30)])
                  ->where('estado', 'EMITIDO');
        }

        $dictamenes = $query->orderBy('fecha_emision', 'desc')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($dictamenes, 'Dictámenes obtenidos exitosamente');
    }

    /**
     * Crear dictamen
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contribuyente_id' => 'required|exists:contribuyentes,id',
            'instalacion_id' => 'nullable|exists:instalaciones,id',
            'producto_id' => 'required|exists:productos,id',
            'laboratorio_id' => 'required|exists:laboratorios,id',
            'tecnico_id' => 'required|exists:tecnicos,id',
            'folio' => 'required|string|max:50|unique:dictamenes,folio',
            'tipo_dictamen' => 'required|in:COMPOSICION,CALIDAD,VERIFICACION,CERTIFICACION,RECERTIFICACION',
            'fecha_muestreo' => 'required|date',
            'fecha_analisis' => 'required|date|after_or_equal:fecha_muestreo',
            'fecha_emision' => 'required|date|after_or_equal:fecha_analisis',
            'fecha_vencimiento' => 'required|date|after:fecha_emision',
            'lote' => 'required|string|max:100',
            'cantidad_muestra' => 'required|numeric|min:0',
            'unidad_muestra' => 'required|in:LITROS,ML,KG,G',
            'condiciones_muestreo' => 'required|array',
            'condiciones_muestreo.temperatura' => 'required|numeric',
            'condiciones_muestreo.presion' => 'required|numeric',
            'condiciones_muestreo.humedad' => 'nullable|numeric',
            'condiciones_muestreo.ubicacion' => 'required|string',
            'condiciones_muestreo.responsable' => 'required|string',
            'resultados' => 'required|array',
            'resultados.densidad' => 'nullable|numeric',
            'resultados.api_gravedad' => 'nullable|numeric',
            'resultados.viscosidad' => 'nullable|numeric',
            'resultados.azufre' => 'nullable|numeric',
            'resultados.poder_calorifico' => 'nullable|numeric',
            'resultados.octanaje_ron' => 'nullable|numeric',
            'resultados.octanaje_mon' => 'nullable|numeric',
            'resultados.cetano' => 'nullable|numeric',
            'resultados.punto_inflamacion' => 'nullable|numeric',
            'resultados.punto_ebullicion' => 'nullable|numeric',
            'resultados.presion_vapor' => 'nullable|numeric',
            'resultados.composicion' => 'nullable|array',
            'resultados.composicion.*.componente' => 'required_with:resultados.composicion|string',
            'resultados.composicion.*.porcentaje' => 'required_with:resultados.composicion|numeric|min:0|max:100',
            'resultados.contaminantes' => 'nullable|array',
            'resultados.contaminantes.*.nombre' => 'required_with:resultados.contaminantes|string',
            'resultados.contaminantes.*.valor' => 'required_with:resultados.contaminantes|numeric',
            'resultados.contaminantes.*.unidad' => 'required_with:resultados.contaminantes|string',
            'resultados.contaminantes.*.limite' => 'nullable|numeric',
            'especificacion_aplicable' => 'required|string|max:255',
            'resultado' => 'required|in:CONFORME,NO_CONFORME,CON_OBSERVACIONES',
            'observaciones' => 'nullable|string|max:1000',
            'conclusiones' => 'nullable|string|max:1000',
            'recomendaciones' => 'nullable|string|max:1000',
            'metodos_utilizados' => 'required|array',
            'metodos_utilizados.*.parametro' => 'required|string',
            'metodos_utilizados.*.metodo' => 'required|string',
            'metodos_utilizados.*.norma' => 'nullable|string',
            'equipos_utilizados' => 'required|array',
            'equipos_utilizados.*.nombre' => 'required|string',
            'equipos_utilizados.*.modelo' => 'required|string',
            'equipos_utilizados.*.serie' => 'required|string',
            'equipos_utilizados.*.calibracion' => 'required|date',
            'incertidumbre' => 'nullable|numeric',
            'trazabilidad' => 'nullable|string|max:500',
            'archivo_dictamen' => 'nullable|file|mimes:pdf|max:20480',
            'archivos_anexos' => 'nullable|array',
            'archivos_anexos.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,jpg,png|max:10240',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            // Guardar archivo principal
            $rutaArchivo = null;
            if ($request->hasFile('archivo_dictamen')) {
                $rutaArchivo = $request->file('archivo_dictamen')
                    ->store("dictamenes/{$request->contribuyente_id}", 'public');
            }

            // Guardar archivos anexos
            $anexos = [];
            if ($request->hasFile('archivos_anexos')) {
                foreach ($request->file('archivos_anexos') as $index => $archivo) {
                    $ruta = $archivo->store("dictamenes/{$request->contribuyente_id}/anexos", 'public');
                    $anexos[] = [
                        'nombre_original' => $archivo->getClientOriginalName(),
                        'ruta' => $ruta,
                        'tipo' => $archivo->getMimeType(),
                        'tamano' => $archivo->getSize()
                    ];
                }
            }

            $dictamen = Dictamen::create([
                'contribuyente_id' => $request->contribuyente_id,
                'instalacion_id' => $request->instalacion_id,
                'producto_id' => $request->producto_id,
                'laboratorio_id' => $request->laboratorio_id,
                'tecnico_id' => $request->tecnico_id,
                'folio' => $request->folio,
                'tipo_dictamen' => $request->tipo_dictamen,
                'fecha_muestreo' => $request->fecha_muestreo,
                'fecha_analisis' => $request->fecha_analisis,
                'fecha_emision' => $request->fecha_emision,
                'fecha_vencimiento' => $request->fecha_vencimiento,
                'lote' => $request->lote,
                'cantidad_muestra' => $request->cantidad_muestra,
                'unidad_muestra' => $request->unidad_muestra,
                'condiciones_muestreo' => $request->condiciones_muestreo,
                'resultados' => $request->resultados,
                'especificacion_aplicable' => $request->especificacion_aplicable,
                'resultado' => $request->resultado,
                'observaciones' => $request->observaciones,
                'conclusiones' => $request->conclusiones,
                'recomendaciones' => $request->recomendaciones,
                'metodos_utilizados' => $request->metodos_utilizados,
                'equipos_utilizados' => $request->equipos_utilizados,
                'incertidumbre' => $request->incertidumbre,
                'trazabilidad' => $request->trazabilidad,
                'archivo_dictamen' => $rutaArchivo,
                'archivos_anexos' => $anexos,
                'estado' => 'EMITIDO',
                'metadata' => $request->metadata
            ]);

            $this->logActivity(
                auth()->id(),
                'calidad',
                'creacion_dictamen',
                'dictamenes',
                "Dictamen creado: {$dictamen->folio} - Resultado: {$dictamen->resultado}",
                'dictamenes',
                $dictamen->id
            );

            DB::commit();

            return $this->sendResponse($dictamen->load(['contribuyente', 'producto', 'laboratorio']), 
                'Dictamen creado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al crear dictamen', [$e->getMessage()], 500);
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
            'laboratorio',
            'tecnico',
            'registrosVolumetricos' => function($q) {
                $q->latest()->limit(10);
            }
        ])->find($id);

        if (!$dictamen) {
            return $this->sendError('Dictamen no encontrado');
        }

        return $this->sendResponse($dictamen, 'Dictamen obtenido exitosamente');
    }

    /**
     * Actualizar dictamen (solo si está en estado BORRADOR)
     */
    public function update(Request $request, $id)
    {
        $dictamen = Dictamen::find($id);

        if (!$dictamen) {
            return $this->sendError('Dictamen no encontrado');
        }

        if ($dictamen->estado != 'BORRADOR') {
            return $this->sendError('Solo se pueden modificar dictámenes en estado borrador', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'instalacion_id' => 'nullable|exists:instalaciones,id',
            'observaciones' => 'nullable|string|max:1000',
            'conclusiones' => 'nullable|string|max:1000',
            'recomendaciones' => 'nullable|string|max:1000',
            'archivo_dictamen' => 'nullable|file|mimes:pdf|max:20480',
            'archivos_anexos' => 'nullable|array',
            'archivos_anexos.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,jpg,png|max:10240',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $dictamen->toArray();

            // Actualizar archivo si se proporciona
            if ($request->hasFile('archivo_dictamen')) {
                $rutaArchivo = $request->file('archivo_dictamen')
                    ->store("dictamenes/{$dictamen->contribuyente_id}", 'public');
                $dictamen->archivo_dictamen = $rutaArchivo;
            }

            // Actualizar anexos si se proporcionan
            if ($request->hasFile('archivos_anexos')) {
                $anexos = $dictamen->archivos_anexos ?? [];
                foreach ($request->file('archivos_anexos') as $archivo) {
                    $ruta = $archivo->store("dictamenes/{$dictamen->contribuyente_id}/anexos", 'public');
                    $anexos[] = [
                        'nombre_original' => $archivo->getClientOriginalName(),
                        'ruta' => $ruta,
                        'tipo' => $archivo->getMimeType(),
                        'tamano' => $archivo->getSize(),
                        'fecha' => now()->toDateTimeString()
                    ];
                }
                $dictamen->archivos_anexos = $anexos;
            }

            $dictamen->instalacion_id = $request->instalacion_id ?? $dictamen->instalacion_id;
            $dictamen->observaciones = $request->observaciones ?? $dictamen->observaciones;
            $dictamen->conclusiones = $request->conclusiones ?? $dictamen->conclusiones;
            $dictamen->recomendaciones = $request->recomendaciones ?? $dictamen->recomendaciones;
            
            if ($request->has('metadata')) {
                $metadata = array_merge($dictamen->metadata ?? [], $request->metadata);
                $dictamen->metadata = $metadata;
            }
            
            $dictamen->save();

            $this->logActivity(
                auth()->id(),
                'calidad',
                'actualizacion_dictamen',
                'dictamenes',
                "Dictamen actualizado: {$dictamen->folio}",
                'dictamenes',
                $dictamen->id,
                $datosAnteriores,
                $dictamen->toArray()
            );

            DB::commit();

            return $this->sendResponse($dictamen, 'Dictamen actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al actualizar dictamen', [$e->getMessage()], 500);
        }
    }

    /**
     * Publicar dictamen (cambiar de BORRADOR a EMITIDO)
     */
    public function publicar(Request $request, $id)
    {
        $dictamen = Dictamen::find($id);

        if (!$dictamen) {
            return $this->sendError('Dictamen no encontrado');
        }

        if ($dictamen->estado != 'BORRADOR') {
            return $this->sendError('El dictamen no está en estado borrador', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'fecha_emision' => 'required|date',
            'fecha_vencimiento' => 'required|date|after:fecha_emision',
            'resultado' => 'required|in:CONFORME,NO_CONFORME,CON_OBSERVACIONES',
            'observaciones_publicacion' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $dictamen->toArray();

            $dictamen->estado = 'EMITIDO';
            $dictamen->fecha_emision = $request->fecha_emision;
            $dictamen->fecha_vencimiento = $request->fecha_vencimiento;
            $dictamen->resultado = $request->resultado;
            
            $metadata = $dictamen->metadata ?? [];
            $metadata['publicacion'] = [
                'fecha' => now()->toDateTimeString(),
                'usuario_id' => auth()->id(),
                'observaciones' => $request->observaciones_publicacion
            ];
            $dictamen->metadata = $metadata;
            
            $dictamen->save();

            $this->logActivity(
                auth()->id(),
                'calidad',
                'publicacion_dictamen',
                'dictamenes',
                "Dictamen publicado: {$dictamen->folio} - Resultado: {$dictamen->resultado}",
                'dictamenes',
                $dictamen->id,
                $datosAnteriores,
                $dictamen->toArray()
            );

            DB::commit();

            return $this->sendResponse($dictamen, 'Dictamen publicado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al publicar dictamen', [$e->getMessage()], 500);
        }
    }

    /**
     * Cancelar dictamen
     */
    public function cancelar(Request $request, $id)
    {
        $dictamen = Dictamen::find($id);

        if (!$dictamen) {
            return $this->sendError('Dictamen no encontrado');
        }

        if ($dictamen->estado == 'CANCELADO') {
            return $this->sendError('El dictamen ya está cancelado', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'motivo_cancelacion' => 'required|string|max:500',
            'observaciones' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $dictamen->toArray();

            $dictamen->estado = 'CANCELADO';
            
            $metadata = $dictamen->metadata ?? [];
            $metadata['cancelacion'] = [
                'fecha' => now()->toDateTimeString(),
                'usuario_id' => auth()->id(),
                'motivo' => $request->motivo_cancelacion,
                'observaciones' => $request->observaciones
            ];
            $dictamen->metadata = $metadata;
            
            $dictamen->save();

            $this->logActivity(
                auth()->id(),
                'calidad',
                'cancelacion_dictamen',
                'dictamenes',
                "Dictamen cancelado: {$dictamen->folio} - Motivo: {$request->motivo_cancelacion}",
                'dictamenes',
                $dictamen->id,
                $datosAnteriores,
                $dictamen->toArray()
            );

            DB::commit();

            return $this->sendResponse($dictamen, 'Dictamen cancelado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al cancelar dictamen', [$e->getMessage()], 500);
        }
    }

    /**
     * Descargar archivo del dictamen
     */
    public function descargar($id)
    {
        $dictamen = Dictamen::find($id);

        if (!$dictamen) {
            return $this->sendError('Dictamen no encontrado');
        }

        if (!$dictamen->archivo_dictamen || !Storage::disk('public')->exists($dictamen->archivo_dictamen)) {
            return $this->sendError('Archivo no encontrado', [], 404);
        }

        $nombreArchivo = "dictamen_{$dictamen->folio}.pdf";

        return Storage::disk('public')->download($dictamen->archivo_dictamen, $nombreArchivo);
    }

    /**
     * Descargar anexo
     */
    public function descargarAnexo(Request $request, $id)
    {
        $dictamen = Dictamen::find($id);

        if (!$dictamen) {
            return $this->sendError('Dictamen no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'anexo_index' => 'required|integer|min:0'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        $anexos = $dictamen->archivos_anexos ?? [];
        
        if (!isset($anexos[$request->anexo_index])) {
            return $this->sendError('Anexo no encontrado', [], 404);
        }

        $anexo = $anexos[$request->anexo_index];
        
        if (!Storage::disk('public')->exists($anexo['ruta'])) {
            return $this->sendError('Archivo no encontrado', [], 404);
        }

        return Storage::disk('public')->download($anexo['ruta'], $anexo['nombre_original']);
    }

    /**
     * Validar dictamen contra especificaciones
     */
    public function validarEspecificaciones($id)
    {
        $dictamen = Dictamen::with('producto')->find($id);

        if (!$dictamen) {
            return $this->sendError('Dictamen no encontrado');
        }

        $producto = $dictamen->producto;
        $resultados = $dictamen->resultados;
        $validaciones = [];
        $conforme = true;
        $observaciones = [];

        // Validar contra especificaciones del producto
        if ($producto->especificaciones_calidad) {
            foreach ($producto->especificaciones_calidad as $espec) {
                $parametro = $espec['parametro'];
                $valorObtenido = null;

                // Buscar el valor en los resultados
                switch ($parametro) {
                    case 'densidad':
                        $valorObtenido = $resultados['densidad'] ?? null;
                        break;
                    case 'api_gravedad':
                        $valorObtenido = $resultados['api_gravedad'] ?? null;
                        break;
                    case 'viscosidad':
                        $valorObtenido = $resultados['viscosidad'] ?? null;
                        break;
                    case 'azufre':
                        $valorObtenido = $resultados['azufre'] ?? null;
                        break;
                    case 'octanaje_ron':
                        $valorObtenido = $resultados['octanaje_ron'] ?? null;
                        break;
                    case 'octanaje_mon':
                        $valorObtenido = $resultados['octanaje_mon'] ?? null;
                        break;
                }

                if ($valorObtenido !== null) {
                    $cumple = true;
                    $mensajes = [];

                    if (isset($espec['valor_min']) && $valorObtenido < $espec['valor_min']) {
                        $cumple = false;
                        $mensajes[] = "Valor ({$valorObtenido}) por debajo del mínimo ({$espec['valor_min']})";
                    }

                    if (isset($espec['valor_max']) && $valorObtenido > $espec['valor_max']) {
                        $cumple = false;
                        $mensajes[] = "Valor ({$valorObtenido}) por encima del máximo ({$espec['valor_max']})";
                    }

                    $validaciones[] = [
                        'parametro' => $parametro,
                        'especificacion' => $espec,
                        'valor_obtenido' => $valorObtenido,
                        'cumple' => $cumple,
                        'mensajes' => $mensajes
                    ];

                    if (!$cumple) {
                        $conforme = false;
                        $observaciones = array_merge($observaciones, $mensajes);
                    }
                }
            }
        }

        // Validar composición
        if (isset($resultados['composicion']) && $producto->composicion_tipica) {
            foreach ($resultados['composicion'] as $comp) {
                $tipico = collect($producto->composicion_tipica)
                    ->firstWhere('componente', $comp['componente']);

                if ($tipico) {
                    $diferencia = abs($comp['porcentaje'] - $tipico['porcentaje']);
                    $tolerancia = $tipico['porcentaje'] * 0.1; // 10% de tolerancia
                    
                    $cumple = $diferencia <= $tolerancia;
                    
                    $validaciones[] = [
                        'parametro' => 'composicion_' . $comp['componente'],
                        'componente' => $comp['componente'],
                        'valor_obtenido' => $comp['porcentaje'],
                        'valor_referencia' => $tipico['porcentaje'],
                        'diferencia' => $diferencia,
                        'tolerancia' => $tolerancia,
                        'cumple' => $cumple
                    ];

                    if (!$cumple) {
                        $conforme = false;
                        $observaciones[] = "Composición de {$comp['componente']} fuera de tolerancia (±10%)";
                    }
                }
            }
        }

        $resultadoValidacion = [
            'dictamen_id' => $dictamen->id,
            'dictamen_folio' => $dictamen->folio,
            'producto' => [
                'id' => $producto->id,
                'nombre' => $producto->nombre,
                'clave_sat' => $producto->clave_sat
            ],
            'validaciones' => $validaciones,
            'conforme' => $conforme,
            'observaciones' => $observaciones,
            'fecha_validacion' => now()->toDateTimeString()
        ];

        return $this->sendResponse($resultadoValidacion, 'Validación contra especificaciones completada');
    }

    /**
     * Obtener estadísticas de dictámenes
     */
    public function estadisticas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contribuyente_id' => 'required|exists:contribuyentes,id',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        $dictamenes = Dictamen::where('contribuyente_id', $request->contribuyente_id)
            ->whereBetween('fecha_emision', [
                Carbon::parse($request->fecha_inicio),
                Carbon::parse($request->fecha_fin)
            ])
            ->get();

        $estadisticas = [
            'periodo' => [
                'inicio' => $request->fecha_inicio,
                'fin' => $request->fecha_fin
            ],
            'contribuyente_id' => $request->contribuyente_id,
            'total_dictamenes' => $dictamenes->count(),
            'por_resultado' => $dictamenes->groupBy('resultado')
                ->map(function ($items) {
                    return [
                        'cantidad' => $items->count(),
                        'porcentaje' => round(($items->count() / max($dictamenes->count(), 1)) * 100, 2)
                    ];
                }),
            'por_tipo' => $dictamenes->groupBy('tipo_dictamen')
                ->map(function ($items) {
                    return $items->count();
                }),
            'por_producto' => $dictamenes->groupBy('producto_id')
                ->map(function ($items) {
                    $producto = $items->first()->producto;
                    return [
                        'producto' => $producto ? $producto->nombre : 'N/A',
                        'cantidad' => $items->count(),
                        'conformes' => $items->where('resultado', 'CONFORME')->count()
                    ];
                })->values(),
            'cumplimiento' => [
                'conformes' => $dictamenes->where('resultado', 'CONFORME')->count(),
                'no_conformes' => $dictamenes->where('resultado', 'NO_CONFORME')->count(),
                'con_observaciones' => $dictamenes->where('resultado', 'CON_OBSERVACIONES')->count(),
                'tasa_conformidad' => round(($dictamenes->where('resultado', 'CONFORME')->count() / max($dictamenes->count(), 1)) * 100, 2)
            ],
            'tendencia_mensual' => $dictamenes->groupBy(function ($item) {
                    return Carbon::parse($item->fecha_emision)->format('Y-m');
                })
                ->map(function ($items, $mes) {
                    return [
                        'mes' => $mes,
                        'total' => $items->count(),
                        'conformes' => $items->where('resultado', 'CONFORME')->count()
                    ];
                })->values()
        ];

        return $this->sendResponse($estadisticas, 'Estadísticas de dictámenes obtenidas exitosamente');
    }
}