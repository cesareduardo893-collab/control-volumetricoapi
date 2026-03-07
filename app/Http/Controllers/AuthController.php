<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Models\Bitacora;
use App\Models\Role;
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
                $user->increment('login_attempts');

                $this->logFailedAttempt($user, $request);
                return $this->error('Credenciales incorrectas', 401);
            }

            // Verificar si la cuenta está activa
            if (!$user->activo) {
                $this->logFailedAttempt($user, $request, 'Cuenta inactiva');
                return $this->forbidden('Tu cuenta está inactiva. Contacta al administrador.');
            }

            // Verificar si la contraseña ha expirado (si aplica)
            if ($user->password_expires_at && Carbon::now()->greaterThan($user->password_expires_at)) {
                $this->logFailedAttempt($user, $request, 'Contraseña expirada');
                return $this->error('Tu contraseña ha expirado. Debes cambiarla.', 403);
            }

            // Verificar si se requiere cambio de contraseña en el primer acceso
            $forceChange = $user->force_password_change ?? false;

            // Resetear intentos fallidos al login exitoso
            $user->login_attempts = 0;
            $user->last_login_at = Carbon::now();
            $user->save();

            // Crear token de acceso (Sanctum)
            $token = $user->createToken('api-token', ['*'])->plainTextToken;

            // Actualizar datos de sesión en el usuario (opcional)
            $user->session_token = $token;
            $user->session_expires_at = Carbon::now()->addMinutes(10);
            $user->save();

            // Registrar en bitácora el evento de login
            $numeroRegistro = 'B' . now()->format('YmdHis') . rand(100, 999);
            Bitacora::create([
                'numero_registro' => $numeroRegistro,
                'usuario_id' => $user->id,
                'tipo_evento' => 'ACCESO',
                'subtipo_evento' => 'LOGIN',
                'modulo' => 'Autenticación',
                'tabla' => 'users',
                'registro_id' => $user->id,
                'descripcion' => 'Inicio de sesión exitoso',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'dispositivo' => $this->parseUserAgent($request->userAgent()),
            ]);

            // Cargar roles y permisos del usuario (si se usan)
            $user->load('roles.permissions');

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
                    'perfil' => $user->perfil ?? 'USUARIO',
                    'roles' => $user->roles->pluck('nombre'),
                    'permisos' => $user->roles->flatMap->permissions->pluck('slug')->unique(),
                    'force_password_change' => $forceChange,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => 600,
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
                // Revocar token actual
                $user->currentAccessToken()->delete();

                // Limpiar datos de sesión
                $user->session_token = null;
                $user->session_expires_at = null;
                $user->save();

                // Registrar en bitácora el evento de logout
                $numeroRegistro = 'B' . now()->format('YmdHis') . rand(100, 999);
                Bitacora::create([
                    'numero_registro' => $numeroRegistro,
                    'usuario_id' => $user->id,
                    'tipo_evento' => 'ACCESO',
                    'subtipo_evento' => 'LOGOUT',
                    'modulo' => 'Autenticación',
                    'tabla' => 'users',
                    'registro_id' => $user->id,
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
                return $this->unauthorized();
            }

            // Cargar roles y permisos
            $user->load('roles.permissions');

            // Actualizar última actividad (para control de sesión)
            $user->session_expires_at = Carbon::now()->addMinutes(10);
            $user->save();

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
                    'perfil' => $user->perfil ?? 'USUARIO',
                    'activo' => $user->activo,
                    'email_verified_at' => $user->email_verified_at,
                    'created_at' => $user->created_at,
                    'roles' => $user->roles->pluck('nombre'),
                    'permisos' => $user->roles->flatMap->permissions->pluck('slug')->unique(),
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
        // Generar número de registro único
        $numeroRegistro = 'B' . now()->format('YmdHis') . rand(100, 999);

        Bitacora::create([
            'numero_registro' => $numeroRegistro,
            'usuario_id' => $user?->id,
            'tipo_evento' => 'seguridad',
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
     * Handle user registration.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'nombres' => 'required|string|max:255',
                'apellidos' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:8|confirmed',
            ]);

            // Crear nuevo usuario
            $user = User::create([
                'identificacion' => $request->identificacion ?? uniqid('USR'),
                'nombres' => $request->nombres,
                'apellidos' => $request->apellidos,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'activo' => true,
                'perfil' => 'USUARIO',
                'login_attempts' => 0,
                'force_password_change' => true,
            ]);

            // Asignar rol por defecto (Operador)
            $role = Role::where('nombre', 'Operador')->first();
            if ($role) {
                $user->roles()->attach($role->id, [
                    'asignado_por' => $user->id,
                    'fecha_asignacion' => now(),
                    'activo' => true
                ]);
            }

            // Generar número de registro único
            $numeroRegistro = 'B' . now()->format('YmdHis') . rand(100, 999);

            // Registrar en bitácora el evento de registro
            Bitacora::create([
                'numero_registro' => $numeroRegistro,
                'usuario_id' => $user->id,
                'tipo_evento' => 'administracion_sistema',
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
                    'nombres' => $user->nombres,
                    'apellidos' => $user->apellidos,
                    'email' => $user->email,
                    'perfil' => $user->perfil,
                    'activo' => $user->activo,
                ]
            ], 'Usuario registrado exitosamente');

        } catch (\Exception $e) {
            return $this->error('Error en el servidor: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Simple user agent parser to extract device/browser info.
     *
     * @param string|null $userAgent
     * @return string|null
     */
    private function parseUserAgent(?string $userAgent): ?string
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
