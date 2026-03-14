<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Bitacora;
use App\Models\Role;
use App\Models\Permission;
use App\Models\HistorialConexion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Carbon\Carbon;

class UserController extends BaseController
{
    /**
     * Listar usuarios
     */
    public function index(Request $request)
    {
        $query = User::with(['roles'])->whereNull('deleted_at');

        // Filtros
        if ($request->has('identificacion')) {
            $query->where('identificacion', 'LIKE', "%{$request->identificacion}%");
        }

        if ($request->has('nombres')) {
            $query->where('nombres', 'LIKE', "%{$request->nombres}%");
        }

        if ($request->has('apellidos')) {
            $query->where('apellidos', 'LIKE', "%{$request->apellidos}%");
        }

        if ($request->has('email')) {
            $query->where('email', 'LIKE', "%{$request->email}%");
        }

        if ($request->has('role_id')) {
            $query->whereHas('roles', function($q) use ($request) {
                $q->where('role_id', $request->role_id)
                  ->whereNull('fecha_revocacion');
            });
        }

        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        if ($request->has('bloqueados')) {
            $query->whereNotNull('locked_until')
                  ->where('locked_until', '>', Carbon::now());
        }

        $usuarios = $query->orderBy('nombres')
            ->paginate($request->get('per_page', 15));

        return $this->success($usuarios, 'Usuarios obtenidos exitosamente');
    }

    /**
     * Crear usuario
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'identificacion' => 'required|string|max:18|unique:users,identificacion',
            'nombres' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
            'password' => ['required', 'confirmed', Password::min(8)
                ->mixedCase()
                ->numbers()
                ->symbols()],
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,id',
            'activo' => 'boolean',
            'force_password_change' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $user = User::create([
                'identificacion' => $request->identificacion,
                'nombres' => $request->nombres,
                'apellidos' => $request->apellidos,
                'email' => $request->email,
                'telefono' => $request->telefono,
                'direccion' => $request->direccion,
                'password' => Hash::make($request->password),
                'activo' => $request->boolean('activo', true),
                'force_password_change' => $request->boolean('force_password_change', true),
                'password_expires_at' => Carbon::now()->addDays(90),
                'last_password_change' => Carbon::now(),
            ]);

            // Asignar roles
            foreach ($request->roles as $roleId) {
                $user->roles()->attach($roleId, [
                    'asignado_por' => auth()->id(),
                    'fecha_asignacion' => now(),
                    'activo' => true
                ]);
            }

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'CREACION_USUARIO',
                'Administración',
                "Usuario creado: {$user->email}",
                'users',
                $user->id
            );

            DB::commit();

            return $this->success($user->load('roles'), 'Usuario creado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al crear usuario: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mostrar usuario
     */
    public function show($id)
    {
        $user = User::with([
            'roles' => function($q) {
                $q->withPivot('fecha_asignacion', 'asignado_por')
                  ->whereNull('fecha_revocacion');
            }
        ])->find($id);

        if (!$user) {
            return $this->error('Usuario no encontrado', 404);
        }

        // Historial de conexiones
        $historialConexiones = HistorialConexion::where('user_id', $id)
            ->orderBy('fecha_hora', 'desc')
            ->limit(20)
            ->get();

        $user->historial_conexiones = $historialConexiones;

        return $this->success($user, 'Usuario obtenido exitosamente');
    }

    /**
     * Actualizar usuario
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->error('Usuario no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'identificacion' => "sometimes|string|max:18|unique:users,identificacion,{$id}",
            'nombres' => 'sometimes|string|max:255',
            'apellidos' => 'sometimes|string|max:255',
            'email' => "sometimes|email|max:255|unique:users,email,{$id}",
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
            'activo' => 'sometimes|boolean',
            'roles' => 'sometimes|array',
            'roles.*' => 'exists:roles,id',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $user->toArray();
            $user->update($request->except('password'));

            // Actualizar roles
            if ($request->has('roles')) {
                // Revocar roles actuales
                DB::table('user_role')
                    ->where('user_id', $user->id)
                    ->whereNull('fecha_revocacion')
                    ->update([
                        'fecha_revocacion' => now(),
                        'activo' => false
                    ]);

                // Asignar nuevos roles
                foreach ($request->roles as $roleId) {
                    $user->roles()->attach($roleId, [
                        'asignado_por' => auth()->id(),
                        'fecha_asignacion' => now(),
                        'activo' => true
                    ]);
                }
            }

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'ACTUALIZACION_USUARIO',
                'Administración',
                "Usuario actualizado: {$user->email}",
                'users',
                $user->id,
                $datosAnteriores,
                $user->toArray()
            );

            DB::commit();

            return $this->success($user->load('roles'), 'Usuario actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al actualizar usuario: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cambiar contraseña
     */
    public function cambiarPassword(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->error('Usuario no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'password_actual' => 'required_if:user_id,' . auth()->id() . '|string',
            'password' => ['required', 'confirmed', Password::min(8)
                ->mixedCase()
                ->numbers()
                ->symbols()],
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            // Verificar contraseña actual si es el mismo usuario
            if (auth()->id() == $id) {
                if (!Hash::check($request->password_actual, $user->password)) {
                    return $this->error('La contraseña actual es incorrecta', 401);
                }
            }

            $datosAnteriores = $user->toArray();

            $user->password = Hash::make($request->password);
            $user->last_password_change = Carbon::now();
            $user->password_expires_at = Carbon::now()->addDays(90);
            $user->force_password_change = false;
            $user->save();

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_SEGURIDAD,
                'CAMBIO_PASSWORD',
                'Seguridad',
                "Contraseña cambiada para usuario: {$user->email}",
                'users',
                $user->id,
                $datosAnteriores,
                $user->toArray()
            );

            DB::commit();

            return $this->success([], 'Contraseña cambiada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al cambiar contraseña: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bloquear usuario
     */
    public function bloquear(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->error('Usuario no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'motivo' => 'required|string|max:500',
            'minutos_bloqueo' => 'nullable|integer|min:1'
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $user->toArray();

            $minutos = $request->minutos_bloqueo ?? 30;
            $user->locked_until = Carbon::now()->addMinutes($minutos);
            
            $user->save();

            // Registrar en historial de conexiones
            HistorialConexion::create([
                'user_id' => $user->id,
                'fecha_hora' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'dispositivo' => $this->parseUserAgent($request->userAgent()),
                'exitosa' => false
            ]);

            // Revocar tokens
            DB::table('personal_access_tokens')
                ->where('tokenable_id', $user->id)
                ->delete();

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_SEGURIDAD,
                'BLOQUEO_USUARIO',
                'Seguridad',
                "Usuario bloqueado: {$user->email} - Motivo: {$request->motivo}",
                'users',
                $user->id,
                $datosAnteriores,
                $user->toArray()
            );

            DB::commit();

            return $this->success($user, 'Usuario bloqueado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al bloquear usuario: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Desbloquear usuario
     */
    public function desbloquear(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->error('Usuario no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'motivo' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $user->toArray();

            $user->locked_until = null;
            $user->failed_login_attempts = 0;
            $user->save();

            // Registrar en historial de conexiones
            HistorialConexion::create([
                'user_id' => $user->id,
                'fecha_hora' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'dispositivo' => $this->parseUserAgent($request->userAgent()),
                'exitosa' => true
            ]);

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_SEGURIDAD,
                'DESBLOQUEO_USUARIO',
                'Seguridad',
                "Usuario desbloqueado: {$user->email} - Motivo: {$request->motivo}",
                'users',
                $user->id,
                $datosAnteriores,
                $user->toArray()
            );

            DB::commit();

            return $this->success($user, 'Usuario desbloqueado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al desbloquear usuario: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Asignar rol
     */
    public function asignarRol(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->error('Usuario no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'rol_id' => 'required|exists:roles,id'
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            // Verificar si ya tiene el rol activo
            $rolActivo = DB::table('user_role')
                ->where('user_id', $user->id)
                ->where('role_id', $request->rol_id)
                ->whereNull('fecha_revocacion')
                ->exists();

            if ($rolActivo) {
                return $this->error('El usuario ya tiene este rol asignado', 409);
            }

            // Revocar rol activo si existe (soft delete)
            DB::table('user_role')
                ->where('user_id', $user->id)
                ->where('role_id', $request->rol_id)
                ->update([
                    'fecha_revocacion' => now(),
                    'activo' => false
                ]);

            // Asignar nuevo rol
            $user->roles()->attach($request->rol_id, [
                'asignado_por' => auth()->id(),
                'fecha_asignacion' => now(),
                'activo' => true
            ]);

            $rol = Role::find($request->rol_id);

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_SEGURIDAD,
                'ASIGNACION_ROL',
                'Seguridad',
                "Rol {$rol->nombre} asignado a usuario {$user->email}",
                'users',
                $user->id
            );

            DB::commit();

            return $this->success($user->load('roles'), 'Rol asignado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al asignar rol: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Quitar rol
     */
    public function quitarRol(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->error('Usuario no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'rol_id' => 'required|exists:roles,id'
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $rol = Role::find($request->rol_id);

            // Revocar rol
            $updated = DB::table('user_role')
                ->where('user_id', $user->id)
                ->where('role_id', $request->rol_id)
                ->whereNull('fecha_revocacion')
                ->update([
                    'fecha_revocacion' => now(),
                    'activo' => false
                ]);

            if ($updated === 0) {
                return $this->error('El usuario no tiene este rol asignado', 404);
            }

            $this->logActivity(
                auth()->id(),
                Bitacora::TIPO_EVENTO_SEGURIDAD,
                'REVOCACION_ROL',
                'Seguridad',
                "Rol {$rol->nombre} revocado de usuario {$user->email}",
                'users',
                $user->id
            );

            DB::commit();

            return $this->success($user->load('roles'), 'Rol revocado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Error al revocar rol: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener permisos del usuario
     */
    public function permisos($id)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->error('Usuario no encontrado', 404);
        }

        $rolesActivos = $user->roles()
            ->wherePivot('fecha_revocacion', null)
            ->with('permissions')
            ->get();

        $permisosDirectos = [];
        $permisosPorRol = [];

        foreach ($rolesActivos as $rol) {
            foreach ($rol->permissions as $permiso) {
                $permisosPorRol[] = [
                    'id' => $permiso->id,
                    'name' => $permiso->name,
                    'slug' => $permiso->slug,
                    'modulo' => $permiso->modulo,
                    'via_rol' => $rol->nombre
                ];
            }
        }

        return $this->success([
            'roles' => $rolesActivos->pluck('nombre'),
            'permisos_por_rol' => $permisosPorRol,
            'permisos_agrupados' => collect($permisosPorRol)->groupBy('modulo')
        ], 'Permisos del usuario obtenidos exitosamente');
    }

    /**
     * Obtener actividad del usuario
     */
    public function actividad(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->error('Usuario no encontrado', 404);
        }

        $query = Bitacora::where('usuario_id', $id);

        if ($request->has('fecha_inicio')) {
            $query->where('created_at', '>=', Carbon::parse($request->fecha_inicio));
        }

        if ($request->has('fecha_fin')) {
            $query->where('created_at', '<=', Carbon::parse($request->fecha_fin));
        }

        if ($request->has('tipo_evento')) {
            $query->where('tipo_evento', $request->tipo_evento);
        }

        $actividades = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return $this->success($actividades, 'Actividad del usuario obtenida exitosamente');
    }

    /**
     * Parsear user agent
     */
    protected function parseUserAgent(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        if (preg_match('/\((.*?)\)/', $userAgent, $matches)) {
            return $matches[1];
        }

        return substr($userAgent, 0, 100);
    }
}
