<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Contribuyente;
use App\Models\User;
use App\Models\Instalacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ContribuyenteController extends BaseController
{
    /**
     * Listar contribuyentes
     */
    public function index(Request $request)
    {
        $query = Contribuyente::with(['instalaciones' => function($q) {
            $q->where('estatus', 'OPERACION');
        }]);

        // Filtros
        if ($request->has('rfc')) {
            $query->where('rfc', 'LIKE', "%{$request->rfc}%");
        }

        if ($request->has('razon_social')) {
            $query->where('razon_social', 'LIKE', "%{$request->razon_social}%");
        }

        if ($request->has('regimen_fiscal')) {
            $query->where('regimen_fiscal', 'LIKE', "%{$request->regimen_fiscal}%");
        }

        if ($request->has('numero_permiso')) {
            $query->where('numero_permiso', 'LIKE', "%{$request->numero_permiso}%");
        }

        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        if ($request->has('proxima_verificacion')) {
            $query->where('proxima_verificacion', '<=', Carbon::parse($request->proxima_verificacion));
        }

        $contribuyentes = $query->orderBy('razon_social')
            ->paginate($request->get('per_page', 15));

        return $this->success($contribuyentes, 'Contribuyentes obtenidos exitosamente');
    }

    /**
     * Crear contribuyente
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rfc' => 'required|string|size:13|unique:contribuyentes,rfc',
            'razon_social' => 'required|string|max:255',
            'nombre_comercial' => 'nullable|string|max:255',
            'regimen_fiscal' => 'required|string|max:255',
            'domicilio_fiscal' => 'required|string|max:255',
            'codigo_postal' => 'required|string|size:5',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'representante_legal' => 'nullable|string|max:255',
            'representante_rfc' => 'nullable|string|size:13',
            'caracter_actua_id' => 'nullable|exists:catalogo_valores,id',
            'numero_permiso' => 'nullable|string|max:255',
            'tipo_permiso' => 'nullable|string|max:255',
            'proveedor_equipos_rfc' => 'nullable|string|size:13',
            'proveedor_equipos_nombre' => 'nullable|string|max:255',
            'certificados_vigentes' => 'nullable|array',
            'ultima_verificacion' => 'nullable|date',
            'proxima_verificacion' => 'nullable|date|after:ultima_verificacion',
            'estatus_verificacion' => 'nullable|string|max:50',
            'activo' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $contribuyente = Contribuyente::create([
                'rfc' => $request->rfc,
                'razon_social' => $request->razon_social,
                'nombre_comercial' => $request->nombre_comercial,
                'regimen_fiscal' => $request->regimen_fiscal,
                'domicilio_fiscal' => $request->domicilio_fiscal,
                'codigo_postal' => $request->codigo_postal,
                'telefono' => $request->telefono,
                'email' => $request->email,
                'representante_legal' => $request->representante_legal,
                'representante_rfc' => $request->representante_rfc,
                'caracter_actua_id' => $request->caracter_actua_id,
                'numero_permiso' => $request->numero_permiso,
                'tipo_permiso' => $request->tipo_permiso,
                'proveedor_equipos_rfc' => $request->proveedor_equipos_rfc,
                'proveedor_equipos_nombre' => $request->proveedor_equipos_nombre,
                'certificados_vigentes' => $request->certificados_vigentes,
                'ultima_verificacion' => $request->ultima_verificacion,
                'proxima_verificacion' => $request->proxima_verificacion,
                'estatus_verificacion' => $request->estatus_verificacion,
                'activo' => $request->boolean('activo', true),
                'fecha_registro' => now(),
            ]);

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'CREACION_CONTRIBUYENTE',
                'Contribuyentes',
                "Contribuyente creado: {$contribuyente->rfc} - {$contribuyente->razon_social}",
                'contribuyentes',
                $contribuyente->id
            );

            DB::commit();

            return $this->success($contribuyente, 'Contribuyente creado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al crear contribuyente: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mostrar contribuyente
     */
    public function show($id)
    {
        $contribuyente = Contribuyente::with([
            'instalaciones' => function($q) {
                $q->with(['tanques', 'medidores', 'dispensarios']);
            },
            'dictamenes' => function($q) {
                $q->latest('fecha_emision')->limit(10);
            },
            'certificadosVerificacion' => function($q) {
                $q->latest('fecha_emision')->limit(5);
            },
            'pedimentos' => function($q) {
                $q->latest('fecha_pedimento')->limit(20);
            }
        ])->find($id);

        if (!$contribuyente) {
            return $this->error('Contribuyente no encontrado', 404);
        }

        return $this->success($contribuyente, 'Contribuyente obtenido exitosamente');
    }

    /**
     * Actualizar contribuyente
     */
    public function update(Request $request, $id)
    {
        $contribuyente = Contribuyente::find($id);

        if (!$contribuyente) {
            return $this->error('Contribuyente no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'rfc' => "sometimes|string|size:13|unique:contribuyentes,rfc,{$id}",
            'razon_social' => 'sometimes|string|max:255',
            'nombre_comercial' => 'nullable|string|max:255',
            'regimen_fiscal' => 'sometimes|string|max:255',
            'domicilio_fiscal' => 'sometimes|string|max:255',
            'codigo_postal' => 'sometimes|string|size:5',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'representante_legal' => 'nullable|string|max:255',
            'representante_rfc' => 'nullable|string|size:13',
            'caracter_actua_id' => 'nullable|exists:catalogo_valores,id',
            'numero_permiso' => 'nullable|string|max:255',
            'tipo_permiso' => 'nullable|string|max:255',
            'proveedor_equipos_rfc' => 'nullable|string|size:13',
            'proveedor_equipos_nombre' => 'nullable|string|max:255',
            'certificados_vigentes' => 'nullable|array',
            'ultima_verificacion' => 'nullable|date',
            'proxima_verificacion' => 'nullable|date|after:ultima_verificacion',
            'estatus_verificacion' => 'nullable|string|max:50',
            'activo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $contribuyente->toArray();
            $contribuyente->update($request->all());

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'ACTUALIZACION_CONTRIBUYENTE',
                'Contribuyentes',
                "Contribuyente actualizado: {$contribuyente->rfc}",
                'contribuyentes',
                $contribuyente->id,
                $datosAnteriores,
                $contribuyente->toArray()
            );

            DB::commit();

            return $this->success($contribuyente, 'Contribuyente actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al actualizar contribuyente: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar contribuyente (soft delete)
     */
    public function destroy($id)
    {
        $contribuyente = Contribuyente::find($id);

        if (!$contribuyente) {
            return $this->error('Contribuyente no encontrado', 404);
        }

        // Verificar si tiene instalaciones activas
        $instalacionesActivas = $contribuyente->instalaciones()
            ->where('estatus', 'OPERACION')
            ->count();

        if ($instalacionesActivas > 0) {
            return $this->error("No se puede eliminar el contribuyente porque tiene {$instalacionesActivas} instalaciones activas", 409);
        }

        try {
            DB::beginTransaction();

            $contribuyente->activo = false;
            $contribuyente->save();
            $contribuyente->delete();

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'ELIMINACION_CONTRIBUYENTE',
                'Contribuyentes',
                "Contribuyente eliminado: {$contribuyente->rfc}",
                'contribuyentes',
                $contribuyente->id
            );

            DB::commit();

            return $this->success([], 'Contribuyente eliminado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al eliminar contribuyente: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener instalaciones del contribuyente
     */
    public function instalaciones(Request $request, $id)
    {
        $contribuyente = Contribuyente::find($id);

        if (!$contribuyente) {
            return $this->error('Contribuyente no encontrado', 404);
        }

        $query = $contribuyente->instalaciones();

        if ($request->has('estatus')) {
            $query->where('estatus', $request->estatus);
        }

        if ($request->has('tipo')) {
            $query->where('tipo_instalacion', $request->tipo);
        }

        $instalaciones = $query->withCount(['tanques', 'medidores'])
            ->paginate($request->get('per_page', 15));

        return $this->success($instalaciones, 'Instalaciones obtenidas exitosamente');
    }

    /**
     * Obtener resumen de cumplimiento
     */
    public function cumplimiento($id)
    {
        $contribuyente = Contribuyente::find($id);

        if (!$contribuyente) {
            return $this->error('Contribuyente no encontrado', 404);
        }

        $hoy = Carbon::now();

        // Certificados de verificación vigentes
        $certificadosVigentes = $contribuyente->certificadosVerificacion()
            ->where('vigente', true)
            ->where('fecha_caducidad', '>=', $hoy)
            ->count();

        $certificadosVencidos = $contribuyente->certificadosVerificacion()
            ->where(function($q) use ($hoy) {
                $q->where('vigente', false)
                  ->orWhere('fecha_caducidad', '<', $hoy);
            })
            ->count();

        // Instalaciones
        $instalaciones = $contribuyente->instalaciones;
        $instalacionesOperativas = $instalaciones->where('estatus', 'OPERACION')->count();

        // Tanques
        $tanques = collect();
        $medidores = collect();
        
        foreach ($instalaciones as $inst) {
            foreach ($inst->tanques as $tanque) {
                $tanques->push($tanque);
            }
            foreach ($inst->medidores as $medidor) {
                $medidores->push($medidor);
            }
        }

        $tanquesOperativos = $tanques->where('estado', 'OPERATIVO')->count();
        $medidoresOperativos = $medidores->where('estado', 'OPERATIVO')->count();

        // Próximas calibraciones
        $proximasCalibraciones = $medidores->filter(function ($m) use ($hoy) {
            return $m->fecha_proxima_calibracion && 
                   Carbon::parse($m->fecha_proxima_calibracion)->lte($hoy->copy()->addDays(30));
        })->count();

        $cumplimiento = [
            'contribuyente' => [
                'id' => $contribuyente->id,
                'rfc' => $contribuyente->rfc,
                'razon_social' => $contribuyente->razon_social,
            ],
            'certificados' => [
                'vigentes' => $certificadosVigentes,
                'vencidos' => $certificadosVencidos,
                'total' => $certificadosVigentes + $certificadosVencidos,
            ],
            'instalaciones' => [
                'total' => $instalaciones->count(),
                'operativas' => $instalacionesOperativas,
                'porcentaje_operatividad' => $instalaciones->count() > 0 
                    ? round(($instalacionesOperativas / $instalaciones->count()) * 100, 2)
                    : 0,
            ],
            'tanques' => [
                'total' => $tanques->count(),
                'operativos' => $tanquesOperativos,
            ],
            'medidores' => [
                'total' => $medidores->count(),
                'operativos' => $medidoresOperativos,
                'proximas_calibraciones' => $proximasCalibraciones,
            ],
            'fecha_consulta' => $hoy->toDateTimeString(),
        ];

        return $this->success($cumplimiento, 'Resumen de cumplimiento obtenido exitosamente');
    }

    /**
     * Obtener catálogo para dropdowns
     */
    public function catalogo()
    {
        $contribuyentes = Contribuyente::where('activo', true)
            ->select('id', 'rfc', 'razon_social', 'nombre_comercial')
            ->orderBy('razon_social')
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'rfc' => $c->rfc,
                    'nombre' => $c->razon_social,
                    'nombre_comercial' => $c->nombre_comercial,
                ];
            });

        return $this->success($contribuyentes, 'Catálogo de contribuyentes obtenido exitosamente');
    }
}