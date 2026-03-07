<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Contribuyente;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class UserController extends BaseController
{
    /**
     * Listar usuarios
     */
    public function index(Request $request)
    {
        $query = User::with(['contribuyente', 'roles.permissions']);

        // Filtros
        if ($request->has('contribuyente_id')) {
            $query->where('contribuyente_id', $request->contribuyente_id);
        }

        if ($request->has('name')) {
            $query->where('name', 'LIKE', "%{$request->name}%");
        }

        if ($request->has('email')) {
            $query->where('email', 'LIKE', "%{$request->email}%");
        }

        if ($request->has('rfc')) {
            $query->where('rfc', 'LIKE', "%{$request->rfc}%");
        }

        if ($request->has('role')) {
            $query->whereHas('roles', function($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        if ($request->has('ultimo_acceso_desde')) {
            $query->where('ultimo_acceso', '>=', Carbon::parse($request->ultimo_acceso_desde));
        }

        $usuarios = $query->orderBy('name')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($usuarios, 'Usuarios obtenidos exitosamente');
    }

    /**
     * Crear usuario
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contribuyente_id' => 'required|exists:contribuyentes,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'rfc' => 'required|string|size:13|unique:users,rfc',
            'curp' => 'nullable|string|size:18|unique:users,curp',
            'password' => ['required', 'confirmed', Password::min(8)
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised()],
            'puesto' => 'nullable|string|max:100',
            'departamento' => 'nullable|string|max:100',
            'telefono' => 'nullable|string|max:20',
            'telefono_movil' => 'nullable|string|max:20',
            'firma_electronica' => 'nullable|string|max:50',
            'certificado_digital' => 'nullable|file|mimes:cer,crt,pem|max:5120',
            'llave_privada' => 'nullable|file|mimes:key,pem|max:5120',
            'password_firma' => 'nullable|string',
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,id',
            'permisos_especiales' => 'nullable|array',
            'permisos_especiales.*' => 'exists:permissions,id',
            'notificaciones' => 'nullable|array',
            'notificaciones.email' => 'boolean',
            'notificaciones.sms' => 'boolean',
            'notificaciones.whatsapp' => 'boolean',
            'notificaciones.push' => 'boolean',
            'notificaciones.eventos' => 'nullable|array',
            'limites' => 'nullable|array',
            'limites.max_instalaciones' => 'nullable|integer|min:1',
            'limites.operaciones_diarias' => 'nullable|integer|min:1',
            'preferencias' => 'nullable|array',
            'preferencias.idioma' => 'nullable|in:es,en',
            'preferencias.zona_horaria' => 'nullable|timezone',
            'preferencias.formato_fecha' => 'nullable|string',
            'preferencias.tema' => 'nullable|in:light,dark,auto',
            'activo' => 'boolean',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            // Procesar archivos de firma electrónica
            $rutaCertificado = null;
            $rutaLlave = null;

            if ($request->hasFile('certificado_digital')) {
                $rutaCertificado = $request->file('certificado_digital')
                    ->store("usuarios/certificados/{$request->rfc}", 'private');
            }

            if ($request->hasFile('llave_privada')) {
                $rutaLlave = $request->file('llave_privada')
                    ->store("usuarios/llaves/{$request->rfc}", 'private');
            }

            // Crear usuario
            $usuario = User::create([
                'contribuyente_id' => $request->contribuyente_id,
                'name' => $request->name,
                'email' => $request->email,
                'rfc' => $request->rfc,
                'curp' => $request->curp,
                'password' => Hash::make($request->password),
                'puesto' => $request->puesto,
                'departamento' => $request->departamento,
                'telefono' => $request->telefono,
                'telefono_movil' => $request->telefono_movil,
                'firma_electronica' => $request->firma_electronica,
                'certificado_digital' => $rutaCertificado,
                'llave_privada' => $rutaLlave,
                'password_firma' => $request->password_firma ? Hash::make($request->password_firma) : null,
                'notificaciones' => $request->notificaciones,
                'limites' => $request->limites,
                'preferencias' => $request->preferencias,
                'activo' => $request->boolean('activo', true),
                'metadata' => $request->metadata
            ]);

            // Asignar roles
            $usuario->roles()->attach($request->roles);

            // Asignar permisos especiales
            if ($request->has('permisos_especiales')) {
                $usuario->permissions()->attach($request->permisos_especiales);
            }

            $this->logActivity(
                auth()->id(),
                'seguridad',
                'creacion_usuario',
                'users',
                "Usuario creado: {$usuario->email} - RFC: {$usuario->rfc}",
                'users',
                $usuario->id
            );

            DB::commit();

            return $this->sendResponse($usuario->load(['contribuyente', 'roles', 'permissions']), 
                'Usuario creado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al crear usuario', [$e->getMessage()], 500);
        }
    }

    /**
     * Mostrar usuario
     */
    public function show($id)
    {
        $usuario = User::with([
            'contribuyente',
            'roles.permissions',
            'permissions',
            'sesiones' => function($q) {
                $q->latest()->limit(10);
            },
            'actividades' => function($q) {
                $q->latest()->limit(20);
            }
        ])->find($id);

        if (!$usuario) {
            return $this->sendError('Usuario no encontrado');
        }

        return $this->sendResponse($usuario, 'Usuario obtenido exitosamente');
    }

    /**
     * Actualizar usuario
     */
    public function update(Request $request, $id)
    {
        $usuario = User::find($id);

        if (!$usuario) {
            return $this->sendError('Usuario no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => "sometimes|email|max:255|unique:users,email,{$id}",
            'rfc' => "sometimes|string|size:13|unique:users,rfc,{$id}",
            'curp' => "nullable|string|size:18|unique:users,curp,{$id}",
            'puesto' => 'nullable|string|max:100',
            'departamento' => 'nullable|string|max:100',
            'telefono' => 'nullable|string|max:20',
            'telefono_movil' => 'nullable|string|max:20',
            'firma_electronica' => 'nullable|string|max:50',
            'certificado_digital' => 'nullable|file|mimes:cer,crt,pem|max:5120',
            'llave_privada' => 'nullable|file|mimes:key,pem|max:5120',
            'password_firma' => 'nullable|string',
            'roles' => 'sometimes|array',
            'roles.*' => 'exists:roles,id',
            'permisos_especiales' => 'nullable|array',
            'permisos_especiales.*' => 'exists:permissions,id',
            'notificaciones' => 'nullable|array',
            'limites' => 'nullable|array',
            'preferencias' => 'nullable|array',
            'activo' => 'sometimes|boolean',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $usuario->toArray();

            // Procesar archivos de firma electrónica
            if ($request->hasFile('certificado_digital')) {
                $rutaCertificado = $request->file('certificado_digital')
                    ->store("usuarios/certificados/{$usuario->rfc}", 'private');
                $usuario->certificado_digital = $rutaCertificado;
            }

            if ($request->hasFile('llave_privada')) {
                $rutaLlave = $request->file('llave_privada')
                    ->store("usuarios/llaves/{$usuario->rfc}", 'private');
                $usuario->llave_privada = $rutaLlave;
            }

            // Actualizar campos
            $usuario->fill($request->except(['password', 'password_firma']));

            if ($request->has('password_firma')) {
                $usuario->password_firma = Hash::make($request->password_firma);
            }

            $usuario->save();

            // Actualizar roles
            if ($request->has('roles')) {
                $usuario->roles()->sync($request->roles);
            }

            // Actualizar permisos especiales
            if ($request->has('permisos_especiales')) {
                $usuario->permissions()->sync($request->permisos_especiales);
            }

            $this->logActivity(
                auth()->id(),
                'seguridad',
                'actualizacion_usuario',
                'users',
                "Usuario actualizado: {$usuario->email}",
                'users',
                $usuario->id,
                $datosAnteriores,
                $usuario->toArray()
            );

            DB::commit();

            return $this->sendResponse($usuario->load(['roles', 'permissions']), 
                'Usuario actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al actualizar usuario', [$e->getMessage()], 500);
        }
    }

    /**
     * Cambiar contraseña
     */
    public function cambiarPassword(Request $request, $id)
    {
        $usuario = User::find($id);

        if (!$usuario) {
            return $this->sendError('Usuario no encontrado');
        }

        // Verificar que no sea el mismo usuario o tenga permisos
        if (auth()->id() != $id && !auth()->user()->hasPermission('cambiar_password_usuarios')) {
            return $this->sendError('No tiene permisos para cambiar la contraseña de este usuario', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'password_actual' => 'required_if:user_id,' . auth()->id() . '|string',
            'password' => ['required', 'confirmed', Password::min(8)
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised()],
            'forzar_cambio' => 'boolean'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            // Verificar contraseña actual si es el mismo usuario
            if (auth()->id() == $id) {
                if (!Hash::check($request->password_actual, $usuario->password)) {
                    return $this->sendError('La contraseña actual es incorrecta', [], 401);
                }
            }

            $datosAnteriores = $usuario->toArray();

            $usuario->password = Hash::make($request->password);
            $usuario->password_changed_at = now();
            
            if ($request->boolean('forzar_cambio')) {
                $usuario->force_password_change = true;
            }

            $usuario->save();

            // Revocar otras sesiones (opcional)
            if ($request->boolean('revocar_sesiones')) {
                DB::table('sessions')->where('user_id', $usuario->id)->delete();
            }

            $this->logActivity(
                auth()->id(),
                'seguridad',
                'cambio_password',
                'users',
                "Contraseña cambiada para usuario: {$usuario->email}",
                'users',
                $usuario->id,
                $datosAnteriores,
                $usuario->toArray()
            );

            DB::commit();

            return $this->sendResponse([], 'Contraseña cambiada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al cambiar contraseña', [$e->getMessage()], 500);
        }
    }

    /**
     * Bloquear usuario
     */
    public function bloquear(Request $request, $id)
    {
        $usuario = User::find($id);

        if (!$usuario) {
            return $this->sendError('Usuario no encontrado');
        }

        if (!$usuario->activo) {
            return $this->sendError('El usuario ya está inactivo', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'motivo' => 'required|string|max:500',
            'dias_bloqueo' => 'nullable|integer|min:1'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $usuario->toArray();

            $usuario->activo = false;
            
            $metadata = $usuario->metadata ?? [];
            $metadata['bloqueos'][] = [
                'fecha' => now()->toDateTimeString(),
                'usuario_id' => auth()->id(),
                'motivo' => $request->motivo,
                'dias_bloqueo' => $request->dias_bloqueo,
                'fecha_fin' => $request->dias_bloqueo ? now()->addDays($request->dias_bloqueo) : null
            ];
            $usuario->metadata = $metadata;
            
            $usuario->save();

            // Revocar sesiones activas
            DB::table('sessions')->where('user_id', $usuario->id)->delete();

            $this->logActivity(
                auth()->id(),
                'seguridad',
                'bloqueo_usuario',
                'users',
                "Usuario bloqueado: {$usuario->email} - Motivo: {$request->motivo}",
                'users',
                $usuario->id,
                $datosAnteriores,
                $usuario->toArray()
            );

            DB::commit();

            return $this->sendResponse($usuario, 'Usuario bloqueado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al bloquear usuario', [$e->getMessage()], 500);
        }
    }

    /**
     * Desbloquear usuario
     */
    public function desbloquear(Request $request, $id)
    {
        $usuario = User::find($id);

        if (!$usuario) {
            return $this->sendError('Usuario no encontrado');
        }

        if ($usuario->activo) {
            return $this->sendError('El usuario ya está activo', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'motivo' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $datosAnteriores = $usuario->toArray();

            $usuario->activo = true;
            $usuario->failed_login_attempts = 0;
            
            $metadata = $usuario->metadata ?? [];
            $metadata['desbloqueos'][] = [
                'fecha' => now()->toDateTimeString(),
                'usuario_id' => auth()->id(),
                'motivo' => $request->motivo
            ];
            $usuario->metadata = $metadata;
            
            $usuario->save();

            $this->logActivity(
                auth()->id(),
                'seguridad',
                'desbloqueo_usuario',
                'users',
                "Usuario desbloqueado: {$usuario->email} - Motivo: {$request->motivo}",
                'users',
                $usuario->id,
                $datosAnteriores,
                $usuario->toArray()
            );

            DB::commit();

            return $this->sendResponse($usuario, 'Usuario desbloqueado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al desbloquear usuario', [$e->getMessage()], 500);
        }
    }

    /**
     * Asignar rol
     */
    public function asignarRol(Request $request, $id)
    {
        $usuario = User::find($id);

        if (!$usuario) {
            return $this->sendError('Usuario no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'rol_id' => 'required|exists:roles,id'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $rol = Role::find($request->rol_id);

            if ($usuario->roles->contains($rol->id)) {
                return $this->sendError('El usuario ya tiene asignado este rol', [], 409);
            }

            $usuario->roles()->attach($rol->id);

            $this->logActivity(
                auth()->id(),
                'seguridad',
                'asignacion_rol',
                'users',
                "Rol {$rol->name} asignado a usuario {$usuario->email}",
                'users',
                $usuario->id
            );

            DB::commit();

            return $this->sendResponse($usuario->load('roles'), 'Rol asignado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al asignar rol', [$e->getMessage()], 500);
        }
    }

    /**
     * Quitar rol
     */
    public function quitarRol(Request $request, $id)
    {
        $usuario = User::find($id);

        if (!$usuario) {
            return $this->sendError('Usuario no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'rol_id' => 'required|exists:roles,id'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $rol = Role::find($request->rol_id);

            if (!$usuario->roles->contains($rol->id)) {
                return $this->sendError('El usuario no tiene asignado este rol', [], 404);
            }

            // Verificar que no sea el último administrador
            if ($rol->name == 'Administrador' && $usuario->roles()->where('name', 'Administrador')->count() == 1) {
                $adminCount = User::whereHas('roles', function($q) {
                    $q->where('name', 'Administrador');
                })->count();

                if ($adminCount <= 1) {
                    return $this->sendError('No se puede quitar el rol de administrador al último administrador del sistema', [], 409);
                }
            }

            $usuario->roles()->detach($rol->id);

            $this->logActivity(
                auth()->id(),
                'seguridad',
                'remocion_rol',
                'users',
                "Rol {$rol->name} removido de usuario {$usuario->email}",
                'users',
                $usuario->id
            );

            DB::commit();

            return $this->sendResponse($usuario->load('roles'), 'Rol removido exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al remover rol', [$e->getMessage()], 500);
        }
    }

    /**
     * Obtener permisos del usuario
     */
    public function permisos($id)
    {
        $usuario = User::find($id);

        if (!$usuario) {
            return $this->sendError('Usuario no encontrado');
        }

        $permisos = [
            'roles' => $usuario->roles->pluck('name'),
            'permisos_rol' => $usuario->getPermissionsViaRoles()->pluck('name'),
            'permisos_directos' => $usuario->permissions->pluck('name'),
            'todos_permisos' => $usuario->getAllPermissions()->pluck('name'),
            'estructura' => $this->estructurarPermisos($usuario)
        ];

        return $this->sendResponse($permisos, 'Permisos del usuario obtenidos exitosamente');
    }

    /**
     * Registrar inicio de sesión
     */
    public function registrarLogin(Request $request, $id)
    {
        $usuario = User::find($id);

        if (!$usuario) {
            return $this->sendError('Usuario no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'ip_address' => 'required|ip',
            'user_agent' => 'required|string',
            'dispositivo' => 'nullable|string',
            'ubicacion' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $sesion = [
                'fecha' => now()->toDateTimeString(),
                'ip' => $request->ip_address,
                'user_agent' => $request->user_agent,
                'dispositivo' => $request->dispositivo,
                'ubicacion' => $request->ubicacion,
                'exitoso' => true
            ];

            $metadata = $usuario->metadata ?? [];
            $metadata['sesiones'][] = $sesion;
            $usuario->metadata = $metadata;
            
            $usuario->ultimo_acceso = now();
            $usuario->ultimo_ip = $request->ip_address;
            $usuario->save();

            DB::commit();

            return $this->sendResponse($sesion, 'Login registrado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al registrar login', [$e->getMessage()], 500);
        }
    }

    /**
     * Obtener sesiones activas
     */
    public function sesionesActivas($id)
    {
        $usuario = User::find($id);

        if (!$usuario) {
            return $this->sendError('Usuario no encontrado');
        }

        $sesiones = DB::table('sessions')
            ->where('user_id', $usuario->id)
            ->where('last_activity', '>=', now()->subHours(24)->timestamp)
            ->get()
            ->map(function ($sesion) {
                return [
                    'id' => $sesion->id,
                    'ip_address' => $sesion->ip_address,
                    'user_agent' => $sesion->user_agent,
                    'last_activity' => Carbon::createFromTimestamp($sesion->last_activity)->toDateTimeString(),
                    'payload' => json_decode($sesion->payload, true)
                ];
            });

        return $this->sendResponse([
            'usuario_id' => $usuario->id,
            'sesiones_activas' => $sesiones->count(),
            'detalle' => $sesiones
        ], 'Sesiones activas obtenidas exitosamente');
    }

    /**
     * Cerrar sesiones (logout forzado)
     */
    public function cerrarSesiones(Request $request, $id)
    {
        $usuario = User::find($id);

        if (!$usuario) {
            return $this->sendError('Usuario no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'sesion_id' => 'nullable|string',
            'excepto_actual' => 'boolean'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $query = DB::table('sessions')->where('user_id', $usuario->id);

            if ($request->has('sesion_id')) {
                $query->where('id', $request->sesion_id);
            }

            if ($request->boolean('excepto_actual')) {
                $sessionId = request()->session()->getId();
                $query->where('id', '!=', $sessionId);
            }

            $count = $query->delete();

            $this->logActivity(
                auth()->id(),
                'seguridad',
                'cierre_sesiones',
                'users',
                "Se cerraron {$count} sesiones del usuario {$usuario->email}",
                'users',
                $usuario->id
            );

            DB::commit();

            return $this->sendResponse([
                'sesiones_cerradas' => $count
            ], 'Sesiones cerradas exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al cerrar sesiones', [$e->getMessage()], 500);
        }
    }

    /**
     * Obtener logs de actividad del usuario
     */
    public function actividad(Request $request, $id)
    {
        $usuario = User::find($id);

        if (!$usuario) {
            return $this->sendError('Usuario no encontrado');
        }

        $query = Bitacora::where('usuario_id', $id);

        if ($request->has('fecha_inicio')) {
            $query->where('fecha', '>=', Carbon::parse($request->fecha_inicio));
        }

        if ($request->has('fecha_fin')) {
            $query->where('fecha', '<=', Carbon::parse($request->fecha_fin));
        }

        if ($request->has('categoria')) {
            $query->where('categoria', $request->categoria);
        }

        if ($request->has('tipo_evento')) {
            $query->where('tipo_evento', $request->tipo_evento);
        }

        $actividades = $query->orderBy('fecha', 'desc')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($actividades, 'Actividades del usuario obtenidas exitosamente');
    }

    /**
     * Métodos privados
     */
    private function estructurarPermisos($usuario)
    {
        $todosPermisos = $usuario->getAllPermissions();
        
        $estructura = [];
        
        foreach ($todosPermisos as $permiso) {
            $partes = explode('.', $permiso->name);
            $modulo = $partes[0] ?? 'general';
            $accion = $partes[1] ?? 'ver';
            
            if (!isset($estructura[$modulo])) {
                $estructura[$modulo] = [
                    'nombre' => $modulo,
                    'permisos' => []
                ];
            }
            
            $estructura[$modulo]['permisos'][] = [
                'nombre' => $permiso->name,
                'accion' => $accion,
                'descripcion' => $permiso->description
            ];
        }
        
        return array_values($estructura);
    }
}