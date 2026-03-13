<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
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
        if ($request->has('clave_sat')) {
            $query->where('clave_sat', 'LIKE', "%{$request->clave_sat}%");
        }

        if ($request->has('codigo')) {
            $query->where('codigo', 'LIKE', "%{$request->codigo}%");
        }

        if ($request->has('nombre')) {
            $query->where('nombre', 'LIKE', "%{$request->nombre}%");
        }

        if ($request->has('tipo_hidrocarburo')) {
            $query->where('tipo_hidrocarburo', $request->tipo_hidrocarburo);
        }

        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        $productos = $query->orderBy('tipo_hidrocarburo')
            ->orderBy('nombre')
            ->paginate($request->get('per_page', 15));

        return $this->success($productos, 'Productos obtenidos exitosamente');
    }

    /**
     * Crear producto
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'clave_sat' => 'required|string|size:10|unique:productos,clave_sat',
            'codigo' => 'required|string|max:20|unique:productos,codigo',
            'clave_identificacion' => 'required|string|size:10|unique:productos,clave_identificacion',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'unidad_medida' => 'required|string|max:50',
            'tipo_hidrocarburo' => 'required|in:petroleo,gas_natural,condensados,gasolina,diesel,turbosina,gas_lp,propano,otro',
            'densidad_api' => 'nullable|numeric|min:0|max:100',
            'contenido_azufre' => 'nullable|numeric|min:0|max:100',
            'clasificacion_azufre' => 'nullable|string|max:255',
            'clasificacion_api' => 'nullable|string|max:255',
            'poder_calorifico' => 'nullable|numeric|min:0',
            'composicion_tipica' => 'nullable|array',
            'especificaciones_tecnicas' => 'nullable|array',
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
            'indice_wobbe' => 'nullable|numeric|min:0',
            'clasificacion_gas' => 'nullable|string|max:255',
            'color_identificacion' => 'nullable|string|size:7',
            'activo' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $producto = Producto::create([
                'clave_sat' => $request->clave_sat,
                'codigo' => $request->codigo,
                'clave_identificacion' => $request->clave_identificacion,
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'unidad_medida' => $request->unidad_medida,
                'tipo_hidrocarburo' => $request->tipo_hidrocarburo,
                'densidad_api' => $request->densidad_api,
                'contenido_azufre' => $request->contenido_azufre,
                'clasificacion_azufre' => $request->clasificacion_azufre,
                'clasificacion_api' => $request->clasificacion_api,
                'poder_calorifico' => $request->poder_calorifico,
                'composicion_tipica' => $request->composicion_tipica,
                'especificaciones_tecnicas' => $request->especificaciones_tecnicas,
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
                'indice_wobbe' => $request->indice_wobbe,
                'clasificacion_gas' => $request->clasificacion_gas,
                'color_identificacion' => $request->color_identificacion,
                'activo' => $request->boolean('activo', true),
            ]);

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'CREACION_PRODUCTO',
                'Catálogos',
                "Producto creado: {$producto->clave_sat} - {$producto->nombre}",
                'productos',
                $producto->id
            );

            DB::commit();

            return $this->success($producto, 'Producto creado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al crear producto: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mostrar producto
     */
    public function show($id)
    {
        $producto = Producto::with(['tanques' => function($q) {
            $q->where('activo', true)->limit(10);
        }])->find($id);

        if (!$producto) {
            return $this->error('Producto no encontrado', 404);
        }

        return $this->success($producto, 'Producto obtenido exitosamente');
    }

    /**
     * Actualizar producto
     */
    public function update(Request $request, $id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return $this->error('Producto no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'clave_sat' => "sometimes|string|size:10|unique:productos,clave_sat,{$id}",
            'codigo' => "sometimes|string|max:20|unique:productos,codigo,{$id}",
            'clave_identificacion' => "sometimes|string|size:10|unique:productos,clave_identificacion,{$id}",
            'nombre' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string',
            'unidad_medida' => 'sometimes|string|max:50',
            'tipo_hidrocarburo' => 'sometimes|in:petroleo,gas_natural,condensados,gasolina,diesel,turbosina,gas_lp,propano,otro',
            'densidad_api' => 'nullable|numeric|min:0|max:100',
            'contenido_azufre' => 'nullable|numeric|min:0|max:100',
            'clasificacion_azufre' => 'nullable|string|max:255',
            'clasificacion_api' => 'nullable|string|max:255',
            'poder_calorifico' => 'nullable|numeric|min:0',
            'composicion_tipica' => 'nullable|array',
            'especificaciones_tecnicas' => 'nullable|array',
            'octanaje_ron' => 'nullable|numeric|min:0|max:120',
            'octanaje_mon' => 'nullable|numeric|min:0|max:120',
            'indice_octano' => 'nullable|numeric|min:0|max:120',
            'contiene_bioetanol' => 'boolean',
            'porcentaje_bioetanol' => 'nullable|numeric|min:0|max:100',
            'contiene_biodiesel' => 'boolean',
            'porcentaje_biodiesel' => 'nullable|numeric|min:0|max:100',
            'contiene_bioturbosina' => 'boolean',
            'porcentaje_bioturbosina' => 'nullable|numeric|min:0|max:100',
            'activo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $producto->toArray();
            $producto->update($request->all());

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'ACTUALIZACION_PRODUCTO',
                'Catálogos',
                "Producto actualizado: {$producto->clave_sat}",
                'productos',
                $producto->id,
                $datosAnteriores,
                $producto->toArray()
            );

            DB::commit();

            return $this->success($producto, 'Producto actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al actualizar producto: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar producto (soft delete)
     */
    public function destroy($id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return $this->error('Producto no encontrado', 404);
        }

        // Verificar si tiene tanques asociados
        $tanquesAsociados = $producto->tanques()->where('activo', true)->count();
        if ($tanquesAsociados > 0) {
            return $this->error("No se puede eliminar el producto porque tiene {$tanquesAsociados} tanques asociados", 409);
        }

        try {
            DB::beginTransaction();

            $producto->activo = false;
            $producto->save();
            $producto->delete();

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'ELIMINACION_PRODUCTO',
                'Catálogos',
                "Producto eliminado: {$producto->clave_sat}",
                'productos',
                $producto->id
            );

            DB::commit();

            return $this->success([], 'Producto eliminado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al eliminar producto: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener productos por tipo
     */
    public function porTipo($tipo)
    {
        $tiposValidos = ['petroleo', 'gas_natural', 'condensados', 'gasolina', 'diesel', 'turbosina', 'gas_lp', 'propano', 'otro'];
        
        if (!in_array($tipo, $tiposValidos)) {
            return $this->error('Tipo de producto no válido', 422);
        }

        $productos = Producto::where('tipo_hidrocarburo', $tipo)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        return $this->success($productos, "Productos tipo {$tipo} obtenidos exitosamente");
    }

    /**
     * Obtener catálogo para dropdowns
     */
    public function catalogo()
    {
        $productos = Producto::where('activo', true)
            ->select('id', 'clave_sat', 'codigo', 'nombre', 'tipo_hidrocarburo', 'unidad_medida')
            ->orderBy('tipo_hidrocarburo')
            ->orderBy('nombre')
            ->get()
            ->groupBy('tipo_hidrocarburo');

        return $this->success($productos, 'Catálogo de productos obtenido exitosamente');
    }

    /**
     * Buscar por clave SAT
     */
    public function buscarPorClaveSat($claveSat)
    {
        $producto = Producto::where('clave_sat', $claveSat)
            ->where('activo', true)
            ->first();

        if (!$producto) {
            return $this->error('Producto no encontrado con esa clave SAT', 404);
        }

        return $this->success($producto, 'Producto encontrado exitosamente');
    }
}