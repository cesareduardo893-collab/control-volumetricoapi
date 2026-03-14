<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PermissionController extends BaseController
{
    /**
     * Listar permisos
     */
    public function index(Request $request)
    {
        $query = Permission::query();

        // Filtros
        if ($request->has('name')) {
            $query->where('name', 'LIKE', "%{$request->name}%");
        }

        if ($request->has('slug')) {
            $query->where('slug', 'LIKE', "%{$request->slug}%");
        }

        if ($request->has('modulo')) {
            $query->where('modulo', $request->modulo);
        }

        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        $permisos = $query->orderBy('modulo')
            ->orderBy('name')
            ->paginate($request->get('per_page', 15));

        return $this->success($permisos, 'Permisos obtenidos exitosamente');
    }

    /**
     * Crear permiso
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:permissions,slug',
            'description' => 'nullable|string|max:500',
            'modulo' => 'required|string|max:100',
            'reglas' => 'nullable|array',
            'activo' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $permiso = Permission::create([
                'name' => $request->name,
                'slug' => $request->slug,
                'description' => $request->description,
                'modulo' => $request->modulo,
                'reglas' => $request->reglas,
                'activo' => $request->boolean('activo', true),
            ]);

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'CREACION_PERMISO',
                'Administración',
                "Permiso creado: {$permiso->slug}",
                'permissions',
                $permiso->id
            );

            DB::commit();

            return $this->success($permiso, 'Permiso creado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al crear permiso: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mostrar permiso
     */
    public function show($id)
    {
        $permiso = Permission::with(['roles' => function($q) {
            $q->wherePivot('activo', true);
        }])->find($id);

        if (!$permiso) {
            return $this->error('Permiso no encontrado', 404);
        }

        return $this->success($permiso, 'Permiso obtenido exitosamente');
    }

    /**
     * Actualizar permiso
     */
    public function update(Request $request, $id)
    {
        $permiso = Permission::find($id);

        if (!$permiso) {
            return $this->error('Permiso no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'slug' => "sometimes|string|max:255|unique:permissions,slug,{$id}",
            'description' => 'nullable|string|max:500',
            'modulo' => 'sometimes|string|max:100',
            'reglas' => 'nullable|array',
            'activo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $permiso->toArray();
            $permiso->update($request->all());

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'ACTUALIZACION_PERMISO',
                'Administración',
                "Permiso actualizado: {$permiso->slug}",
                'permissions',
                $permiso->id,
                $datosAnteriores,
                $permiso->toArray()
            );

            DB::commit();

            return $this->success($permiso, 'Permiso actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al actualizar permiso: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar permiso (soft delete)
     */
    public function destroy($id)
    {
        $permiso = Permission::find($id);

        if (!$permiso) {
            return $this->error('Permiso no encontrado', 404);
        }

        // Verificar si está asignado a roles
        $rolesAsignados = DB::table('role_permission')
            ->where('permission_id', $id)
            ->where('activo', true)
            ->count();

        if ($rolesAsignados > 0) {
            return $this->error("No se puede eliminar el permiso porque está asignado a {$rolesAsignados} roles", 409);
        }

        try {
            DB::beginTransaction();

            $permiso->activo = false;
            $permiso->save();
            $permiso->delete();

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'ELIMINACION_PERMISO',
                'Administración',
                "Permiso eliminado: {$permiso->slug}",
                'permissions',
                $permiso->id
            );

            DB::commit();

            return $this->success([], 'Permiso eliminado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al eliminar permiso: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener permisos por módulo
     */
    public function porModulo()
    {
        $permisos = Permission::where('activo', true)
            ->orderBy('modulo')
            ->orderBy('name')
            ->get();

        $agrupados = $permisos->groupBy('modulo')->map(function ($items, $modulo) {
            return [
                'modulo' => $modulo,
                'permisos' => $items->map(function ($p) {
                    return [
                        'id' => $p->id,
                        'name' => $p->name,
                        'slug' => $p->slug,
                        'description' => $p->description,
                    ];
                })
            ];
        })->values();

        return $this->success($agrupados, 'Permisos por módulo obtenidos exitosamente');
    }

    /**
     * Sincronizar permisos (para desarrollo)
     */
    public function sincronizar(Request $request)
    {
        if (!app()->environment('local', 'development')) {
            return $this->error('Operación no permitida en este entorno', 403);
        }

        $validator = Validator::make($request->all(), [
            'permisos' => 'required|array',
            'permisos.*.name' => 'required|string',
            'permisos.*.slug' => 'required|string',
            'permisos.*.modulo' => 'required|string',
            'permisos.*.description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $resultados = [
                'creados' => [],
                'actualizados' => [],
                'errores' => []
            ];

            foreach ($request->permisos as $datos) {
                try {
                    $permiso = Permission::where('slug', $datos['slug'])->first();

                    if ($permiso) {
                        $permiso->update([
                            'name' => $datos['name'],
                            'description' => $datos['description'] ?? $permiso->description,
                            'modulo' => $datos['modulo'],
                            'activo' => true
                        ]);
                        $resultados['actualizados'][] = $datos['slug'];
                    } else {
                        Permission::create([
                            'name' => $datos['name'],
                            'slug' => $datos['slug'],
                            'description' => $datos['description'] ?? null,
                            'modulo' => $datos['modulo'],
                            'activo' => true
                        ]);
                        $resultados['creados'][] = $datos['slug'];
                    }
                } catch (\Exception $e) {
                    $resultados['errores'][] = [
                        'permiso' => $datos['slug'],
                        'error' => $e->getMessage()
                    ];
                }
            }

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'SINCRONIZACION_PERMISOS',
                'Administración',
                "Sincronización completada. Creados: " . count($resultados['creados']) . ", Actualizados: " . count($resultados['actualizados']),
                'permissions'
            );

            DB::commit();

            return $this->success($resultados, 'Sincronización completada');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error en sincronización: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Verificar permiso
     */
    public function verificar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'permiso_slug' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        $user = User::find($request->user_id);
        
        // Obtener roles activos del usuario
        $roles = DB::table('user_role')
            ->where('user_id', $user->id)
            ->whereNull('fecha_revocacion')
            ->pluck('role_id');

        // Obtener ID del permiso
        $permisoId = DB::table('permissions')
            ->where('slug', $request->permiso_slug)
            ->where('activo', true)
            ->value('id');

        if (!$permisoId) {
            return $this->success([
                'user_id' => $user->id,
                'user_email' => $user->email,
                'permiso' => $request->permiso_slug,
                'tiene_permiso' => false,
                'via_roles' => []
            ], 'Verificación completada');
        }

        // Verificar si algún rol tiene el permiso
        $tienePermiso = DB::table('role_permission')
            ->whereIn('role_id', $roles)
            ->where('permission_id', $permisoId)
            ->where('activo', true)
            ->exists();

        $permiso = Permission::where('slug', $request->permiso_slug)->first();

        return $this->success([
            'user_id' => $user->id,
            'user_email' => $user->email,
            'permiso' => $permiso ? $permiso->name : $request->permiso_slug,
            'tiene_permiso' => $tienePermiso,
            'via_roles' => $tienePermiso ? $this->obtenerRolesConPermiso($user->id, $permisoId) : []
        ], 'Verificación completada');
    }

    /**
     * Obtener roles con permiso
     */
    private function obtenerRolesConPermiso($userId, $permissionId)
    {
        return DB::table('user_role')
            ->join('roles', 'user_role.role_id', '=', 'roles.id')
            ->join('role_permission', 'roles.id', '=', 'role_permission.role_id')
            ->where('user_role.user_id', $userId)
            ->where('user_role.fecha_revocacion', null)
            ->where('role_permission.permission_id', $permissionId)
            ->where('role_permission.activo', true)
            ->pluck('roles.nombre')
            ->toArray();
    }
}