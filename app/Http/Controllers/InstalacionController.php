<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Instalacion;
use App\Models\Contribuyente;
use App\Models\Tanque;
use App\Models\Medidor;
use App\Models\Dispensario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InstalacionController extends BaseController
{
    /**
     * Listar instalaciones
     */
    public function index(Request $request)
    {
        $query = Instalacion::with(['contribuyente']);

        // Filtros
        if ($request->has('contribuyente_id')) {
            $query->where('contribuyente_id', $request->contribuyente_id);
        }

        if ($request->has('clave_instalacion')) {
            $query->where('clave_instalacion', 'LIKE', "%{$request->clave_instalacion}%");
        }

        if ($request->has('nombre')) {
            $query->where('nombre', 'LIKE', "%{$request->nombre}%");
        }

        if ($request->has('tipo_instalacion')) {
            $query->where('tipo_instalacion', $request->tipo_instalacion);
        }

        if ($request->has('estatus')) {
            $query->where('estatus', $request->estatus);
        }

        if ($request->has('municipio')) {
            $query->where('municipio', 'LIKE', "%{$request->municipio}%");
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        $instalaciones = $query->orderBy('contribuyente_id')
            ->orderBy('clave_instalacion')
            ->paginate($request->get('per_page', 15));

        return $this->success($instalaciones, 'Instalaciones obtenidas exitosamente');
    }

    /**
     * Crear instalación
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contribuyente_id' => 'required|exists:contribuyentes,id',
            'clave_instalacion' => 'required|string|max:255|unique:instalaciones,clave_instalacion',
            'nombre' => 'required|string|max:255',
            'tipo_instalacion' => 'required|string|max:255',
            'domicilio' => 'required|string|max:255',
            'codigo_postal' => 'required|string|size:5',
            'municipio' => 'required|string|max:255',
            'estado' => 'required|string|max:255',
            'latitud' => 'nullable|numeric|between:-90,90',
            'longitud' => 'nullable|numeric|between:-180,180',
            'telefono' => 'nullable|string|max:20',
            'responsable' => 'nullable|string|max:255',
            'fecha_operacion' => 'nullable|date',
            'estatus' => 'required|in:OPERACION,SUSPENDIDA,CANCELADA',
            'configuracion_monitoreo' => 'nullable|array',
            'parametros_volumetricos' => 'nullable|array',
            'umbrales_alarma' => 'nullable|array',
            'observaciones' => 'nullable|string',
            'activo' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            // Validar contribuyente activo
            $contribuyente = Contribuyente::find($request->contribuyente_id);
            if (!$contribuyente || !$contribuyente->activo) {
                return $this->error('El contribuyente no está activo', 422);
            }

            $instalacion = Instalacion::create([
                'contribuyente_id' => $request->contribuyente_id,
                'clave_instalacion' => $request->clave_instalacion,
                'nombre' => $request->nombre,
                'tipo_instalacion' => $request->tipo_instalacion,
                'domicilio' => $request->domicilio,
                'codigo_postal' => $request->codigo_postal,
                'municipio' => $request->municipio,
                'estado' => $request->estado,
                'latitud' => $request->latitud,
                'longitud' => $request->longitud,
                'telefono' => $request->telefono,
                'responsable' => $request->responsable,
                'fecha_operacion' => $request->fecha_operacion,
                'estatus' => $request->estatus,
                'configuracion_monitoreo' => $request->configuracion_monitoreo,
                'parametros_volumetricos' => $request->parametros_volumetricos,
                'umbrales_alarma' => $request->umbrales_alarma,
                'observaciones' => $request->observaciones,
                'activo' => $request->boolean('activo', true),
            ]);

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'CREACION_INSTALACION',
                'Configuración',
                "Instalación creada: {$instalacion->clave_instalacion} - {$instalacion->nombre}",
                'instalaciones',
                $instalacion->id
            );

            DB::commit();

            return $this->success($instalacion->load('contribuyente'), 'Instalación creada exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al crear instalación: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mostrar instalación
     */
    public function show($id)
    {
        $instalacion = Instalacion::with([
            'contribuyente',
            'tanques' => function($q) {
                $q->with('producto')->orderBy('identificador');
            },
            'medidores' => function($q) {
                $q->orderBy('numero_serie');
            },
            'dispensarios' => function($q) {
                $q->with('mangueras')->orderBy('clave');
            },
            'reportesSat' => function($q) {
                $q->latest()->limit(6);
            },
            'alarmas' => function($q) {
                $q->latest()->limit(10);
            }
        ])->find($id);

        if (!$instalacion) {
            return $this->error('Instalación no encontrada', 404);
        }

        // Calcular estadísticas
        $instalacion->estadisticas = $this->calcularEstadisticas($instalacion);

        return $this->success($instalacion, 'Instalación obtenida exitosamente');
    }

    /**
     * Actualizar instalación
     */
    public function update(Request $request, $id)
    {
        $instalacion = Instalacion::find($id);

        if (!$instalacion) {
            return $this->error('Instalación no encontrada', 404);
        }

        $validator = Validator::make($request->all(), [
            'clave_instalacion' => "sometimes|string|max:255|unique:instalaciones,clave_instalacion,{$id}",
            'nombre' => 'sometimes|string|max:255',
            'tipo_instalacion' => 'sometimes|string|max:255',
            'domicilio' => 'sometimes|string|max:255',
            'codigo_postal' => 'sometimes|string|size:5',
            'municipio' => 'sometimes|string|max:255',
            'estado' => 'sometimes|string|max:255',
            'latitud' => 'nullable|numeric|between:-90,90',
            'longitud' => 'nullable|numeric|between:-180,180',
            'telefono' => 'nullable|string|max:20',
            'responsable' => 'nullable|string|max:255',
            'fecha_operacion' => 'nullable|date',
            'estatus' => 'sometimes|in:OPERACION,SUSPENDIDA,CANCELADA',
            'configuracion_monitoreo' => 'nullable|array',
            'parametros_volumetricos' => 'nullable|array',
            'umbrales_alarma' => 'nullable|array',
            'observaciones' => 'nullable|string',
            'activo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $instalacion->toArray();
            $instalacion->update($request->all());

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'ACTUALIZACION_INSTALACION',
                'Configuración',
                "Instalación actualizada: {$instalacion->clave_instalacion}",
                'instalaciones',
                $instalacion->id,
                $datosAnteriores,
                $instalacion->toArray()
            );

            DB::commit();

            return $this->success($instalacion, 'Instalación actualizada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al actualizar instalación: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar instalación (soft delete)
     */
    public function destroy($id)
    {
        $instalacion = Instalacion::find($id);

        if (!$instalacion) {
            return $this->error('Instalación no encontrada', 404);
        }

        // Verificar si tiene tanques activos
        $tanquesActivos = $instalacion->tanques()->where('activo', true)->count();
        if ($tanquesActivos > 0) {
            return $this->error("No se puede eliminar la instalación porque tiene {$tanquesActivos} tanques activos", 409);
        }

        // Verificar si tiene medidores activos
        $medidoresActivos = $instalacion->medidores()->where('activo', true)->count();
        if ($medidoresActivos > 0) {
            return $this->error("No se puede eliminar la instalación porque tiene {$medidoresActivos} medidores activos", 409);
        }

        try {
            DB::beginTransaction();

            $instalacion->activo = false;
            $instalacion->estatus = 'CANCELADA';
            $instalacion->save();
            $instalacion->delete();

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'ELIMINACION_INSTALACION',
                'Configuración',
                "Instalación eliminada: {$instalacion->clave_instalacion}",
                'instalaciones',
                $instalacion->id
            );

            DB::commit();

            return $this->success([], 'Instalación eliminada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al eliminar instalación: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener tanques de la instalación
     */
    public function tanques(Request $request, $id)
    {
        $instalacion = Instalacion::find($id);

        if (!$instalacion) {
            return $this->error('Instalación no encontrada', 404);
        }

        $query = $instalacion->tanques()->with('producto');

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('producto_id')) {
            $query->where('producto_id', $request->producto_id);
        }

        if ($request->boolean('activos')) {
            $query->where('activo', true);
        }

        $tanques = $query->orderBy('identificador')
            ->paginate($request->get('per_page', 15));

        return $this->success($tanques, 'Tanques obtenidos exitosamente');
    }

    /**
     * Obtener medidores de la instalación
     */
    public function medidores(Request $request, $id)
    {
        $instalacion = Instalacion::find($id);

        if (!$instalacion) {
            return $this->error('Instalación no encontrada', 404);
        }

        $query = $instalacion->medidores()->with('tanque');

        if ($request->has('tipo_medicion')) {
            $query->where('tipo_medicion', $request->tipo_medicion);
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->boolean('activos')) {
            $query->where('activo', true);
        }

        if ($request->boolean('calibracion_proxima')) {
            $query->where('fecha_proxima_calibracion', '<=', Carbon::now()->addDays(30));
        }

        $medidores = $query->orderBy('numero_serie')
            ->paginate($request->get('per_page', 15));

        return $this->success($medidores, 'Medidores obtenidos exitosamente');
    }

    /**
     * Obtener dispensarios de la instalación
     */
    public function dispensarios(Request $request, $id)
    {
        $instalacion = Instalacion::find($id);

        if (!$instalacion) {
            return $this->error('Instalación no encontrada', 404);
        }

        $query = $instalacion->dispensarios()->with('mangueras');

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->boolean('activos')) {
            $query->where('activo', true);
        }

        $dispensarios = $query->orderBy('clave')
            ->paginate($request->get('per_page', 15));

        return $this->success($dispensarios, 'Dispensarios obtenidos exitosamente');
    }

    /**
     * Obtener resumen operativo
     */
    public function resumenOperativo($id)
    {
        $instalacion = Instalacion::with(['contribuyente'])->find($id);

        if (!$instalacion) {
            return $this->error('Instalación no encontrada', 404);
        }

        $hoy = Carbon::today();

        // Tanques
        $tanques = $instalacion->tanques;
        $tanquesOperativos = $tanques->where('estado', 'OPERATIVO')->count();
        $capacidadTotal = $tanques->sum('capacidad_total');
        $capacidadOperativa = $tanques->sum('capacidad_operativa');

        // Medidores
        $medidores = $instalacion->medidores;
        $medidoresOperativos = $medidores->where('estado', 'OPERATIVO')->count();
        $medidoresCalibracionProxima = $medidores->filter(function ($m) {
            return $m->fecha_proxima_calibracion && 
                   Carbon::parse($m->fecha_proxima_calibracion)->lte(Carbon::now()->addDays(30));
        })->count();

        // Últimos registros volumétricos
        $ultimosRegistros = DB::table('registros_volumetricos')
            ->where('instalacion_id', $id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Alarmas activas
        $alarmasActivas = DB::table('alarmas')
            ->where('componente_tipo', 'instalacion')
            ->where('componente_id', $id)
            ->where('atendida', false)
            ->count();

        $resumen = [
            'instalacion' => [
                'id' => $instalacion->id,
                'clave' => $instalacion->clave_instalacion,
                'nombre' => $instalacion->nombre,
                'estatus' => $instalacion->estatus,
                'contribuyente' => $instalacion->contribuyente->razon_social,
            ],
            'tanques' => [
                'total' => $tanques->count(),
                'operativos' => $tanquesOperativos,
                'capacidad_total' => $capacidadTotal,
                'capacidad_operativa' => $capacidadOperativa,
            ],
            'medidores' => [
                'total' => $medidores->count(),
                'operativos' => $medidoresOperativos,
                'calibracion_proxima' => $medidoresCalibracionProxima,
            ],
            'dispensarios' => [
                'total' => $instalacion->dispensarios->count(),
                'operativos' => $instalacion->dispensarios->where('estado', 'OPERATIVO')->count(),
            ],
            'actividad_reciente' => [
                'ultimos_registros' => $ultimosRegistros->count(),
                'alarmas_activas' => $alarmasActivas,
            ],
            'fecha_consulta' => $hoy->toDateTimeString(),
        ];

        return $this->success($resumen, 'Resumen operativo obtenido exitosamente');
    }

    /**
     * Métodos privados
     */
    private function calcularEstadisticas($instalacion)
    {
        $hoy = Carbon::today();

        $tanques = $instalacion->tanques;
        $medidores = $instalacion->medidores;

        return [
            'tanques' => [
                'total' => $tanques->count(),
                'operativos' => $tanques->where('estado', 'OPERATIVO')->count(),
                'capacidad_total' => $tanques->sum('capacidad_total'),
            ],
            'medidores' => [
                'total' => $medidores->count(),
                'operativos' => $medidores->where('estado', 'OPERATIVO')->count(),
                'calibracion_proxima' => $medidores->filter(function ($m) use ($hoy) {
                    return $m->fecha_proxima_calibracion && 
                           Carbon::parse($m->fecha_proxima_calibracion)->lte($hoy->copy()->addDays(30));
                })->count(),
            ],
            'dispensarios' => [
                'total' => $instalacion->dispensarios->count(),
                'operativos' => $instalacion->dispensarios->where('estado', 'OPERATIVO')->count(),
            ],
        ];
    }

    /**
     * Buscar instalaciones para autocompletado
     */
    public function search(Request $request)
    {
        $search = $request->get('search', '');
        $contribuyenteId = $request->get('contribuyente_id');
        
        if (strlen($search) < 2 && !$contribuyenteId) {
            return $this->success([], 'Escriba al menos 2 caracteres o seleccione un contribuyente');
        }

        $query = Instalacion::where('activo', true);
        
        if ($contribuyenteId) {
            $query->where('contribuyente_id', $contribuyenteId);
        }
        
        if (strlen($search) >= 2) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'LIKE', "%{$search}%")
                  ->orWhere('clave_instalacion', 'LIKE', "%{$search}%");
            });
        }
        
        $instalaciones = $query
            ->select('id', 'clave_instalacion', 'nombre', 'domicilio', 'contribuyente_id')
            ->orderBy('nombre')
            ->limit(15)
            ->get();

        return $this->success($instalaciones, 'Resultados de búsqueda');
    }
}