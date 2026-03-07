<?php

namespace App\Http\Controllers;

use App\Models\Dispensario;
use App\Models\Instalacion;
use App\Models\Manguera;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DispensarioController extends BaseController
{
    /**
     * Listar dispensarios
     */
    public function index(Request $request)
    {
        $query = Dispensario::with(['instalacion', 'mangueras.medidor']);

        // Filtros
        if ($request->has('instalacion_id')) {
            $query->where('instalacion_id', $request->instalacion_id);
        }

        if ($request->has('codigo')) {
            $query->where('codigo', 'LIKE', "%{$request->codigo}%");
        }

        if ($request->has('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->has('marca')) {
            $query->where('marca', 'LIKE', "%{$request->marca}%");
        }

        if ($request->has('modelo')) {
            $query->where('modelo', 'LIKE', "%{$request->modelo}%");
        }

        if ($request->has('estatus')) {
            $query->where('estatus', $request->estatus);
        }

        if ($request->boolean('activo')) {
            $query->where('activo', true);
        }

        $dispensarios = $query->orderBy('instalacion_id')
            ->orderBy('codigo')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($dispensarios, 'Dispensarios obtenidos exitosamente');
    }

    /**
     * Crear dispensario
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'instalacion_id' => 'required|exists:instalaciones,id',
            'codigo' => 'required|string|max:50|unique:dispensarios,codigo,NULL,id,instalacion_id,' . $request->instalacion_id,
            'tipo' => 'required|in:COMBUSTIBLE,GLP,OTROS',
            'marca' => 'required|string|max:100',
            'modelo' => 'required|string|max:100',
            'numero_serie' => 'required|string|max:100|unique:dispensarios,numero_serie',
            'numero_equipo' => 'nullable|string|max:50',
            'fecha_fabricacion' => 'nullable|date',
            'fecha_instalacion' => 'nullable|date',
            'tecnologia' => 'required|in:MECANICO,ELECTRONICO,INTELIGENTE',
            'num_mangueras' => 'required|integer|min:1|max:32',
            'caudal_maximo' => 'nullable|numeric|min:0',
            'caudal_minimo' => 'nullable|numeric|min:0',
            'unidad_caudal' => 'nullable|in:LPM,GPM',
            'presion_trabajo' => 'nullable|numeric|min:0',
            'presion_maxima' => 'nullable|numeric|min:0',
            'unidad_presion' => 'nullable|in:PSI,BAR,KPA',
            'precision' => 'nullable|numeric|min:0',
            'resolucion' => 'nullable|numeric|min:0',
            'clase_exactitud' => 'nullable|string|max:50',
            'certificado_aprobacion' => 'nullable|string|max:100',
            'fecha_ultima_verificacion' => 'nullable|date',
            'fecha_proxima_verificacion' => 'nullable|date|after:fecha_ultima_verificacion',
            'configuracion_comunicacion' => 'nullable|array',
            'configuracion_comunicacion.tipo' => 'required_with:configuracion_comunicacion|in:RS232,RS485,ETHERNET,WIFI',
            'configuracion_comunicacion.direccion' => 'required_with:configuracion_comunicacion|string',
            'configuracion_comunicacion.baudrate' => 'nullable|integer',
            'configuracion_comunicacion.parametros' => 'nullable|array',
            'configuracion_comunicacion.activo' => 'boolean',
            'parametros_operacion' => 'nullable|array',
            'parametros_operacion.modos_operacion' => 'nullable|array',
            'parametros_operacion.tiene_precio' => 'boolean',
            'parametros_operacion.tiene_totalizadores' => 'boolean',
            'parametros_operacion.tiene_impresora' => 'boolean',
            'parametros_operacion.tiene_pantalla' => 'boolean',
            'parametros_operacion.tiene_teclado' => 'boolean',
            'ubicacion' => 'nullable|array',
            'ubicacion.coordenadas' => 'nullable|array',
            'ubicacion.descripcion' => 'nullable|string',
            'ubicacion.isla' => 'nullable|string',
            'ubicacion.lado' => 'nullable|in:IZQUIERDO,DERECHO,CENTRAL',
            'observaciones' => 'nullable|string|max:1000',
            'estatus' => 'required|in:OPERACION,MANTENIMIENTO,FALLA,INACTIVO',
            'activo' => 'boolean',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            // Validar instalación activa
            $instalacion = Instalacion::find($request->instalacion_id);
            if (!$instalacion->activo) {
                return $this->sendError('La instalación no está activa', [], 422);
            }

            $dispensario = Dispensario::create([
                'instalacion_id' => $request->instalacion_id,
                'codigo' => $request->codigo,
                'tipo' => $request->tipo,
                'marca' => $request->marca,
                'modelo' => $request->modelo,
                'numero_serie' => $request->numero_serie,
                'numero_equipo' => $request->numero_equipo,
                'fecha_fabricacion' => $request->fecha_fabricacion,
                'fecha_instalacion' => $request->fecha_instalacion,
                'tecnologia' => $request->tecnologia,
                'num_mangueras' => $request->num_mangueras,
                'caudal_maximo' => $request->caudal_maximo,
                'caudal_minimo' => $request->caudal_minimo,
                'unidad_caudal' => $request->unidad_caudal,
                'presion_trabajo' => $request->presion_trabajo,
                'presion_maxima' => $request->presion_maxima,
                'unidad_presion' => $request->unidad_presion,
                'precision' => $request->precision,
                'resolucion' => $request->resolucion,
                'clase_exactitud' => $request->clase_exactitud,
                'certificado_aprobacion' => $request->certificado_aprobacion,
                'fecha_ultima_verificacion' => $request->fecha_ultima_verificacion,
                'fecha_proxima_verificacion' => $request->fecha_proxima_verificacion,
                'configuracion_comunicacion' => $request->configuracion_comunicacion,
                'parametros_operacion' => $request->parametros_operacion,
                'ubicacion' => $request->ubicacion,
                'observaciones' => $request->observaciones,
                'estatus' => $request->estatus,
                'activo' => $request->boolean('activo', true),
                'metadata' => $request->metadata
            ]);

            $this->logActivity(
                auth()->id(),
                'configuracion',
                'creacion_dispensario',
                'dispensarios',
                "Dispensario creado: {$dispensario->codigo} - Serie: {$dispensario->numero_serie}",
                'dispensarios',
                $dispensario->id
            );

            DB::commit();

            return $this->sendResponse($dispensario->load('instalacion'), 
                'Dispensario creado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al crear dispensario', [$e->getMessage()], 500);
        }
    }

    /**
     * Mostrar dispensario
     */
    public function show($id)
    {
        $dispensario = Dispensario::with([
            'instalacion',
            'mangueras' => function($q) {
                $q->with('medidor')->orderBy('numero');
            }
        ])->find($id);

        if (!$dispensario) {
            return $this->sendError('Dispensario no encontrado');
        }

        // Calcular estadísticas
        $dispensario->estadisticas = $this->calcularEstadisticas($dispensario);

        return $this->sendResponse($dispensario, 'Dispensario obtenido exitosamente');
    }

    /**
     * Actualizar dispensario
     */
    public function update(Request $request, $id)
    {
        $dispensario = Dispensario::find($id);

        if (!$dispensario) {
            return $this->sendError('Dispensario no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'tipo' => 'sometimes|in:COMBUSTIBLE,GLP,OTROS',
            'marca' => 'sometimes|string|max:100',
            'modelo' => 'sometimes|string|max:100',
            'numero_equipo' => 'nullable|string|max:50',
            'tecnologia' => 'sometimes|in:MECANICO,ELECTRONICO,INTELIGENTE',
            'caudal_maximo' => 'nullable|numeric|min:0',
            'caudal_minimo' => 'nullable|numeric|min:0',
            'presion_trabajo' => 'nullable|numeric|min:0',
            'presion_maxima' => 'nullable|numeric|min:0',
            'precision' => 'nullable|numeric|min:0',
            'resolucion' => 'nullable|numeric|min:0',
            'clase_exactitud' => 'nullable|string|max:50',
            'certificado_aprobacion' => 'nullable|string|max:100',
            'fecha_ultima_verificacion' => 'nullable|date',
            'fecha_proxima_verificacion' => 'nullable|date|after:fecha_ultima_verificacion',
            'configuracion_comunicacion' => 'nullable|array',
            'parametros_operacion' => 'nullable|array',
            'ubicacion' => 'nullable|array',
            'observaciones' => 'nullable|string|max:1000',
            'estatus' => 'sometimes|in:OPERACION,MANTENIMIENTO,FALLA,INACTIVO',
            'activo' => 'sometimes|boolean',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $dispensario->toArray();
            $dispensario->update($request->all());

            $this->logActivity(
                auth()->id(),
                'configuracion',
                'actualizacion_dispensario',
                'dispensarios',
                "Dispensario actualizado: {$dispensario->codigo}",
                'dispensarios',
                $dispensario->id,
                $datosAnteriores,
                $dispensario->toArray()
            );

            DB::commit();

            return $this->sendResponse($dispensario, 'Dispensario actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al actualizar dispensario', [$e->getMessage()], 500);
        }
    }

    /**
     * Eliminar dispensario (soft delete)
     */
    public function destroy($id)
    {
        $dispensario = Dispensario::find($id);

        if (!$dispensario) {
            return $this->sendError('Dispensario no encontrado');
        }

        // Verificar si tiene mangueras activas
        $manguerasActivas = $dispensario->mangueras()->where('activo', true)->count();
        if ($manguerasActivas > 0) {
            return $this->sendError('No se puede eliminar el dispensario porque tiene mangueras activas', [], 409);
        }

        try {
            DB::beginTransaction();

            $dispensario->activo = false;
            $dispensario->estatus = 'INACTIVO';
            $dispensario->save();
            $dispensario->delete();

            $this->logActivity(
                auth()->id(),
                'configuracion',
                'eliminacion_dispensario',
                'dispensarios',
                "Dispensario eliminado: {$dispensario->codigo}",
                'dispensarios',
                $dispensario->id
            );

            DB::commit();

            return $this->sendResponse([], 'Dispensario eliminado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al eliminar dispensario', [$e->getMessage()], 500);
        }
    }

    /**
     * Obtener mangueras del dispensario
     */
    public function mangueras(Request $request, $id)
    {
        $dispensario = Dispensario::find($id);

        if (!$dispensario) {
            return $this->sendError('Dispensario no encontrado');
        }

        $query = $dispensario->mangueras()->with('medidor');

        if ($request->has('producto_id')) {
            $query->where('producto_id', $request->producto_id);
        }

        if ($request->has('estatus')) {
            $query->where('estatus', $request->estatus);
        }

        if ($request->boolean('activas')) {
            $query->where('activo', true);
        }

        $mangueras = $query->orderBy('numero')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($mangueras, 'Mangueras del dispensario obtenidas exitosamente');
    }

    /**
     * Agregar manguera al dispensario
     */
    public function agregarManguera(Request $request, $id)
    {
        $dispensario = Dispensario::find($id);

        if (!$dispensario) {
            return $this->sendError('Dispensario no encontrado');
        }

        // Verificar que no exceda el número máximo de mangueras
        $manguerasActuales = $dispensario->mangueras()->count();
        if ($manguerasActuales >= $dispensario->num_mangueras) {
            return $this->sendError("El dispensario ya tiene el máximo de mangueras ({$dispensario->num_mangueras})", [], 409);
        }

        $validator = Validator::make($request->all(), [
            'numero' => 'required|integer|min:1|unique:mangueras,numero,NULL,id,dispensario_id,' . $id,
            'producto_id' => 'required|exists:productos,id',
            'medidor_id' => 'nullable|exists:medidores,id',
            'lado' => 'required|in:IZQUIERDO,DERECHO,CENTRAL',
            'longitud' => 'nullable|numeric|min:0',
            'unidad_longitud' => 'nullable|in:METROS,PIES',
            'diametro' => 'nullable|numeric|min:0',
            'unidad_diametro' => 'nullable|in:PULGADAS,MM',
            'color' => 'nullable|string|max:50',
            'fecha_instalacion' => 'nullable|date',
            'fecha_ultima_prueba' => 'nullable|date',
            'fecha_proxima_prueba' => 'nullable|date|after:fecha_ultima_prueba',
            'presion_trabajo' => 'nullable|numeric|min:0',
            'presion_prueba' => 'nullable|numeric|min:0',
            'observaciones' => 'nullable|string|max:500',
            'estatus' => 'required|in:OPERACION,MANTENIMIENTO,FALLA,INACTIVO'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            // Validar que el medidor no esté asignado a otra manguera
            if ($request->has('medidor_id') && $request->medidor_id) {
                $medidorAsignado = Manguera::where('medidor_id', $request->medidor_id)
                    ->where('activo', true)
                    ->exists();
                
                if ($medidorAsignado) {
                    return $this->sendError('El medidor ya está asignado a otra manguera', [], 422);
                }
            }

            $manguera = Manguera::create([
                'dispensario_id' => $id,
                'numero' => $request->numero,
                'producto_id' => $request->producto_id,
                'medidor_id' => $request->medidor_id,
                'lado' => $request->lado,
                'longitud' => $request->longitud,
                'unidad_longitud' => $request->unidad_longitud,
                'diametro' => $request->diametro,
                'unidad_diametro' => $request->unidad_diametro,
                'color' => $request->color,
                'fecha_instalacion' => $request->fecha_instalacion,
                'fecha_ultima_prueba' => $request->fecha_ultima_prueba,
                'fecha_proxima_prueba' => $request->fecha_proxima_prueba,
                'presion_trabajo' => $request->presion_trabajo,
                'presion_prueba' => $request->presion_prueba,
                'observaciones' => $request->observaciones,
                'estatus' => $request->estatus,
                'activo' => true
            ]);

            $this->logActivity(
                auth()->id(),
                'configuracion',
                'agregar_manguera',
                'dispensarios',
                "Manguera {$request->numero} agregada a dispensario {$dispensario->codigo}",
                'dispensarios',
                $dispensario->id
            );

            DB::commit();

            return $this->sendResponse($manguera->load('producto', 'medidor'), 
                'Manguera agregada exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al agregar manguera', [$e->getMessage()], 500);
        }
    }

    /**
     * Verificar estado del dispensario
     */
    public function verificarEstado($id)
    {
        $dispensario = Dispensario::with('mangueras')->find($id);

        if (!$dispensario) {
            return $this->sendError('Dispensario no encontrado');
        }

        $alertas = [];

        // Verificar mangueras
        $manguerasOperativas = $dispensario->mangueras->where('estatus', 'OPERACION')->count();
        $manguerasFalla = $dispensario->mangueras->where('estatus', 'FALLA')->count();

        if ($manguerasFalla > 0) {
            $alertas[] = [
                'tipo' => 'MANGUERAS_FALLA',
                'severidad' => 'ALTA',
                'mensaje' => "{$manguerasFalla} manguera(s) en estado FALLA"
            ];
        }

        // Verificar próxima verificación
        if ($dispensario->fecha_proxima_verificacion) {
            $diasRestantes = now()->diffInDays($dispensario->fecha_proxima_verificacion, false);
            
            if ($diasRestantes <= 0) {
                $alertas[] = [
                    'tipo' => 'VERIFICACION_VENCIDA',
                    'severidad' => 'CRITICA',
                    'mensaje' => "La verificación del dispensario venció el {$dispensario->fecha_proxima_verificacion->format('d/m/Y')}"
                ];
            } elseif ($diasRestantes <= 30) {
                $alertas[] = [
                    'tipo' => 'VERIFICACION_PROXIMA',
                    'severidad' => 'MEDIA',
                    'mensaje' => "Próxima verificación en {$diasRestantes} días"
                ];
            }
        }

        // Verificar comunicación
        if ($dispensario->configuracion_comunicacion && $dispensario->configuracion_comunicacion['activo'] ?? false) {
            $prueba = $this->simularPruebaComunicacion($dispensario);
            if (!$prueba['exitosa']) {
                $alertas[] = [
                    'tipo' => 'FALLA_COMUNICACION',
                    'severidad' => 'ALTA',
                    'mensaje' => 'Falla en la comunicación con el dispensario',
                    'detalle' => $prueba['detalle']
                ];
            }
        }

        $estado = [
            'dispensario_id' => $dispensario->id,
            'codigo' => $dispensario->codigo,
            'estatus' => $dispensario->estatus,
            'activo' => $dispensario->activo,
            'mangueras' => [
                'total' => $dispensario->mangueras->count(),
                'operativas' => $manguerasOperativas,
                'en_falla' => $manguerasFalla
            ],
            'verificacion' => $dispensario->fecha_proxima_verificacion ? [
                'ultima' => $dispensario->fecha_ultima_verificacion?->format('Y-m-d'),
                'proxima' => $dispensario->fecha_proxima_verificacion->format('Y-m-d'),
                'dias_restantes' => now()->diffInDays($dispensario->fecha_proxima_verificacion, false)
            ] : null,
            'comunicacion' => $dispensario->configuracion_comunicacion ? [
                'configurada' => true,
                'tipo' => $dispensario->configuracion_comunicacion['tipo'] ?? null,
                'activa' => $dispensario->configuracion_comunicacion['activo'] ?? false
            ] : ['configurada' => false],
            'alertas' => $alertas,
            'fecha_verificacion' => now()->toDateTimeString()
        ];

        return $this->sendResponse($estado, 'Estado del dispensario verificado exitosamente');
    }

    /**
     * Obtener lecturas en tiempo real
     */
    public function lecturasTiempoReal($id)
    {
        $dispensario = Dispensario::with('mangueras.medidor')->find($id);

        if (!$dispensario) {
            return $this->sendError('Dispensario no encontrado');
        }

        if ($dispensario->estatus != 'OPERACION') {
            return $this->sendError('El dispensario no está en operación', [], 400);
        }

        $lecturas = [];

        foreach ($dispensario->mangueras as $manguera) {
            if ($manguera->medidor && $manguera->activo) {
                $lecturas[] = [
                    'manguera_id' => $manguera->id,
                    'numero' => $manguera->numero,
                    'producto' => $manguera->producto->nombre ?? 'N/A',
                    'lectura' => $this->simularLecturaManguera($manguera),
                    'timestamp' => now()->toDateTimeString()
                ];
            }
        }

        return $this->sendResponse([
            'dispensario' => [
                'id' => $dispensario->id,
                'codigo' => $dispensario->codigo,
                'modelo' => $dispensario->modelo
            ],
            'lecturas' => $lecturas,
            'total_venta' => collect($lecturas)->sum('lectura.monto'),
            'total_volumen' => collect($lecturas)->sum('lectura.volumen')
        ], 'Lecturas en tiempo real obtenidas exitosamente');
    }

    /**
     * Métodos privados
     */
    private function calcularEstadisticas($dispensario)
    {
        $mangueras = $dispensario->mangueras;
        $hoy = Carbon::today();
        $inicioMes = Carbon::now()->startOfMonth();

        // Total de ventas del día (simulado)
        $ventasHoy = $mangueras->count() * rand(10, 50);
        $ventasMes = $ventasHoy * now()->day;

        return [
            'mangueras' => [
                'total' => $mangueras->count(),
                'operativas' => $mangueras->where('estatus', 'OPERACION')->count(),
                'por_producto' => $mangueras->groupBy('producto_id')
                    ->map(function ($items) {
                        return [
                            'cantidad' => $items->count(),
                            'producto' => $items->first()->producto->nombre ?? 'N/A'
                        ];
                    })->values()
            ],
            'ventas' => [
                'hoy' => $ventasHoy,
                'mes' => $ventasMes,
                'promedio_diario' => $ventasMes / max(now()->day, 1)
            ],
            'operatividad' => [
                'dias_operacion' => 30 - $mangueras->where('estatus', 'MANTENIMIENTO')->count() * 2,
                'porcentaje_uso' => round(($mangueras->where('estatus', 'OPERACION')->count() / max($mangueras->count(), 1)) * 100, 2)
            ]
        ];
    }

    private function simularPruebaComunicacion($dispensario)
    {
        $tipos = [
            'RS232' => ['exitosa' => true, 'latencia' => rand(100, 300)],
            'RS485' => ['exitosa' => true, 'latencia' => rand(80, 250)],
            'ETHERNET' => ['exitosa' => true, 'latencia' => rand(10, 100)],
            'WIFI' => ['exitosa' => true, 'latencia' => rand(20, 150)],
        ];

        $tipo = $dispensario->configuracion_comunicacion['tipo'] ?? 'ETHERNET';
        $simulacion = $tipos[$tipo] ?? $tipos['ETHERNET'];

        // 95% de éxito para simular realidad
        $exitosa = rand(1, 100) <= 95;

        if (!$exitosa) {
            return [
                'exitosa' => false,
                'latencia_ms' => null,
                'detalle' => 'Error de comunicación - No respuesta del dispensario'
            ];
        }

        return [
            'exitosa' => true,
            'latencia_ms' => $simulacion['latencia'],
            'detalle' => "Comunicación establecida vía {$tipo}"
        ];
    }

    private function simularLecturaManguera($manguera)
    {
        $volumen = rand(10, 500) / 10;
        $precioPorLitro = rand(200, 250) / 10; // $20.0 a $25.0 por litro
        
        return [
            'volumen' => $volumen,
            'precio_unitario' => $precioPorLitro,
            'monto' => $volumen * $precioPorLitro,
            'medidor' => $manguera->medidor ? [
                'id' => $manguera->medidor->id,
                'lectura_actual' => rand(10000, 99999) / 10,
                'unidad' => $manguera->medidor->unidad_medicion
            ] : null,
            'calidad_senal' => rand(85, 100) . '%'
        ];
    }
}