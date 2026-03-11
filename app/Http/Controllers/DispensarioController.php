<?php

namespace App\Http\Controllers;

use App\Models\Dispensario;
use App\Models\Instalacion;
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
        $query = Dispensario::with(['instalacion', 'mangueras']);

        // Filtros
        if ($request->has('instalacion_id')) {
            $query->where('instalacion_id', $request->instalacion_id);
        }

        if ($request->has('clave')) {
            $query->where('clave', 'LIKE', "%{$request->clave}%");
        }

        if ($request->has('modelo')) {
            $query->where('modelo', 'LIKE', "%{$request->modelo}%");
        }

        if ($request->has('fabricante')) {
            $query->where('fabricante', 'LIKE', "%{$request->fabricante}%");
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        $dispensarios = $query->orderBy('instalacion_id')
            ->orderBy('clave')
            ->paginate($request->get('per_page', 15));

        return $this->success($dispensarios, 'Dispensarios obtenidos exitosamente');
    }

    /**
     * Crear dispensario
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'instalacion_id' => 'required|exists:instalaciones,id',
            'clave' => 'required|string|max:255|unique:dispensarios,clave',
            'descripcion' => 'nullable|string',
            'modelo' => 'nullable|string|max:255',
            'fabricante' => 'nullable|string|max:255',
            'estado' => 'required|in:OPERATIVO,MANTENIMIENTO,FUERA_SERVICIO',
            'activo' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            // Validar instalación activa
            $instalacion = Instalacion::find($request->instalacion_id);
            if (!$instalacion || !$instalacion->activo) {
                return $this->error('La instalación no está activa', 422);
            }

            $dispensario = Dispensario::create([
                'instalacion_id' => $request->instalacion_id,
                'clave' => $request->clave,
                'descripcion' => $request->descripcion,
                'modelo' => $request->modelo,
                'fabricante' => $request->fabricante,
                'estado' => $request->estado,
                'activo' => $request->boolean('activo', true),
            ]);

            $this->logActivity(
                auth()->id(),
                'configuracion',
                'CREACION_DISPENSARIO',
                'Configuración',
                "Dispensario creado: {$dispensario->clave}",
                'dispensarios',
                $dispensario->id
            );

            DB::commit();

            return $this->success($dispensario->load('instalacion'), 'Dispensario creado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al crear dispensario: ' . $e->getMessage(), 500);
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
                $q->with('medidor')->orderBy('clave');
            }
        ])->find($id);

        if (!$dispensario) {
            return $this->error('Dispensario no encontrado', 404);
        }

        return $this->success($dispensario, 'Dispensario obtenido exitosamente');
    }

    /**
     * Actualizar dispensario
     */
    public function update(Request $request, $id)
    {
        $dispensario = Dispensario::find($id);

        if (!$dispensario) {
            return $this->error('Dispensario no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'clave' => "sometimes|string|max:255|unique:dispensarios,clave,{$id}",
            'descripcion' => 'nullable|string',
            'modelo' => 'nullable|string|max:255',
            'fabricante' => 'nullable|string|max:255',
            'estado' => 'sometimes|in:OPERATIVO,MANTENIMIENTO,FUERA_SERVICIO',
            'activo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $dispensario->toArray();
            $dispensario->update($request->all());

            $this->logActivity(
                auth()->id(),
                'configuracion',
                'ACTUALIZACION_DISPENSARIO',
                'Configuración',
                "Dispensario actualizado: {$dispensario->clave}",
                'dispensarios',
                $dispensario->id,
                $datosAnteriores,
                $dispensario->toArray()
            );

            DB::commit();

            return $this->success($dispensario, 'Dispensario actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al actualizar dispensario: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar dispensario (soft delete)
     */
    public function destroy($id)
    {
        $dispensario = Dispensario::find($id);

        if (!$dispensario) {
            return $this->error('Dispensario no encontrado', 404);
        }

        // Verificar si tiene mangueras activas
        $manguerasActivas = $dispensario->mangueras()->where('activo', true)->count();
        if ($manguerasActivas > 0) {
            return $this->error("No se puede eliminar el dispensario porque tiene {$manguerasActivas} mangueras activas", 409);
        }

        try {
            DB::beginTransaction();

            $dispensario->activo = false;
            $dispensario->estado = 'FUERA_SERVICIO';
            $dispensario->save();
            $dispensario->delete();

            $this->logActivity(
                auth()->id(),
                'configuracion',
                'ELIMINACION_DISPENSARIO',
                'Configuración',
                "Dispensario eliminado: {$dispensario->clave}",
                'dispensarios',
                $dispensario->id
            );

            DB::commit();

            return $this->success([], 'Dispensario eliminado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al eliminar dispensario: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener mangueras del dispensario
     */
    public function mangueras(Request $request, $id)
    {
        $dispensario = Dispensario::find($id);

        if (!$dispensario) {
            return $this->error('Dispensario no encontrado', 404);
        }

        $query = $dispensario->mangueras()->with('medidor');

        if ($request->has('medidor_id')) {
            $query->where('medidor_id', $request->medidor_id);
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->boolean('activas')) {
            $query->where('activo', true);
        }

        $mangueras = $query->orderBy('clave')
            ->paginate($request->get('per_page', 15));

        return $this->success($mangueras, 'Mangueras obtenidas exitosamente');
    }

    /**
     * Verificar estado del dispensario
     */
    public function verificarEstado($id)
    {
        $dispensario = Dispensario::with('mangueras')->find($id);

        if (!$dispensario) {
            return $this->error('Dispensario no encontrado', 404);
        }

        $alertas = [];

        // Verificar mangueras
        $manguerasOperativas = $dispensario->mangueras->where('estado', 'OPERATIVO')->count();
        $manguerasFalla = $dispensario->mangueras->where('estado', 'FUERA_SERVICIO')->count();

        if ($manguerasFalla > 0) {
            $alertas[] = [
                'tipo' => 'MANGUERAS_FUERA_SERVICIO',
                'severidad' => 'MEDIA',
                'mensaje' => "{$manguerasFalla} manguera(s) fuera de servicio",
            ];
        }

        $estado = [
            'dispensario_id' => $dispensario->id,
            'clave' => $dispensario->clave,
            'estado' => $dispensario->estado,
            'activo' => $dispensario->activo,
            'mangueras' => [
                'total' => $dispensario->mangueras->count(),
                'operativas' => $manguerasOperativas,
                'en_falla' => $manguerasFalla,
            ],
            'alertas' => $alertas,
            'fecha_verificacion' => Carbon::now()->toDateTimeString(),
        ];

        return $this->success($estado, 'Estado del dispensario verificado exitosamente');
    }
}