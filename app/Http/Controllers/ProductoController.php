<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ProductoController extends BaseController
{
    /**
     * Listar productos
     */
    public function index(Request $request)
    {
        $query = Producto::query();

        // Filtros
        if ($request->has('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->has('clave_sat')) {
            $query->where('clave_sat', 'LIKE', "%{$request->clave_sat}%");
        }

        if ($request->has('nombre')) {
            $query->where('nombre', 'LIKE', "%{$request->nombre}%");
        }

        if ($request->has('clasificacion')) {
            $query->where('clasificacion', $request->clasificacion);
        }

        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        if ($request->has('requiere_dictamen')) {
            $query->where('requiere_dictamen', $request->boolean('requiere_dictamen'));
        }

        if ($request->has('permiso_cre')) {
            $query->where('permiso_cre', 'LIKE', "%{$request->permiso_cre}%");
        }

        $productos = $query->orderBy('tipo')
            ->orderBy('nombre')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($productos, 'Productos obtenidos exitosamente');
    }

    /**
     * Crear producto
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'clave_sat' => 'required|string|max:20|unique:productos,clave_sat',
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|in:HIDROCARBURO,PETROLIFERO,GAS_NATURAL,GAS_LP,BIOCOMBUSTIBLE',
            'clasificacion' => 'required|in:CRUDO,CONDENSADO,GASOLINA,DIESEL,TURBOSINA,COMBUSTOLEO,ASFALTO,GAS_NATURAL,GAS_LP,ETANOL,BIODIESEL,OTRO',
            'subtipo' => 'nullable|string|max:100',
            'descripcion' => 'nullable|string|max:500',
            'unidad_medida' => 'required|in:LITROS,M3,BARRILES,GALONES,KG,TONELADAS',
            'factor_conversion_m3' => 'required|numeric|min:0',
            'factor_conversion_barriles' => 'required|numeric|min:0',
            'densidad_referencia' => 'nullable|numeric|min:0',
            'temperatura_referencia' => 'nullable|numeric',
            'api_gravedad' => 'nullable|numeric|min:0|max:100',
            'viscosidad' => 'nullable|numeric|min:0',
            'poder_calorifico_inferior' => 'nullable|numeric|min:0',
            'poder_calorifico_superior' => 'nullable|numeric|min:0',
            'unidad_poder_calorifico' => 'nullable|in:MJ/m3,BTU/pie3,Kcal/m3',
            'presion_vapor' => 'nullable|numeric|min:0',
            'punto_inflamacion' => 'nullable|numeric',
            'punto_ebullicion' => 'nullable|numeric',
            'composicion_tipica' => 'nullable|array',
            'composicion_tipica.*.componente' => 'required_with:composicion_tipica|string',
            'composicion_tipica.*.porcentaje' => 'required_with:composicion_tipica|numeric|min:0|max:100',
            'especificaciones_calidad' => 'nullable|array',
            'especificaciones_calidad.*.parametro' => 'required_with:especificaciones_calidad|string',
            'especificaciones_calidad.*.valor_min' => 'nullable|numeric',
            'especificaciones_calidad.*.valor_max' => 'nullable|numeric',
            'especificaciones_calidad.*.unidad' => 'required_with:especificaciones_calidad|string',
            'requiere_dictamen' => 'boolean',
            'permiso_cre' => 'nullable|string|max:50',
            'normas_aplicables' => 'nullable|array',
            'normas_aplicables.*' => 'string',
            'activo' => 'boolean',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            // Validaciones específicas por tipo
            if ($request->tipo == 'HIDROCARBURO' && !$request->api_gravedad) {
                return $this->sendError('Para hidrocarburos es requerido el API gravedad', [], 422);
            }

            if (in_array($request->tipo, ['GAS_NATURAL', 'GAS_LP']) && !$request->poder_calorifico_inferior) {
                return $this->sendError('Para gases es requerido el poder calorífico', [], 422);
            }

            if (in_array($request->clasificacion, ['GASOLINA', 'DIESEL']) && !$request->especificaciones_calidad) {
                return $this->sendError('Para combustibles es requerido especificaciones de calidad', [], 422);
            }

            $producto = Producto::create($request->all());

            $this->logActivity(
                auth()->id(),
                'catalogos',
                'creacion_producto',
                'productos',
                "Producto creado: {$producto->clave_sat} - {$producto->nombre}",
                'productos',
                $producto->id
            );

            DB::commit();

            return $this->sendResponse($producto, 'Producto creado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al crear producto', [$e->getMessage()], 500);
        }
    }

    /**
     * Mostrar producto
     */
    public function show($id)
    {
        $producto = Producto::with([
            'tanques' => function($q) {
                $q->where('activo', true)->limit(10);
            },
            'dictamenes' => function($q) {
                $q->latest()->limit(5);
            }
        ])->find($id);

        if (!$producto) {
            return $this->sendError('Producto no encontrado');
        }

        // Calcular estadísticas de uso
        $producto->estadisticas = $this->calcularEstadisticasProducto($producto);

        return $this->sendResponse($producto, 'Producto obtenido exitosamente');
    }

    /**
     * Actualizar producto
     */
    public function update(Request $request, $id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return $this->sendError('Producto no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'clave_sat' => "sometimes|string|max:20|unique:productos,clave_sat,{$id}",
            'nombre' => 'sometimes|string|max:255',
            'tipo' => 'sometimes|in:HIDROCARBURO,PETROLIFERO,GAS_NATURAL,GAS_LP,BIOCOMBUSTIBLE',
            'clasificacion' => 'sometimes|in:CRUDO,CONDENSADO,GASOLINA,DIESEL,TURBOSINA,COMBUSTOLEO,ASFALTO,GAS_NATURAL,GAS_LP,ETANOL,BIODIESEL,OTRO',
            'subtipo' => 'nullable|string|max:100',
            'descripcion' => 'nullable|string|max:500',
            'unidad_medida' => 'sometimes|in:LITROS,M3,BARRILES,GALONES,KG,TONELADAS',
            'factor_conversion_m3' => 'sometimes|numeric|min:0',
            'factor_conversion_barriles' => 'sometimes|numeric|min:0',
            'densidad_referencia' => 'nullable|numeric|min:0',
            'temperatura_referencia' => 'nullable|numeric',
            'api_gravedad' => 'nullable|numeric|min:0|max:100',
            'viscosidad' => 'nullable|numeric|min:0',
            'poder_calorifico_inferior' => 'nullable|numeric|min:0',
            'poder_calorifico_superior' => 'nullable|numeric|min:0',
            'unidad_poder_calorifico' => 'nullable|in:MJ/m3,BTU/pie3,Kcal/m3',
            'presion_vapor' => 'nullable|numeric|min:0',
            'punto_inflamacion' => 'nullable|numeric',
            'punto_ebullicion' => 'nullable|numeric',
            'composicion_tipica' => 'nullable|array',
            'especificaciones_calidad' => 'nullable|array',
            'requiere_dictamen' => 'boolean',
            'permiso_cre' => 'nullable|string|max:50',
            'normas_aplicables' => 'nullable|array',
            'activo' => 'sometimes|boolean',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $producto->toArray();
            $producto->update($request->all());

            $this->logActivity(
                auth()->id(),
                'catalogos',
                'actualizacion_producto',
                'productos',
                "Producto actualizado: {$producto->clave_sat}",
                'productos',
                $producto->id,
                $datosAnteriores,
                $producto->toArray()
            );

            DB::commit();

            return $this->sendResponse($producto, 'Producto actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al actualizar producto', [$e->getMessage()], 500);
        }
    }

    /**
     * Eliminar producto (soft delete)
     */
    public function destroy($id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return $this->sendError('Producto no encontrado');
        }

        // Verificar si tiene tanques asociados
        $tieneTanques = $producto->tanques()->where('activo', true)->exists();
        
        if ($tieneTanques) {
            return $this->sendError('No se puede eliminar el producto porque tiene tanques activos asociados', [], 409);
        }

        try {
            DB::beginTransaction();

            $producto->activo = false;
            $producto->save();
            $producto->delete();

            $this->logActivity(
                auth()->id(),
                'catalogos',
                'eliminacion_producto',
                'productos',
                "Producto eliminado: {$producto->clave_sat}",
                'productos',
                $producto->id
            );

            DB::commit();

            return $this->sendResponse([], 'Producto eliminado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al eliminar producto', [$e->getMessage()], 500);
        }
    }

    /**
     * Obtener productos por tipo
     */
    public function porTipo($tipo)
    {
        $tiposValidos = ['HIDROCARBURO', 'PETROLIFERO', 'GAS_NATURAL', 'GAS_LP', 'BIOCOMBUSTIBLE'];
        
        if (!in_array($tipo, $tiposValidos)) {
            return $this->sendError('Tipo de producto no válido', [], 422);
        }

        $productos = Producto::where('tipo', $tipo)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        return $this->sendResponse($productos, "Productos tipo {$tipo} obtenidos exitosamente");
    }

    /**
     * Verificar especificaciones
     */
    public function verificarEspecificaciones(Request $request, $id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return $this->sendError('Producto no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'densidad' => 'nullable|numeric',
            'api_gravedad' => 'nullable|numeric',
            'viscosidad' => 'nullable|numeric',
            'poder_calorifico' => 'nullable|numeric',
            'composicion' => 'nullable|array',
            'composicion.*.componente' => 'required_with:composicion|string',
            'composicion.*.porcentaje' => 'required_with:composicion|numeric|min:0|max:100'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        $resultado = [
            'producto_id' => $producto->id,
            'producto_nombre' => $producto->nombre,
            'verificaciones' => [],
            'conforme' => true,
            'observaciones' => []
        ];

        // Verificar densidad si se proporciona
        if ($request->has('densidad') && $producto->especificaciones_calidad) {
            foreach ($producto->especificaciones_calidad as $espec) {
                if ($espec['parametro'] == 'densidad') {
                    $conforme = true;
                    if (isset($espec['valor_min']) && $request->densidad < $espec['valor_min']) {
                        $conforme = false;
                        $resultado['observaciones'][] = "Densidad ({$request->densidad}) por debajo del mínimo ({$espec['valor_min']})";
                    }
                    if (isset($espec['valor_max']) && $request->densidad > $espec['valor_max']) {
                        $conforme = false;
                        $resultado['observaciones'][] = "Densidad ({$request->densidad}) por encima del máximo ({$espec['valor_max']})";
                    }
                    
                    $resultado['verificaciones'][] = [
                        'parametro' => 'densidad',
                        'valor' => $request->densidad,
                        'especificacion' => $espec,
                        'conforme' => $conforme
                    ];
                    
                    if (!$conforme) {
                        $resultado['conforme'] = false;
                    }
                    break;
                }
            }
        }

        // Verificar API gravedad
        if ($request->has('api_gravedad') && $producto->api_gravedad) {
            $diferencia = abs($request->api_gravedad - $producto->api_gravedad);
            $tolerancia = $producto->api_gravedad * 0.05; // 5% de tolerancia
            
            $conforme = $diferencia <= $tolerancia;
            
            $resultado['verificaciones'][] = [
                'parametro' => 'api_gravedad',
                'valor' => $request->api_gravedad,
                'referencia' => $producto->api_gravedad,
                'diferencia' => $diferencia,
                'tolerancia' => $tolerancia,
                'conforme' => $conforme
            ];
            
            if (!$conforme) {
                $resultado['conforme'] = false;
                $resultado['observaciones'][] = "API gravedad fuera de tolerancia (±5%)";
            }
        }

        // Verificar composición
        if ($request->has('composicion') && $producto->composicion_tipica) {
            foreach ($request->composicion as $comp) {
                $tipico = collect($producto->composicion_tipica)
                    ->firstWhere('componente', $comp['componente']);
                
                if ($tipico) {
                    $diferencia = abs($comp['porcentaje'] - $tipico['porcentaje']);
                    $tolerancia = $tipico['porcentaje'] * 0.1; // 10% de tolerancia
                    
                    $conforme = $diferencia <= $tolerancia;
                    
                    $resultado['verificaciones'][] = [
                        'parametro' => 'composicion_' . $comp['componente'],
                        'componente' => $comp['componente'],
                        'valor' => $comp['porcentaje'],
                        'referencia' => $tipico['porcentaje'],
                        'diferencia' => $diferencia,
                        'tolerancia' => $tolerancia,
                        'conforme' => $conforme
                    ];
                    
                    if (!$conforme) {
                        $resultado['conforme'] = false;
                        $resultado['observaciones'][] = "Composición de {$comp['componente']} fuera de tolerancia (±10%)";
                    }
                }
            }
        }

        return $this->sendResponse($resultado, 'Verificación de especificaciones completada');
    }

    /**
     * Obtener factor de conversión
     */
    public function factorConversion(Request $request, $id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return $this->sendError('Producto no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'de_unidad' => 'required|in:LITROS,M3,BARRILES,GALONES,KG,TONELADAS',
            'a_unidad' => 'required|in:LITROS,M3,BARRILES,GALONES,KG,TONELADAS|different:de_unidad',
            'temperatura' => 'nullable|numeric',
            'presion' => 'nullable|numeric'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        $factor = $this->calcularFactorConversion(
            $producto,
            $request->de_unidad,
            $request->a_unidad,
            $request->temperatura,
            $request->presion
        );

        return $this->sendResponse([
            'producto' => [
                'id' => $producto->id,
                'clave_sat' => $producto->clave_sat,
                'nombre' => $producto->nombre
            ],
            'conversion' => [
                'de' => $request->de_unidad,
                'a' => $request->a_unidad,
                'factor' => $factor,
                'condiciones' => [
                    'temperatura' => $request->temperatura ?? $producto->temperatura_referencia,
                    'presion' => $request->presion ?? 101.325
                ]
            ]
        ], 'Factor de conversión obtenido exitosamente');
    }

    /**
     * Obtener catálogo para dropdowns
     */
    public function catalogo()
    {
        $productos = Producto::where('activo', true)
            ->select('id', 'clave_sat', 'nombre', 'tipo', 'clasificacion', 'unidad_medida')
            ->orderBy('tipo')
            ->orderBy('nombre')
            ->get()
            ->groupBy('tipo');

        return $this->sendResponse($productos, 'Catálogo de productos obtenido exitosamente');
    }

    /**
     * Métodos privados
     */
    private function calcularEstadisticasProducto($producto)
    {
        $registros = DB::table('registros_volumetricos')
            ->where('producto_id', $producto->id)
            ->where('fecha_operacion', '>=', now()->subDays(30))
            ->get();

        return [
            'total_registros_30d' => $registros->count(),
            'volumen_total_30d' => $registros->sum('volumen_corregido'),
            'tanques_activos' => $producto->tanques()->where('activo', true)->count(),
            'instalaciones' => $producto->tanques()
                ->with('instalacion')
                ->get()
                ->pluck('instalacion')
                ->unique('id')
                ->values()
                ->map(function($inst) {
                    return [
                        'id' => $inst->id,
                        'clave' => $inst->clave_instalacion,
                        'nombre' => $inst->nombre
                    ];
                }),
            'fecha_primer_registro' => DB::table('registros_volumetricos')
                ->where('producto_id', $producto->id)
                ->min('fecha_operacion'),
            'fecha_ultimo_registro' => DB::table('registros_volumetricos')
                ->where('producto_id', $producto->id)
                ->max('fecha_operacion')
        ];
    }

    private function calcularFactorConversion($producto, $de, $a, $temperatura = null, $presion = null)
    {
        // Factores base
        $factores = [
            'LITROS' => 1,
            'M3' => 1000,
            'BARRILES' => 158.987,
            'GALONES' => 3.78541,
            'KG' => 1,
            'TONELADAS' => 1000
        ];

        // Convertir a litros como unidad base para volumen
        if (in_array($de, ['LITROS', 'M3', 'BARRILES', 'GALONES'])) {
            $valorEnLitros = 1 * $factores[$de];
            
            // Aplicar corrección por temperatura si es necesario
            if ($temperatura && $producto->densidad_referencia) {
                $coeficiente = $producto->tipo == 'HIDROCARBURO' ? 0.0005 : 0.0006;
                $factorCorreccion = 1 + ($coeficiente * ($producto->temperatura_referencia - $temperatura));
                $valorEnLitros *= $factorCorreccion;
            }
            
            // Convertir a la unidad destino
            return $valorEnLitros / $factores[$a];
        }
        
        // Para conversiones masa-volumen
        if ($producto->densidad_referencia) {
            if ($de == 'KG' || $de == 'TONELADAS') {
                $valorEnKg = 1 * $factores[$de];
                $volumenEnLitros = $valorEnKg / $producto->densidad_referencia;
                return $volumenEnLitros / $factores[$a];
            } else {
                $valorEnLitros = 1 * $factores[$de];
                $masaEnKg = $valorEnLitros * $producto->densidad_referencia;
                return $masaEnKg / $factores[$a];
            }
        }

        return 1; // Factor por defecto
    }
}