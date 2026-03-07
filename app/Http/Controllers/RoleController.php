<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoleController extends BaseController
{
    /**
     * Listar roles
     */
    public function index(Request $request)
    {
        $query = Role::with(['permissions']);

        // Filtros
        if ($request->has('name')) {
            $query->where('name', 'LIKE', "%{$request->name}%");
        }

        if ($request->has('guard_name')) {
            $query->where('guard_name', $request->guard_name);
        }

        if ($request->has('sistema')) {
            $query->where('sistema', $request->boolean('sistema'));
        }

        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        $roles = $query->orderBy('name')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($roles, 'Roles obtenidos exitosamente');
    }

    /**
     * Crear rol
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:roles,name',
            'guard_name' => 'required|in:web,api',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'nivel_jerarquico' => 'nullable|integer|min:1|max:100',
            'sistema' => 'boolean',
            'activo' => 'boolean',
            'permisos' => 'nullable|array',
            'permisos.*' => 'exists:permissions,id',
            'configuracion' => 'nullable|array',
            'configuracion.limite_usuarios' => 'nullable|integer|min:1',
            'configuracion.puede_asignar_roles' => 'boolean',
            'configuracion.puede_gestionar_permisos' => 'boolean',
            'configuracion.visible_front' => 'boolean',
            'configuracion.color' => 'nullable|string|max:20',
            'configuracion.icono' => 'nullable|string|max:50',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $rol = Role::create([
                'name' => $request->name,
                'guard_name' => $request->guard_name,
                'display_name' => $request->display_name,
                'description' => $request->description,
                'nivel_jerarquico' => $request->nivel_jerarquico,
                'sistema' => $request->boolean('sistema', false),
                'activo' => $request->boolean('activo', true),
                'configuracion' => $request->configuracion,
                'metadata' => $request->metadata
            ]);

            // Asignar permisos
            if ($request->has('permisos')) {
                $rol->permissions()->attach($request->permisos);
            }

            $this->logActivity(
                auth()->id(),
                'seguridad',
                'creacion_rol',
                'roles',
                "Rol creado: {$rol->name}",
                'roles',
                $rol->id
            );

            DB::commit();

            return $this->sendResponse($rol->load('permissions'), 'Rol creado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al crear rol', [$e->getMessage()], 500);
        }
    }

    /**
     * Mostrar rol
     */
    public function show($id)
    {
        $rol = Role::with([
            'permissions',
            'users' => function($q) {
                $q->select('id', 'name', 'email', 'activo')->limit(10);
            }
        ])->find($id);

        if (!$rol) {
            return $this->sendError('Rol no encontrado');
        }

        // Estadísticas de uso
        $rol->estadisticas = [
            'total_usuarios' => $rol->users()->count(),
            'usuarios_activos' => $rol->users()->where('activo', true)->count(),
            'permisos_agrupados' => $this->agruparPermisos($rol->permissions)
        ];

        return $this->sendResponse($rol, 'Rol obtenido exitosamente');
    }

    /**
     * Actualizar rol
     */
    public function update(Request $request, $id)
    {
        $rol = Role::find($id);

        if (!$rol) {
            return $this->sendError('Rol no encontrado');
        }

        // Validar que no sea un rol de sistema
        if ($rol->sistema && !auth()->user()->hasPermission('modificar_roles_sistema')) {
            return $this->sendError('No tiene permisos para modificar roles de sistema', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('roles')->ignore($rol->id)
            ],
            'display_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:500',
            'nivel_jerarquico' => 'nullable|integer|min:1|max:100',
            'activo' => 'sometimes|boolean',
            'permisos' => 'nullable|array',
            'permisos.*' => 'exists:permissions,id',
            'configuracion' => 'nullable|array',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $rol->toArray();

            // Actualizar campos básicos
            $rol->fill($request->only([
                'name', 'display_name', 'description', 
                'nivel_jerarquico', 'activo', 'configuracion', 'metadata'
            ]));

            // No permitir cambiar el guard_name si ya tiene usuarios
            if ($rol->users()->count() > 0 && $request->has('guard_name')) {
                return $this->sendError('No se puede cambiar el guard_name de un rol con usuarios asignados', [], 422);
            }

            $rol->save();

            // Actualizar permisos
            if ($request->has('permisos')) {
                $rol->permissions()->sync($request->permisos);
            }

            $this->logActivity(
                auth()->id(),
                'seguridad',
                'actualizacion_rol',
                'roles',
                "Rol actualizado: {$rol->name}",
                'roles',
                $rol->id,
                $datosAnteriores,
                $rol->toArray()
            );

            DB::commit();

            return $this->sendResponse($rol->load('permissions'), 'Rol actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al actualizar rol', [$e->getMessage()], 500);
        }
    }

    /**
     * Eliminar rol (soft delete)
     */
    public function destroy($id)
    {
        $rol = Role::find($id);

        if (!$rol) {
            return $this->sendError('Rol no encontrado');
        }

        // Validar que no sea un rol de sistema
        if ($rol->sistema) {
            return $this->sendError('No se puede eliminar un rol de sistema', [], 403);
        }

        // Verificar si tiene usuarios asignados
        $usuariosAsignados = $rol->users()->count();
        if ($usuariosAsignados > 0) {
            return $this->sendError("No se puede eliminar el rol porque tiene {$usuariosAsignados} usuarios asignados", [], 409);
        }

        try {
            DB::beginTransaction();

            $rol->activo = false;
            $rol->save();
            $rol->delete();

            $this->logActivity(
                auth()->id(),
                'seguridad',
                'eliminacion_rol',
                'roles',
                "Rol eliminado: {$rol->name}",
                'roles',
                $rol->id
            );

            DB::commit();

            return $this->sendResponse([], 'Rol eliminado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al eliminar rol', [$e->getMessage()], 500);
        }
    }

    /**
     * Asignar permisos al rol
     */
    public function asignarPermisos(Request $request, $id)
    {
        $rol = Role::find($id);

        if (!$rol) {
            return $this->sendError('Rol no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'permisos' => 'required|array',
            'permisos.*' => 'exists:permissions,id',
            'modo' => 'required|in:agregar,remover,sync'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $rol->permissions()->pluck('id')->toArray();

            switch ($request->modo) {
                case 'agregar':
                    $rol->permissions()->attach($request->permisos);
                    $mensaje = 'Permisos agregados exitosamente';
                    break;
                case 'remover':
                    $rol->permissions()->detach($request->permisos);
                    $mensaje = 'Permisos removidos exitosamente';
                    break;
                case 'sync':
                    $rol->permissions()->sync($request->permisos);
                    $mensaje = 'Permisos sincronizados exitosamente';
                    break;
            }

            $nuevosPermisos = $rol->permissions()->pluck('id')->toArray();

            $this->logActivity(
                auth()->id(),
                'seguridad',
                'asignacion_permisos_rol',
                'roles',
                "Permisos actualizados para rol {$rol->name} - Modo: {$request->modo}",
                'roles',
                $rol->id,
                ['permisos_anteriores' => $datosAnteriores],
                ['permisos_nuevos' => $nuevosPermisos]
            );

            DB::commit();

            return $this->sendResponse($rol->load('permissions'), $mensaje);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al asignar permisos', [$e->getMessage()], 500);
        }
    }

    /**
     * Clonar rol
     */
    public function clonar(Request $request, $id)
    {
        $rolOriginal = Role::find($id);

        if (!$rolOriginal) {
            return $this->sendError('Rol no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:roles,name',
            'display_name' => 'required|string|max:255',
            'incluir_permisos' => 'boolean',
            'descripcion' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $nuevoRol = Role::create([
                'name' => $request->name,
                'guard_name' => $rolOriginal->guard_name,
                'display_name' => $request->display_name,
                'description' => $request->descripcion ?? $rolOriginal->description,
                'nivel_jerarquico' => $rolOriginal->nivel_jerarquico,
                'sistema' => false, // Los roles clonados nunca son de sistema
                'activo' => true,
                'configuracion' => $rolOriginal->configuracion,
                'metadata' => [
                    'clonado_de' => [
                        'id' => $rolOriginal->id,
                        'name' => $rolOriginal->name,
                        'fecha' => now()->toDateTimeString(),
                        'usuario_id' => auth()->id()
                    ]
                ]
            ]);

            // Clonar permisos si se solicita
            if ($request->boolean('incluir_permisos')) {
                $permisos = $rolOriginal->permissions()->pluck('id')->toArray();
                $nuevoRol->permissions()->attach($permisos);
            }

            $this->logActivity(
                auth()->id(),
                'seguridad',
                'clonacion_rol',
                'roles',
                "Rol clonado: {$rolOriginal->name} -> {$nuevoRol->name}",
                'roles',
                $nuevoRol->id
            );

            DB::commit();

            return $this->sendResponse($nuevoRol->load('permissions'), 'Rol clonado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al clonar rol', [$e->getMessage()], 500);
        }
    }

    /**
     * Obtener matriz de permisos
     */
    public function matrizPermisos()
    {
        $roles = Role::with('permissions')
            ->where('activo', true)
            ->orderBy('nivel_jerarquico')
            ->get();

        $permisos = Permission::orderBy('name')->get();

        $matriz = [];

        foreach ($permisos as $permiso) {
            $fila = [
                'permiso' => $permiso->name,
                'display_name' => $permiso->display_name,
                'modulo' => explode('.', $permiso->name)[0] ?? 'general',
                'roles' => []
            ];

            foreach ($roles as $rol) {
                $fila['roles'][$rol->name] = $rol->permissions->contains($permiso->id);
            }

            $matriz[] = $fila;
        }

        $resultado = [
            'roles' => $roles->map(function($r) {
                return [
                    'id' => $r->id,
                    'name' => $r->name,
                    'display_name' => $r->display_name,
                    'nivel' => $r->nivel_jerarquico
                ];
            }),
            'matriz' => $matriz,
            'resumen' => [
                'total_roles' => $roles->count(),
                'total_permisos' => $permisos->count(),
                'asignaciones_totales' => DB::table('role_has_permissions')->count()
            ]
        ];

        return $this->sendResponse($resultado, 'Matriz de permisos obtenida exitosamente');
    }

    /**
     * Obtener permisos del rol
     */
    public function permisos($id)
    {
        $rol = Role::find($id);

        if (!$rol) {
            return $this->sendError('Rol no encontrado');
        }

        $permisos = $rol->permissions()->orderBy('name')->get();

        return $this->sendResponse([
            'rol' => [
                'id' => $rol->id,
                'name' => $rol->name,
                'display_name' => $rol->display_name
            ],
            'permisos' => $permisos,
            'agrupados' => $this->agruparPermisos($permisos)
        ], 'Permisos del rol obtenidos exitosamente');
    }

    /**
     * Obtener usuarios del rol
     */
    public function usuarios(Request $request, $id)
    {
        $rol = Role::find($id);

        if (!$rol) {
            return $this->sendError('Rol no encontrado');
        }

        $usuarios = $rol->users()
            ->select('id', 'name', 'email', 'rfc', 'activo', 'ultimo_acceso')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($usuarios, 'Usuarios del rol obtenidos exitosamente');
    }

    /**
     * Métodos privados
     */
    private function agruparPermisos($permisos)
    {
        $agrupados = [];

        foreach ($permisos as $permiso) {
            $partes = explode('.', $permiso->name);
            $modulo = $partes[0] ?? 'general';
            $accion = $partes[1] ?? 'ver';
            
            if (!isset($agrupados[$modulo])) {
                $agrupados[$modulo] = [
                    'modulo' => $modulo,
                    'permisos' => []
                ];
            }
            
            $agrupados[$modulo]['permisos'][] = [
                'id' => $permiso->id,
                'name' => $permiso->name,
                'accion' => $accion,
                'display_name' => $permiso->display_name
            ];
        }

        return array_values($agrupados);
    }
}