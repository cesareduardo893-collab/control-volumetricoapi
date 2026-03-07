<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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

        if ($request->has('guard_name')) {
            $query->where('guard_name', $request->guard_name);
        }

        if ($request->has('modulo')) {
            $query->where('name', 'LIKE', "{$request->modulo}.%");
        }

        if ($request->has('sistema')) {
            $query->where('sistema', $request->boolean('sistema'));
        }

        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        $permisos = $query->orderBy('name')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($permisos, 'Permisos obtenidos exitosamente');
    }

    /**
     * Crear permiso
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:permissions,name',
            'guard_name' => 'required|in:web,api',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'modulo' => 'required|string|max:100',
            'accion' => 'required|string|max:100',
            'sistema' => 'boolean',
            'activo' => 'boolean',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $permiso = Permission::create([
                'name' => $request->name,
                'guard_name' => $request->guard_name,
                'display_name' => $request->display_name,
                'description' => $request->description,
                'modulo' => $request->modulo,
                'accion' => $request->accion,
                'sistema' => $request->boolean('sistema', false),
                'activo' => $request->boolean('activo', true),
                'metadata' => $request->metadata
            ]);

            $this->logActivity(
                auth()->id(),
                'seguridad',
                'creacion_permiso',
                'permissions',
                "Permiso creado: {$permiso->name}",
                'permissions',
                $permiso->id
            );

            DB::commit();

            return $this->sendResponse($permiso, 'Permiso creado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al crear permiso', [$e->getMessage()], 500);
        }
    }

    /**
     * Mostrar permiso
     */
    public function show($id)
    {
        $permiso = Permission::with(['roles' => function($q) {
            $q->select('id', 'name', 'display_name');
        }])->find($id);

        if (!$permiso) {
            return $this->sendError('Permiso no encontrado');
        }

        // Estadísticas de uso
        $permiso->estadisticas = [
            'total_roles' => $permiso->roles->count(),
            'roles_activos' => $permiso->roles()->where('activo', true)->count(),
            'usuarios_con_permiso' => $this->contarUsuariosConPermiso($permiso)
        ];

        return $this->sendResponse($permiso, 'Permiso obtenido exitosamente');
    }

    /**
     * Actualizar permiso
     */
    public function update(Request $request, $id)
    {
        $permiso = Permission::find($id);

        if (!$permiso) {
            return $this->sendError('Permiso no encontrado');
        }

        // Validar que no sea un permiso de sistema
        if ($permiso->sistema && !auth()->user()->hasPermission('modificar_permisos_sistema')) {
            return $this->sendError('No tiene permisos para modificar permisos de sistema', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('permissions')->ignore($permiso->id)
            ],
            'display_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:500',
            'modulo' => 'sometimes|string|max:100',
            'accion' => 'sometimes|string|max:100',
            'activo' => 'sometimes|boolean',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $permiso->toArray();

            // No permitir cambiar el guard_name si ya está en uso
            if ($request->has('guard_name') && $permiso->roles()->count() > 0) {
                return $this->sendError('No se puede cambiar el guard_name de un permiso asignado a roles', [], 422);
            }

            $permiso->fill($request->all());
            $permiso->save();

            $this->logActivity(
                auth()->id(),
                'seguridad',
                'actualizacion_permiso',
                'permissions',
                "Permiso actualizado: {$permiso->name}",
                'permissions',
                $permiso->id,
                $datosAnteriores,
                $permiso->toArray()
            );

            DB::commit();

            return $this->sendResponse($permiso, 'Permiso actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al actualizar permiso', [$e->getMessage()], 500);
        }
    }

    /**
     * Eliminar permiso (soft delete)
     */
    public function destroy($id)
    {
        $permiso = Permission::find($id);

        if (!$permiso) {
            return $this->sendError('Permiso no encontrado');
        }

        // Validar que no sea un permiso de sistema
        if ($permiso->sistema) {
            return $this->sendError('No se puede eliminar un permiso de sistema', [], 403);
        }

        // Verificar si tiene roles asignados
        $rolesAsignados = $permiso->roles()->count();
        if ($rolesAsignados > 0) {
            return $this->sendError("No se puede eliminar el permiso porque está asignado a {$rolesAsignados} roles", [], 409);
        }

        try {
            DB::beginTransaction();

            $permiso->activo = false;
            $permiso->save();
            $permiso->delete();

            $this->logActivity(
                auth()->id(),
                'seguridad',
                'eliminacion_permiso',
                'permissions',
                "Permiso eliminado: {$permiso->name}",
                'permissions',
                $permiso->id
            );

            DB::commit();

            return $this->sendResponse([], 'Permiso eliminado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al eliminar permiso', [$e->getMessage()], 500);
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

        $agrupados = [];

        foreach ($permisos as $permiso) {
            $modulo = $permiso->modulo;
            
            if (!isset($agrupados[$modulo])) {
                $agrupados[$modulo] = [
                    'modulo' => $modulo,
                    'permisos' => []
                ];
            }
            
            $agrupados[$modulo]['permisos'][] = [
                'id' => $permiso->id,
                'name' => $permiso->name,
                'display_name' => $permiso->display_name,
                'accion' => $permiso->accion,
                'description' => $permiso->description
            ];
        }

        return $this->sendResponse(array_values($agrupados), 'Permisos por módulo obtenidos exitosamente');
    }

    /**
     * Sincronizar permisos (para desarrollo/migraciones)
     */
    public function sincronizar(Request $request)
    {
        if (!app()->environment('local', 'development')) {
            return $this->sendError('Esta operación solo está disponible en entornos de desarrollo', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'permisos' => 'required|array',
            'permisos.*.name' => 'required|string',
            'permisos.*.display_name' => 'required|string',
            'permisos.*.modulo' => 'required|string',
            'permisos.*.accion' => 'required|string',
            'permisos.*.guard_name' => 'sometimes|in:web,api',
            'permisos.*.description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $resultados = [
                'creados' => [],
                'actualizados' => [],
                'sin_cambios' => [],
                'errores' => []
            ];

            foreach ($request->permisos as $datos) {
                try {
                    $permiso = Permission::where('name', $datos['name'])->first();

                    if ($permiso) {
                        // Actualizar existente
                        $cambios = false;
                        foreach (['display_name', 'description', 'modulo', 'accion'] as $campo) {
                            if (isset($datos[$campo]) && $permiso->$campo != $datos[$campo]) {
                                $permiso->$campo = $datos[$campo];
                                $cambios = true;
                            }
                        }

                        if ($cambios) {
                            $permiso->save();
                            $resultados['actualizados'][] = $permiso->name;
                        } else {
                            $resultados['sin_cambios'][] = $permiso->name;
                        }
                    } else {
                        // Crear nuevo
                        $permiso = Permission::create([
                            'name' => $datos['name'],
                            'guard_name' => $datos['guard_name'] ?? 'web',
                            'display_name' => $datos['display_name'],
                            'description' => $datos['description'] ?? null,
                            'modulo' => $datos['modulo'],
                            'accion' => $datos['accion'],
                            'sistema' => true,
                            'activo' => true
                        ]);
                        $resultados['creados'][] = $permiso->name;
                    }
                } catch (\Exception $e) {
                    $resultados['errores'][] = [
                        'permiso' => $datos['name'],
                        'error' => $e->getMessage()
                    ];
                }
            }

            $this->logActivity(
                auth()->id(),
                'seguridad',
                'sincronizacion_permisos',
                'permissions',
                "Sincronización de permisos completada. Creados: " . count($resultados['creados']) . ", Actualizados: " . count($resultados['actualizados']),
                'permissions'
            );

            DB::commit();

            return $this->sendResponse($resultados, 'Sincronización de permisos completada');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error en sincronización de permisos', [$e->getMessage()], 500);
        }
    }

    /**
     * Verificar permiso
     */
    public function verificar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'permission_name' => 'required|string',
            'user_id' => 'nullable|exists:users,id'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        $userId = $request->user_id ?? auth()->id();
        $user = User::find($userId);

        if (!$user) {
            return $this->sendError('Usuario no encontrado');
        }

        $tienePermiso = $user->hasPermission($request->permission_name);

        return $this->sendResponse([
            'user_id' => $userId,
            'user_name' => $user->name,
            'permission' => $request->permission_name,
            'tiene_permiso' => $tienePermiso,
            'via' => $tienePermiso ? $this->obtenerViaPermiso($user, $request->permission_name) : null
        ], 'Verificación de permiso completada');
    }

    /**
     * Métodos privados
     */
    private function contarUsuariosConPermiso($permiso)
    {
        // Contar usuarios que tienen este permiso directamente
        $directos = DB::table('user_has_permissions')
            ->where('permission_id', $permiso->id)
            ->count();

        // Contar usuarios que tienen este permiso a través de roles
        $roles = $permiso->roles()->pluck('id');
        $porRoles = 0;
        
        if ($roles->isNotEmpty()) {
            $porRoles = DB::table('user_has_roles')
                ->whereIn('role_id', $roles)
                ->distinct('user_id')
                ->count('user_id');
        }

        return [
            'directos' => $directos,
            'por_roles' => $porRoles,
            'total' => $directos + $porRoles
        ];
    }

    private function obtenerViaPermiso($user, $permissionName)
    {
        if ($user->hasDirectPermission($permissionName)) {
            return 'DIRECTO';
        }

        foreach ($user->roles as $rol) {
            if ($rol->hasPermissionTo($permissionName)) {
                return "ROL: {$rol->name}";
            }
        }

        return null;
    }
}