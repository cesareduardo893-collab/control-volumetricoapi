<?php

namespace App\Http\Controllers;

use App\Models\Manguera;
use App\Models\Dispensario;
use App\Models\Producto;
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
        $query = Manguera::with(['dispensario.instalacion', 'producto', 'medidor']);

        // Filtros
        if ($request->has('dispensario_id')) {
            $query->where('dispensario_id', $request->dispensario_id);
        }

        if ($request->has('instalacion_id')) {
            $query->whereHas('dispensario', function($q) use ($request) {
                $q->where('instalacion_id', $request->instalacion_id);
            });
        }

        if ($request->has('producto_id')) {
            $query->where('producto_id', $request->producto_id);
        }

        if ($request->has('medidor_id')) {
            $query->where('medidor_id', $request->medidor_id);
        }

        if ($request->has('lado')) {
            $query->where('lado', $request->lado);
        }

        if ($request->has('numero')) {
            $query->where('numero', $request->numero);
        }

        if ($request->has('estatus')) {
            $query->where('estatus', $request->estatus);
        }

        if ($request->boolean('activo')) {
            $query->where('activo', true);
        }

        if ($request->boolean('prueba_proxima')) {
            $query->where('fecha_proxima_prueba', '<=', now()->addDays(30))
                  ->whereNotNull('fecha_proxima_prueba');
        }

        $mangueras = $query->orderBy('dispensario_id')
            ->orderBy('numero')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($mangueras, 'Mangueras obtenidas exitosamente');
    }

    /**
     * Mostrar manguera
     */
    public function show($id)
    {
        $manguera = Manguera::with([
            'dispensario.instalacion',
            'producto',
            'medidor'
        ])->find($id);

        if (!$manguera) {
            return $this->sendError('Manguera no encontrada');
        }

        // Historial de ventas (simulado)
        $manguera->historial_ventas = $this->simularHistorialVentas($manguera);

        return $this->sendResponse($manguera, 'Manguera obtenida exitosamente');
    }

    /**
     * Actualizar manguera
     */
    public function update(Request $request, $id)
    {
        $manguera = Manguera::find($id);

        if (!$manguera) {
            return $this->sendError('Manguera no encontrada');
        }

        $validator = Validator::make($request->all(), [
            'producto_id' => 'sometimes|exists:productos,id',
            'medidor_id' => 'nullable|exists:medidores,id',
            'longitud' => 'nullable|numeric|min:0',
            'diametro' => 'nullable|numeric|min:0',
            'color' => 'nullable|string|max:50',
            'fecha_ultima_prueba' => 'nullable|date',
            'fecha_proxima_prueba' => 'nullable|date|after:fecha_ultima_prueba',
            'presion_trabajo' => 'nullable|numeric|min:0',
            'presion_prueba' => 'nullable|numeric|min:0',
            'observaciones' => 'nullable|string|max:500',
            'estatus' => 'sometimes|in:OPERACION,MANTENIMIENTO,FALLA,INACTIVO',
            'activo' => 'sometimes|boolean',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            // Validar que el medidor no esté asignado a otra manguera activa
            if ($request->has('medidor_id') && $request->medidor_id && $request->medidor_id != $manguera->medidor_id) {
                $medidorAsignado = Manguera::where('medidor_id', $request->medidor_id)
                    ->where('id', '!=', $id)
                    ->where('activo', true)
                    ->exists();
                
                if ($medidorAsignado) {
                    return $this->sendError('El medidor ya está asignado a otra manguera', [], 422);
                }
            }

            $datosAnteriores = $manguera->toArray();
            $manguera->update($request->all());

            $this->logActivity(
                auth()->id(),
                'configuracion',
                'actualizacion_manguera',
                'mangueras',
                "Manguera actualizada: Dispensario {$manguera->dispensario->codigo} - Manguera {$manguera->numero}",
                'mangueras',
                $manguera->id,
                $datosAnteriores,
                $manguera->toArray()
            );

            DB::commit();

            return $this->sendResponse($manguera, 'Manguera actualizada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al actualizar manguera', [$e->getMessage()], 500);
        }
    }

    /**
     * Eliminar manguera (soft delete)
     */
    public function destroy($id)
    {
        $manguera = Manguera::find($id);

        if (!$manguera) {
            return $this->sendError('Manguera no encontrada');
        }

        try {
            DB::beginTransaction();

            $manguera->activo = false;
            $manguera->estatus = 'INACTIVO';
            $manguera->save();
            $manguera->delete();

            $this->logActivity(
                auth()->id(),
                'configuracion',
                'eliminacion_manguera',
                'mangueras',
                "Manguera eliminada: Dispensario {$manguera->dispensario->codigo} - Manguera {$manguera->numero}",
                'mangueras',
                $manguera->id
            );

            DB::commit();

            return $this->sendResponse([], 'Manguera eliminada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al eliminar manguera', [$e->getMessage()], 500);
        }
    }

    /**
     * Cambiar producto
     */
    public function cambiarProducto(Request $request, $id)
    {
        $manguera = Manguera::find($id);

        if (!$manguera) {
            return $this->sendError('Manguera no encontrada');
        }

        $validator = Validator::make($request->all(), [
            'producto_id' => 'required|exists:productos,id',
            'motivo' => 'required|string|max:255',
            'fecha_cambio' => 'required|date',
            'requiere_purga' => 'boolean',
            'observaciones' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $productoAnterior = $manguera->producto;
            $datosAnteriores = $manguera->toArray();

            $manguera->producto_id = $request->producto_id;
            
            $metadata = $manguera->metadata ?? [];
            $metadata['cambios_producto'][] = [
                'fecha' => $request->fecha_cambio,
                'producto_anterior_id' => $productoAnterior ? $productoAnterior->id : null,
                'producto_anterior_nombre' => $productoAnterior ? $productoAnterior->nombre : null,
                'producto_nuevo_id' => $request->producto_id,
                'motivo' => $request->motivo,
                'requiere_purga' => $request->boolean('requiere_purga', true),
                'observaciones' => $request->observaciones,
                'usuario_id' => auth()->id(),
                'fecha_registro' => now()->toDateTimeString()
            ];
            $manguera->metadata = $metadata;
            
            $manguera->save();

            $this->logActivity(
                auth()->id(),
                'operacion',
                'cambio_producto_manguera',
                'mangueras',
                "Cambio de producto en manguera {$manguera->id}: " . 
                ($productoAnterior ? $productoAnterior->nombre : 'VACIO') . " -> " . 
                Producto::find($request->producto_id)->nombre,
                'mangueras',
                $manguera->id,
                $datosAnteriores,
                $manguera->toArray()
            );

            DB::commit();

            return $this->sendResponse($manguera->load('producto'), 
                'Producto de manguera cambiado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al cambiar producto de manguera', [$e->getMessage()], 500);
        }
    }

    /**
     * Registrar prueba de presión
     */
    public function registrarPrueba(Request $request, $id)
    {
        $manguera = Manguera::find($id);

        if (!$manguera) {
            return $this->sendError('Manguera no encontrada');
        }

        $validator = Validator::make($request->all(), [
            'fecha_prueba' => 'required|date',
            'presion_aplicada' => 'required|numeric|min:0',
            'presion_estable' => 'required|numeric|min:0',
            'tiempo_prueba' => 'required|integer|min:1',
            'unidad_tiempo' => 'required|in:MINUTOS,HORAS',
            'resultado' => 'required|in:APROBADA,RECHAZADA',
            'observaciones' => 'nullable|string|max:500',
            'tecnico' => 'required|string|max:255',
            'certificado' => 'nullable|string|max:100',
            'archivo_resultado' => 'nullable|file|mimes:pdf|max:5120'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            // Guardar archivo
            $rutaArchivo = null;
            if ($request->hasFile('archivo_resultado')) {
                $rutaArchivo = $request->file('archivo_resultado')
                    ->store("pruebas/mangueras/{$manguera->id}", 'public');
            }

            $datosAnteriores = $manguera->toArray();

            $prueba = [
                'fecha' => $request->fecha_prueba,
                'presion_aplicada' => $request->presion_aplicada,
                'presion_estable' => $request->presion_estable,
                'tiempo' => $request->tiempo_prueba,
                'unidad_tiempo' => $request->unidad_tiempo,
                'resultado' => $request->resultado,
                'tecnico' => $request->tecnico,
                'certificado' => $request->certificado,
                'archivo' => $rutaArchivo,
                'observaciones' => $request->observaciones
            ];

            $metadata = $manguera->metadata ?? [];
            $metadata['pruebas_presion'][] = $prueba;
            $manguera->metadata = $metadata;

            // Actualizar fechas de prueba
            $manguera->fecha_ultima_prueba = $request->fecha_prueba;
            $manguera->fecha_proxima_prueba = Carbon::parse($request->fecha_prueba)->addYear();
            
            $manguera->save();

            $this->logActivity(
                auth()->id(),
                'mantenimiento',
                'prueba_manguera',
                'mangueras',
                "Prueba de presión registrada para manguera {$manguera->id} - Resultado: {$request->resultado}",
                'mangueras',
                $manguera->id,
                $datosAnteriores,
                $manguera->toArray()
            );

            DB::commit();

            return $this->sendResponse($prueba, 'Prueba registrada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al registrar prueba', [$e->getMessage()], 500);
        }
    }

    /**
     * Asignar medidor
     */
    public function asignarMedidor(Request $request, $id)
    {
        $manguera = Manguera::find($id);

        if (!$manguera) {
            return $this->sendError('Manguera no encontrada');
        }

        $validator = Validator::make($request->all(), [
            'medidor_id' => 'required|exists:medidores,id'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            // Validar que el medidor no esté asignado a otra manguera activa
            $medidorAsignado = Manguera::where('medidor_id', $request->medidor_id)
                ->where('id', '!=', $id)
                ->where('activo', true)
                ->exists();
            
            if ($medidorAsignado) {
                return $this->sendError('El medidor ya está asignado a otra manguera', [], 422);
            }

            $datosAnteriores = $manguera->toArray();

            $medidorAnterior = $manguera->medidor;
            $manguera->medidor_id = $request->medidor_id;
            
            $metadata = $manguera->metadata ?? [];
            $metadata['asignaciones_medidor'][] = [
                'fecha' => now()->toDateTimeString(),
                'medidor_anterior_id' => $medidorAnterior ? $medidorAnterior->id : null,
                'medidor_anterior_serie' => $medidorAnterior ? $medidorAnterior->numero_serie : null,
                'medidor_nuevo_id' => $request->medidor_id,
                'usuario_id' => auth()->id()
            ];
            $manguera->metadata = $metadata;
            
            $manguera->save();

            $this->logActivity(
                auth()->id(),
                'configuracion',
                'asignacion_medidor_manguera',
                'mangueras',
                "Medidor asignado a manguera {$manguera->id}",
                'mangueras',
                $manguera->id,
                $datosAnteriores,
                $manguera->toArray()
            );

            DB::commit();

            return $this->sendResponse($manguera->load('medidor'), 
                'Medidor asignado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al asignar medidor', [$e->getMessage()], 500);
        }
    }

    /**
     * Obtener historial de ventas
     */
    public function historialVentas(Request $request, $id)
    {
        $manguera = Manguera::find($id);

        if (!$manguera) {
            return $this->sendError('Manguera no encontrada');
        }

        $validator = Validator::make($request->all(), [
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        // Simular historial de ventas
        $historial = $this->simularHistorialVentasPeriodo(
            $manguera,
            Carbon::parse($request->fecha_inicio),
            Carbon::parse($request->fecha_fin)
        );

        return $this->sendResponse($historial, 'Historial de ventas obtenido exitosamente');
    }

    /**
     * Métodos privados
     */
    private function simularHistorialVentas($manguera)
    {
        $hoy = Carbon::today();
        $ventas = [];

        for ($i = 30; $i >= 0; $i--) {
            $fecha = $hoy->copy()->subDays($i);
            $ventas[] = [
                'fecha' => $fecha->format('Y-m-d'),
                'volumen' => rand(100, 1000) / 10,
                'importe' => rand(2000, 20000) / 10,
                'transacciones' => rand(5, 50)
            ];
        }

        $totalVolumen = collect($ventas)->sum('volumen');
        $totalImporte = collect($ventas)->sum('importe');

        return [
            'diario' => $ventas,
            'resumen' => [
                'total_volumen_30d' => $totalVolumen,
                'total_importe_30d' => $totalImporte,
                'promedio_diario_volumen' => $totalVolumen / 30,
                'promedio_diario_importe' => $totalImporte / 30
            ]
        ];
    }

    private function simularHistorialVentasPeriodo($manguera, $inicio, $fin)
    {
        $dias = $inicio->diffInDays($fin);
        $ventas = [];

        for ($i = 0; $i <= $dias; $i++) {
            $fecha = $inicio->copy()->addDays($i);
            $ventas[] = [
                'fecha' => $fecha->format('Y-m-d'),
                'volumen' => rand(50, 800) / 10,
                'importe' => rand(1000, 16000) / 10,
                'transacciones' => rand(3, 40)
            ];
        }

        $totalVolumen = collect($ventas)->sum('volumen');
        $totalImporte = collect($ventas)->sum('importe');

        return [
            'periodo' => [
                'inicio' => $inicio->format('Y-m-d'),
                'fin' => $fin->format('Y-m-d'),
                'dias' => $dias + 1
            ],
            'ventas' => $ventas,
            'resumen' => [
                'total_volumen' => $totalVolumen,
                'total_importe' => $totalImporte,
                'promedio_diario_volumen' => $totalVolumen / ($dias + 1),
                'promedio_diario_importe' => $totalImporte / ($dias + 1),
                'volumen_maximo' => collect($ventas)->max('volumen'),
                'volumen_minimo' => collect($ventas)->min('volumen')
            ]
        ];
    }
}