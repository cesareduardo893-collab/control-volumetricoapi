<?php

namespace App\Http\Controllers;

use App\Models\Contribuyente;
use App\Models\User;
use App\Models\Instalacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ContribuyenteController extends BaseController
{
    /**
     * Listar contribuyentes
     */
    public function index(Request $request)
    {
        $query = Contribuyente::query();
        
        // Filtros
        if ($request->has('rfc')) {
            $query->where('rfc', 'LIKE', "%{$request->rfc}%");
        }
        
        if ($request->has('razon_social')) {
            $query->where('razon_social', 'LIKE', "%{$request->razon_social}%");
        }
        
        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }
        
        if ($request->has('caracter')) {
            $query->where('caracter_actua', $request->caracter);
        }
        
        if ($request->has('permiso')) {
            $query->where('numero_permiso', $request->permiso);
        }
        
        $contribuyentes = $query->with(['instalaciones' => function($q) {
            $q->where('estatus', 'OPERACION')->select('id', 'contribuyente_id', 'clave_instalacion', 'nombre');
        }])->paginate($request->get('per_page', 15));
        
        return $this->sendResponse($contribuyentes, 'Contribuyentes obtenidos exitosamente');
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
            'caracter_actua' => 'required|in:contratista,asignatario,permisionario,usuario',
            'numero_permiso' => 'nullable|string|max:255',
            'tipo_permiso' => 'nullable|string|max:255',
            'proveedor_equipos_rfc' => 'nullable|string|size:13',
            'proveedor_equipos_nombre' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();
            
            $contribuyente = Contribuyente::create($request->all());
            
            $this->logActivity(
                auth()->id(),
                'administracion_sistema',
                'creacion_contribuyente',
                'contribuyentes',
                "Contribuyente creado: {$contribuyente->rfc} - {$contribuyente->razon_social}",
                'contribuyentes',
                $contribuyente->id
            );
            
            DB::commit();
            
            return $this->sendResponse($contribuyente, 'Contribuyente creado exitosamente', 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al crear contribuyente', [$e->getMessage()], 500);
        }
    }

    /**
     * Mostrar contribuyente
     */
    public function show($id)
    {
        $contribuyente = Contribuyente::with([
            'instalaciones.tanques',
            'instalaciones.medidores',
            'dictamenes' => function($q) {
                $q->latest()->limit(10);
            },
            'certificados' => function($q) {
                $q->latest()->limit(5);
            },
            'pedimentos' => function($q) {
                $q->latest()->limit(20);
            }
        ])->find($id);
        
        if (!$contribuyente) {
            return $this->sendError('Contribuyente no encontrado');
        }
        
        return $this->sendResponse($contribuyente, 'Contribuyente obtenido exitosamente');
    }

    /**
     * Actualizar contribuyente
     */
    public function update(Request $request, $id)
    {
        $contribuyente = Contribuyente::find($id);
        
        if (!$contribuyente) {
            return $this->sendError('Contribuyente no encontrado');
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
            'caracter_actua' => 'sometimes|in:contratista,asignatario,permisionario,usuario',
            'numero_permiso' => 'nullable|string|max:255',
            'tipo_permiso' => 'nullable|string|max:255',
            'proveedor_equipos_rfc' => 'nullable|string|size:13',
            'proveedor_equipos_nombre' => 'nullable|string|max:255',
            'activo' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();
            
            $datosAnteriores = $contribuyente->toArray();
            $contribuyente->update($request->all());
            
            $this->logActivity(
                auth()->id(),
                'administracion_sistema',
                'actualizacion_contribuyente',
                'contribuyentes',
                "Contribuyente actualizado: {$contribuyente->rfc}",
                'contribuyentes',
                $contribuyente->id,
                $datosAnteriores,
                $contribuyente->toArray()
            );
            
            DB::commit();
            
            return $this->sendResponse($contribuyente, 'Contribuyente actualizado exitosamente');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al actualizar contribuyente', [$e->getMessage()], 500);
        }
    }

    /**
     * Eliminar contribuyente (soft delete)
     */
    public function destroy($id)
    {
        $contribuyente = Contribuyente::find($id);
        
        if (!$contribuyente) {
            return $this->sendError('Contribuyente no encontrado');
        }
        
        // Verificar si tiene instalaciones activas
        $instalacionesActivas = $contribuyente->instalaciones()
            ->where('estatus', 'OPERACION')
            ->count();
            
        if ($instalacionesActivas > 0) {
            return $this->sendError('No se puede eliminar el contribuyente porque tiene instalaciones activas', [], 409);
        }
        
        try {
            DB::beginTransaction();
            
            $contribuyente->activo = false;
            $contribuyente->save();
            $contribuyente->delete();
            
            $this->logActivity(
                auth()->id(),
                'administracion_sistema',
                'eliminacion_contribuyente',
                'contribuyentes',
                "Contribuyente eliminado: {$contribuyente->rfc}",
                'contribuyentes',
                $contribuyente->id
            );
            
            DB::commit();
            
            return $this->sendResponse([], 'Contribuyente eliminado exitosamente');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al eliminar contribuyente', [$e->getMessage()], 500);
        }
    }

    /**
     * Obtener instalaciones del contribuyente
     */
    public function instalaciones($id)
    {
        $contribuyente = Contribuyente::find($id);
        
        if (!$contribuyente) {
            return $this->sendError('Contribuyente no encontrado');
        }
        
        $instalaciones = $contribuyente->instalaciones()
            ->withCount(['tanques', 'medidores'])
            ->paginate(request()->get('per_page', 15));
            
        return $this->sendResponse($instalaciones, 'Instalaciones obtenidas exitosamente');
    }

    /**
     * Obtener resumen de cumplimiento
     */
    public function cumplimiento($id)
    {
        $contribuyente = Contribuyente::with([
            'certificados' => function($q) {
                $q->select('id', 'contribuyente_id', 'vigente', 'resultado');
            },
            'dictamenes' => function($q) {
                $q->select('id', 'contribuyente_id', 'fecha_emision');
            },
            'instalaciones' => function($q) {
                $q->select('id', 'contribuyente_id', 'estatus');
            },
            'instalaciones.medidores' => function($q) {
                $q->select('id', 'instalacion_id', 'fecha_proxima_calibracion');
            }
        ])->find($id);
        
        if (!$contribuyente) {
            return $this->sendError('Contribuyente no encontrado');
        }
        
        $cumplimiento = [
            'contribuyente' => [
                'rfc' => $contribuyente->rfc,
                'razon_social' => $contribuyente->razon_social
            ],
            'certificados' => [
                'total' => $contribuyente->certificados->count(),
                'vigente' => $contribuyente->certificados
                    ->where('vigente', true)
                    ->where('resultado', 'acreditado')
                    ->isNotEmpty()
            ],
            'dictamenes' => [
                'total' => $contribuyente->dictamenes->count(),
                'ultimo_mes' => $contribuyente->dictamenes
                    ->where('fecha_emision', '>=', now()->subMonth())
                    ->count()
            ],
            'instalaciones' => [
                'total' => $contribuyente->instalaciones->count(),
                'operativas' => $contribuyente->instalaciones
                    ->where('estatus', 'OPERACION')
                    ->count()
            ],
            'medidores' => [
                'total' => $contribuyente->instalaciones->sum(function($instalacion) {
                    return $instalacion->medidores->count();
                }),
                'calibracion_proxima' => $contribuyente->instalaciones->sum(function($instalacion) {
                    return $instalacion->medidores->where('fecha_proxima_calibracion', '<=', now()->addMonth())->count();
                })
            ]
        ];
        
        return $this->sendResponse($cumplimiento, 'Resumen de cumplimiento obtenido exitosamente');
    }
}