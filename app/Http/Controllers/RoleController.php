<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class RoleController extends BaseController
{
    /**
     * Listar roles
     */
    public function index(Request $request)
    {
        $query = Role::with(['permissions']);

        // Filtros
        if ($request->has('nombre')) {
            $query->where('nombre', 'LIKE', "%{$request->nombre}%");
        }

        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        if ($request->has('es_administrador')) {
            $query->where('es_administrador', $request->boolean('es_administrador'));
        }

        if ($request->has('nivel_minimo')) {
            $query->where('nivel_jerarquico', '>=', $request->nivel_minimo);
        }

        $roles = $query->orderBy('nivel_jerarquico')
            ->orderBy('nombre')
            ->paginate($request->get('per_page', 15));

        return $this->success($roles, 'Roles obtenidos exitosamente');
    }

    /**
     * Crear rol
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255|unique:roles,nombre',
            'descripcion' => 'nullable|string|max:500',
            'nivel_jerarquico' => 'required|integer|min:1|max:100',
            'es_administrador' => 'boolean',
            'activo' => 'boolean',
            'permisos' => 'nullable|array',
            'permisos.*' => 'exists:permissions,id',
            'restricciones_acceso' => 'nullable|array',
            'configuracion_ui' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $rol = Role::create([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'nivel_jerarquico' => $request->nivel_jerarquico,
                'es_administrador' => $request->boolean('es_administrador', false),
                'activo' => $request->boolean('activo', true),
                'restricciones_acceso' => $request->restricciones_acceso,
                'configuracion_ui' => $request->configuracion_ui,
            ]);

            // Asignar permisos
            if ($request->has('permisos')) {
                foreach ($request->permisos as $permisoId) {
                    DB::table('role_permission')->insert([
                        'role_id' => $rol->id,
                        'permission_id' => $permisoId,
                        'activo' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'CREACION_ROL',
                'Administración',
                "Rol creado: {$rol->nombre}",
                'roles',
                $rol->id
            );

            DB::commit();

            return $this->success($rol->load('permissions'), 'Rol creado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al crear rol: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mostrar rol
     */
    public function show($id)
    {
        $rol = Role::with(['permissions'])->find($id);

        if (!$rol) {
            return $this->error('Rol no encontrado', 404);
        }

        // Contar usuarios con este rol activo
        $usuariosActivos = DB::table('user_role')
            ->where('role_id', $id)
            ->whereNull('fecha_revocacion')
            ->count();

        $rol->usuarios_activos = $usuariosActivos;

        return $this->success($rol, 'Rol obtenido exitosamente');
    }

    /**
     * Actualizar rol
     */
    public function update(Request $request, $id)
    {
        $rol = Role::find($id);

        if (!$rol) {
            return $this->error('Rol no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => "sometimes|string|max:255|unique:roles,nombre,{$id}",
            'descripcion' => 'nullable|string|max:500',
            'nivel_jerarquico' => 'sometimes|integer|min:1|max:100',
            'es_administrador' => 'sometimes|boolean',
            'activo' => 'sometimes|boolean',
            'permisos' => 'nullable|array',
            'permisos.*' => 'exists:permissions,id',
            'restricciones_acceso' => 'nullable|array',
            'configuracion_ui' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $rol->toArray();
            $rol->update($request->except('permisos'));

            // Actualizar permisos si se proporcionan
            if ($request->has('permisos')) {
                // Desactivar permisos actuales
                DB::table('role_permission')
                    ->where('role_id', $rol->id)
                    ->update([
                        'activo' => false, 
                        'deleted_at' => now()
                    ]);

                // Asignar nuevos permisos
                foreach ($request->permisos as $permisoId) {
                    DB::table('role_permission')->updateOrInsert(
                        [
                            'role_id' => $rol->id,
                            'permission_id' => $permisoId
                        ],
                        [
                            'activo' => true,
                            'deleted_at' => null,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]
                    );
                }
            }

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'ACTUALIZACION_ROL',
                'Administración',
                "Rol actualizado: {$rol->nombre}",
                'roles',
                $rol->id,
                $datosAnteriores,
                $rol->toArray()
            );

            DB::commit();

            return $this->success($rol->load('permissions'), 'Rol actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al actualizar rol: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar rol (soft delete)
     */
    public function destroy($id)
    {
        $rol = Role::find($id);

        if (!$rol) {
            return $this->error('Rol no encontrado', 404);
        }

        // Verificar si tiene usuarios asignados activos
        $usuariosActivos = DB::table('user_role')
            ->where('role_id', $id)
            ->whereNull('fecha_revocacion')
            ->count();

        if ($usuariosActivos > 0) {
            return $this->error("No se puede eliminar el rol porque tiene {$usuariosActivos} usuarios asignados", 409);
        }

        try {
            DB::beginTransaction();

            $rol->activo = false;
            $rol->save();
            $rol->delete();

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'ELIMINACION_ROL',
                'Administración',
                "Rol eliminado: {$rol->nombre}",
                'roles',
                $rol->id
            );

            DB::commit();

            return $this->success([], 'Rol eliminado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al eliminar rol: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Asignar permisos al rol
     */
    public function asignarPermisos(Request $request, $id)
    {
        $rol = Role::find($id);

        if (!$rol) {
            return $this->error('Rol no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'permisos' => 'required|array',
            'permisos.*' => 'exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $permisosAnteriores = $rol->permissions()->pluck('permission_id')->toArray();

            // Desactivar permisos actuales
            DB::table('role_permission')
                ->where('role_id', $rol->id)
                ->update([
                    'activo' => false, 
                    'deleted_at' => now()
                ]);

            // Asignar nuevos permisos
            foreach ($request->permisos as $permisoId) {
                DB::table('role_permission')->updateOrInsert(
                    [
                        'role_id' => $rol->id,
                        'permission_id' => $permisoId
                    ],
                    [
                        'activo' => true,
                        'deleted_at' => null,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                );
            }

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'ASIGNACION_PERMISOS_ROL',
                'Administración',
                "Permisos asignados al rol {$rol->nombre}",
                'roles',
                $rol->id,
                ['permisos_anteriores' => $permisosAnteriores],
                ['permisos_nuevos' => $request->permisos]
            );

            DB::commit();

            return $this->success($rol->load('permissions'), 'Permisos asignados exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al asignar permisos: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener matriz de permisos
     */
    public function matrizPermisos()
    {
        $roles = Role::where('activo', true)
            ->orderBy('nivel_jerarquico')
            ->get();

        $permisos = Permission::where('activo', true)
            ->orderBy('modulo')
            ->orderBy('name')
            ->get();

        $matriz = [];

        foreach ($permisos as $permiso) {
            $fila = [
                'permiso' => [
                    'id' => $permiso->id,
                    'name' => $permiso->name,
                    'slug' => $permiso->slug,
                    'modulo' => $permiso->modulo,
                ],
                'roles' => []
            ];

            foreach ($roles as $rol) {
                $tienePermiso = DB::table('role_permission')
                    ->where('role_id', $rol->id)
                    ->where('permission_id', $permiso->id)
                    ->where('activo', true)
                    ->exists();

                $fila['roles'][$rol->id] = $tienePermiso;
            }

            $matriz[] = $fila;
        }

        return $this->success([
            'roles' => $roles,
            'permisos' => $permisos,
            'matriz' => $matriz
        ], 'Matriz de permisos obtenida exitosamente');
    }

    /**
     * Clonar rol
     */
    public function clonar(Request $request, $id)
    {
        $rolOriginal = Role::find($id);

        if (!$rolOriginal) {
            return $this->error('Rol no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255|unique:roles,nombre',
            'incluir_permisos' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $nuevoRol = Role::create([
                'nombre' => $request->nombre,
                'descripcion' => $rolOriginal->descripcion,
                'nivel_jerarquico' => $rolOriginal->nivel_jerarquico,
                'es_administrador' => false,
                'activo' => true,
                'restricciones_acceso' => $rolOriginal->restricciones_acceso,
                'configuracion_ui' => $rolOriginal->configuracion_ui,
            ]);

            // Clonar permisos si se solicita
            if ($request->boolean('incluir_permisos')) {
                $permisos = $rolOriginal->permissions()->pluck('permission_id')->toArray();
                
                foreach ($permisos as $permisoId) {
                    DB::table('role_permission')->insert([
                        'role_id' => $nuevoRol->id,
                        'permission_id' => $permisoId,
                        'activo' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'CLONACION_ROL',
                'Administración',
                "Rol clonado: {$rolOriginal->nombre} -> {$nuevoRol->nombre}",
                'roles',
                $nuevoRol->id
            );

            DB::commit();

            return $this->success($nuevoRol->load('permissions'), 'Rol clonado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al clonar rol: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener permisos del rol
     */
    public function permisos($id)
    {
        $rol = Role::find($id);

        if (!$rol) {
            return $this->error('Rol no encontrado', 404);
        }

        $permisos = $rol->permissions()
            ->where('activo', true)
            ->orderBy('modulo')
            ->orderBy('name')
            ->get();

        return $this->success([
            'rol' => [
                'id' => $rol->id,
                'nombre' => $rol->nombre,
            ],
            'permisos' => $permisos,
            'agrupados' => $permisos->groupBy('modulo')
        ], 'Permisos del rol obtenidos exitosamente');
    }
}