<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Models\Bitacora;
use App\Models\Role;
use App\Models\HistorialConexion;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends BaseController
{
    /**
     * Handle user login.
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $credentials = $request->only('email', 'password');
            
            $user = User::where('email', $credentials['email'])->first();

            // Verificar existencia del usuario
            if (!$user) {
                $this->logFailedAttempt(null, $request);
                return $this->error('Credenciales incorrectas', 401);
            }

            // Verificar contraseña
            if (!Hash::check($credentials['password'], $user->password)) {
                // Incrementar intentos fallidos
                $user->increment('failed_login_attempts');

                // Bloquear cuenta si excede intentos
                if ($user->failed_login_attempts >= 5) {
                    $user->locked_until = Carbon::now()->addMinutes(30);
                    $user->save();
                }

                $this->logFailedAttempt($user, $request);
                return $this->error('Credenciales incorrectas', 401);
            }

            // Verificar si la cuenta está bloqueada
            if ($user->locked_until && Carbon::now()->lessThan($user->locked_until)) {
                $minutesLeft = Carbon::now()->diffInMinutes($user->locked_until);
                return $this->error("Cuenta bloqueada. Intente nuevamente en {$minutesLeft} minutos", 403);
            }

            // Verificar si la cuenta está activa
            if (!$user->activo) {
                $this->logFailedAttempt($user, $request, 'Cuenta inactiva');
                return $this->error('Tu cuenta está inactiva. Contacta al administrador.', 403);
            }

            // Verificar si la contraseña ha expirado
            if ($user->password_expires_at && Carbon::now()->greaterThan($user->password_expires_at)) {
                $this->logFailedAttempt($user, $request, 'Contraseña expirada');
                return $this->error('Tu contraseña ha expirado. Debes cambiarla.', 403);
            }

            // Verificar si se requiere cambio de contraseña
            $forceChange = $user->force_password_change ?? false;

            // Resetear intentos fallidos al login exitoso
            $user->failed_login_attempts = 0;
            $user->locked_until = null;
            $user->last_login_at = Carbon::now();
            $user->last_login_ip = $request->ip();
            $user->last_login_user_agent = $request->userAgent();
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

            // Crear token de acceso (Sanctum)
            $token = $user->createToken('api-token', ['*'])->plainTextToken;

            // Actualizar datos de sesión
            $user->session_token = $token;
            $user->session_expires_at = Carbon::now()->addMinutes(60);
            $user->save();

            // Registrar en bitácora
            $numeroRegistro = 'B' . now()->format('YmdHis') . rand(100, 999);
            Bitacora::create([
                'numero_registro' => $numeroRegistro,
                'usuario_id' => $user->id,
                'tipo_evento' => Bitacora::TIPO_EVENTO_SEGURIDAD,
                'subtipo_evento' => 'LOGIN',
                'modulo' => 'Autenticación',
                'descripcion' => 'Inicio de sesión exitoso',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'dispositivo' => $this->parseUserAgent($request->userAgent()),
            ]);

            // Cargar roles y permisos
            $roles = $user->roles()->wherePivot('fecha_revocacion', null)->get();
            
            return $this->success([
                'user' => [
                    'id' => $user->id,
                    'identificacion' => $user->identificacion,
                    'nombres' => $user->nombres,
                    'apellidos' => $user->apellidos,
                    'email' => $user->email,
                    'telefono' => $user->telefono,
                    'direccion' => $user->direccion,
                    'full_name' => $user->nombres . ' ' . $user->apellidos,
                    'roles' => $roles->pluck('nombre'),
                    'force_password_change' => $forceChange,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 'Inicio de sesión exitoso');

        } catch (\Exception $e) {
            return $this->error('Error en el servidor: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle user logout.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if ($user) {
                // Registrar cierre de sesión en historial
                HistorialConexion::create([
                    'user_id' => $user->id,
                    'fecha_hora' => now(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'dispositivo' => $this->parseUserAgent($request->userAgent()),
                    'exitosa' => true
                ]);

                // Revocar token actual
                $user->currentAccessToken()->delete();

                // Limpiar datos de sesión
                $user->session_token = null;
                $user->session_expires_at = null;
                $user->save();

                // Registrar en bitácora
                $numeroRegistro = 'B' . now()->format('YmdHis') . rand(100, 999);
                Bitacora::create([
                    'numero_registro' => $numeroRegistro,
                    'usuario_id' => $user->id,
                    'tipo_evento' => Bitacora::TIPO_EVENTO_SEGURIDAD,
                    'subtipo_evento' => 'LOGOUT',
                    'modulo' => 'Autenticación',
                    'descripcion' => 'Cierre de sesión',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'dispositivo' => $this->parseUserAgent($request->userAgent()),
                ]);
            }

            return $this->success(null, 'Sesión cerrada exitosamente');

        } catch (\Exception $e) {
            return $this->error('Error al cerrar sesión: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get authenticated user info.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function user(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return $this->error('No autorizado', 401);
            }

            // Actualizar expiración de sesión
            $user->session_expires_at = Carbon::now()->addMinutes(60);
            $user->save();

            // Cargar roles activos
            $roles = $user->roles()->wherePivot('fecha_revocacion', null)->with('permissions')->get();
            
            // Obtener todos los permisos de los roles
            $permisos = collect();
            foreach ($roles as $role) {
                if ($role->permissions) {
                    $permisos = $permisos->merge($role->permissions->pluck('slug'));
                }
            }

            return $this->success([
                'user' => [
                    'id' => $user->id,
                    'identificacion' => $user->identificacion,
                    'nombres' => $user->nombres,
                    'apellidos' => $user->apellidos,
                    'email' => $user->email,
                    'telefono' => $user->telefono,
                    'direccion' => $user->direccion,
                    'full_name' => $user->nombres . ' ' . $user->apellidos,
                    'activo' => $user->activo,
                    'email_verified_at' => $user->email_verified_at,
                    'created_at' => $user->created_at,
                    'roles' => $roles->pluck('nombre'),
                    'permisos' => $permisos->unique()->values(),
                ]
            ]);

        } catch (\Exception $e) {
            return $this->error('Error al obtener usuario: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Log failed login attempt.
     *
     * @param User|null $user
     * @param Request $request
     * @param string|null $reason
     * @return void
     */
    private function logFailedAttempt(?User $user, Request $request, ?string $reason = null): void
    {
        $numeroRegistro = 'B' . now()->format('YmdHis') . rand(100, 999);

        Bitacora::create([
            'numero_registro' => $numeroRegistro,
            'usuario_id' => $user?->id,
            'tipo_evento' => Bitacora::TIPO_EVENTO_SEGURIDAD,
            'subtipo_evento' => 'LOGIN_FAILED',
            'modulo' => 'Autenticación',
            'tabla' => 'users',
            'registro_id' => $user?->id,
            'descripcion' => 'Intento de inicio de sesión fallido' . ($reason ? ": $reason" : ''),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'dispositivo' => $this->parseUserAgent($request->userAgent()),
        ]);
    }

    /**
     * Register new user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'identificacion' => 'required|string|max:18|unique:users,identificacion',
                'nombres' => 'required|string|max:255',
                'apellidos' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:8|confirmed',
                'telefono' => 'nullable|string|max:20',
                'direccion' => 'nullable|string|max:255',
            ]);

            // Crear nuevo usuario
            $user = User::create([
                'identificacion' => $request->identificacion,
                'nombres' => $request->nombres,
                'apellidos' => $request->apellidos,
                'email' => $request->email,
                'telefono' => $request->telefono,
                'direccion' => $request->direccion,
                'password' => Hash::make($request->password),
                'activo' => true,
                'force_password_change' => true,
                'password_expires_at' => Carbon::now()->addDays(90),
                'last_password_change' => Carbon::now(),
            ]);

            // Asignar rol por defecto (Operador)
            $role = Role::where('nombre', 'Operador')->first();
            if ($role) {
                $user->roles()->attach($role->id, [
                    'asignado_por' => auth()->id(),
                    'fecha_asignacion' => now(),
                    'activo' => true
                ]);
            }

            // Registrar en bitácora
            $numeroRegistro = 'B' . now()->format('YmdHis') . rand(100, 999);
            Bitacora::create([
                'numero_registro' => $numeroRegistro,
                'usuario_id' => $user->id,
                'tipo_evento' => Bitacora::TIPO_EVENTO_ADMINISTRACION,
                'subtipo_evento' => 'REGISTRO_USUARIO',
                'modulo' => 'Autenticación',
                'tabla' => 'users',
                'registro_id' => $user->id,
                'descripcion' => 'Registro de nuevo usuario',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'dispositivo' => $this->parseUserAgent($request->userAgent()),
            ]);

            return $this->success([
                'user' => [
                    'id' => $user->id,
                    'identificacion' => $user->identificacion,
                    'nombres' => $user->nombres,
                    'apellidos' => $user->apellidos,
                    'email' => $user->email,
                ]
            ], 'Usuario registrado exitosamente', 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error('Error de validación', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->error('Error en el servidor: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Simple user agent parser.
     *
     * @param string|null $userAgent
     * @return string|null
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