<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Manguera;
use App\Models\Dispensario;
use App\Models\Medidor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MangueraController extends BaseController
{
    /**
     * Listar mangueras
     */
    public function index(Request $request)
    {
        $query = Manguera::with(['dispensario.instalacion', 'medidor']);

        // Filtros
        if ($request->has('dispensario_id')) {
            $query->where('dispensario_id', $request->dispensario_id);
        }

        if ($request->has('medidor_id')) {
            $query->where('medidor_id', $request->medidor_id);
        }

        if ($request->has('clave')) {
            $query->where('clave', 'LIKE', "%{$request->clave}%");
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->boolean('activo')) {
            $query->where('activo', true);
        }

        $mangueras = $query->orderBy('dispensario_id')
            ->orderBy('clave')
            ->paginate($request->get('per_page', 15));

        return $this->success($mangueras, 'Mangueras obtenidas exitosamente');
    }

    /**
     * Crear manguera
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'dispensario_id' => 'required|exists:dispensarios,id',
            'clave' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'medidor_id' => 'nullable|exists:medidores,id',
            'estado' => 'required|in:OPERATIVO,MANTENIMIENTO,FUERA_SERVICIO',
            'activo' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            // Validar que el medidor no esté asignado a otra manguera activa
            if ($request->has('medidor_id') && $request->medidor_id) {
                $medidorAsignado = Manguera::where('medidor_id', $request->medidor_id)
                    ->where('activo', true)
                    ->exists();
                
                if ($medidorAsignado) {
                    return $this->error('El medidor ya está asignado a otra manguera', 422);
                }
            }

            $manguera = Manguera::create([
                'dispensario_id' => $request->dispensario_id,
                'clave' => $request->clave,
                'descripcion' => $request->descripcion,
                'medidor_id' => $request->medidor_id,
                'estado' => $request->estado,
                'activo' => $request->boolean('activo', true),
            ]);

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'CREACION_MANGUERA',
                'Configuración',
                "Manguera creada: {$manguera->clave}",
                'mangueras',
                $manguera->id
            );

            DB::commit();

            return $this->success($manguera, 'Manguera creada exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al crear manguera: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mostrar manguera
     */
    public function show($id)
    {
        $manguera = Manguera::with([
            'dispensario.instalacion',
            'medidor'
        ])->find($id);

        if (!$manguera) {
            return $this->error('Manguera no encontrada', 404);
        }

        return $this->success($manguera, 'Manguera obtenida exitosamente');
    }

    /**
     * Actualizar manguera
     */
    public function update(Request $request, $id)
    {
        $manguera = Manguera::find($id);

        if (!$manguera) {
            return $this->error('Manguera no encontrada', 404);
        }

        $validator = Validator::make($request->all(), [
            'descripcion' => 'nullable|string',
            'medidor_id' => 'nullable|exists:medidores,id',
            'estado' => 'sometimes|in:OPERATIVO,MANTENIMIENTO,FUERA_SERVICIO',
            'activo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            // Validar que el medidor no esté asignado a otra manguera activa
            if ($request->has('medidor_id') && $request->medidor_id) {
                $medidorAsignado = Manguera::where('medidor_id', $request->medidor_id)
                    ->where('id', '!=', $id)
                    ->where('activo', true)
                    ->exists();
                
                if ($medidorAsignado) {
                    return $this->error('El medidor ya está asignado a otra manguera', 422);
                }
            }

            $datosAnteriores = $manguera->toArray();
            $manguera->update($request->all());

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'ACTUALIZACION_MANGUERA',
                'Configuración',
                "Manguera actualizada: {$manguera->clave}",
                'mangueras',
                $manguera->id,
                $datosAnteriores,
                $manguera->toArray()
            );

            DB::commit();

            return $this->success($manguera, 'Manguera actualizada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al actualizar manguera: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar manguera (soft delete)
     */
    public function destroy($id)
    {
        $manguera = Manguera::find($id);

        if (!$manguera) {
            return $this->error('Manguera no encontrada', 404);
        }

        try {
            DB::beginTransaction();

            $manguera->activo = false;
            $manguera->estado = 'FUERA_SERVICIO';
            $manguera->save();
            $manguera->delete();

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'ELIMINACION_MANGUERA',
                'Configuración',
                "Manguera eliminada: {$manguera->clave}",
                'mangueras',
                $manguera->id
            );

            DB::commit();

            return $this->success([], 'Manguera eliminada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al eliminar manguera: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Asignar medidor
     */
    public function asignarMedidor(Request $request, $id)
    {
        $manguera = Manguera::find($id);

        if (!$manguera) {
            return $this->error('Manguera no encontrada', 404);
        }

        $validator = Validator::make($request->all(), [
            'medidor_id' => 'required|exists:medidores,id',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            // Validar que el medidor no esté asignado a otra manguera activa
            $medidorAsignado = Manguera::where('medidor_id', $request->medidor_id)
                ->where('id', '!=', $id)
                ->where('activo', true)
                ->exists();
            
            if ($medidorAsignado) {
                return $this->error('El medidor ya está asignado a otra manguera', 422);
            }

            $datosAnteriores = $manguera->toArray();
            $manguera->medidor_id = $request->medidor_id;
            $manguera->save();

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'ASIGNACION_MEDIDOR_MANGUERA',
                'Configuración',
                "Medidor asignado a manguera {$manguera->clave}",
                'mangueras',
                $manguera->id,
                $datosAnteriores,
                $manguera->toArray()
            );

            DB::commit();

            return $this->success($manguera->load('medidor'), 'Medidor asignado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al asignar medidor: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Quitar medidor
     */
    public function quitarMedidor($id)
    {
        $manguera = Manguera::find($id);

        if (!$manguera) {
            return $this->error('Manguera no encontrada', 404);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $manguera->toArray();
            $manguera->medidor_id = null;
            $manguera->save();

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'QUITAR_MEDIDOR_MANGUERA',
                'Configuración',
                "Medidor quitado de manguera {$manguera->clave}",
                'mangueras',
                $manguera->id,
                $datosAnteriores,
                $manguera->toArray()
            );

            DB::commit();

            return $this->success($manguera, 'Medidor quitado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al quitar medidor: ' . $e->getMessage(), 500);
        }
    }
}