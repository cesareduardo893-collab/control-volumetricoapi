<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Bitacora;
use App\Models\HistorialConexion;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends BaseController
{
    const ADMIN_EMAIL = 'controlvolumetrico69@gmail.com';

    /**
     * Redirect to Google OAuth
     */
    public function redirectToGoogle()
    {
        try {
            return Socialite::driver('google')->redirect();
        } catch (\Exception $e) {
            Log::error('Error al redirigir a Google: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'google_client_id' => config('services.google.client_id'),
                'google_redirect' => config('services.google.redirect'),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al conectar con Google: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle Google OAuth callback
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            // Verificar si hay un error en la respuesta de Google
            if ($request->has('error')) {
                return redirect()->to(config('app.frontend_url', 'http://localhost:8001') . '/login?error=google_auth_failed');
            }

            // Obtener usuario de Google
            $googleUser = Socialite::driver('google')->stateless()->user();

            Log::info('Google Auth Callback', [
                'email' => $googleUser->getEmail(),
                'name' => $googleUser->getName(),
                'id' => $googleUser->getId(),
            ]);

            // Buscar usuario existente por google_id o email
            $user = User::where('google_id', $googleUser->getId())
                ->orWhere('email', $googleUser->getEmail())
                ->first();

            if ($user) {
                // Usuario existe - actualizar datos de Google si es necesario
                $user = $this->updateExistingUser($user, $googleUser);
            } else {
                // Nuevo usuario - crear cuenta
                $user = $this->createNewUser($googleUser, $request);
            }

            // Verificar si la cuenta está activa
            if (!$user->activo) {
                $this->logFailedAttempt($user, $request, 'Cuenta inactiva');
                return redirect()->to(config('app.frontend_url', 'http://localhost:8001') . '/login?error=account_inactive');
            }

            // Resetear intentos fallidos
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
                'subtipo_evento' => 'LOGIN_GOOGLE',
                'modulo' => 'Autenticación',
                'descripcion' => 'Inicio de sesión exitoso con Google',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'dispositivo' => $this->parseUserAgent($request->userAgent()),
            ]);

            // Cargar roles
            $roles = $user->roles()->wherePivot('fecha_revocacion', null)->get();

            // Redirigir al frontend con el token
            $frontendUrl = config('app.frontend_url', 'http://localhost:8001');
            $redirectUrl = $frontendUrl . '/auth/google/callback?' . http_build_query([
                'token' => $token,
                'user_id' => $user->id,
                'user_name' => $user->nombres . ' ' . $user->apellidos,
                'user_email' => $user->email,
                'user_roles' => $roles->pluck('nombre')->implode(','),
            ]);

            return redirect()->to($redirectUrl);

        } catch (\Exception $e) {
            Log::error('Error en callback de Google: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->to(config('app.frontend_url', 'http://localhost:8001') . '/login?error=google_auth_error');
        }
    }

    /**
     * Handle Google Sign-In from frontend (receives id_token)
     */
    public function handleGoogleSignIn(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_token' => 'required|string',
            ]);

            // Verificar el token de Google
            $googleUser = Socialite::driver('google')->stateless()->userFromToken($request->id_token);

            Log::info('Google Sign-In', [
                'email' => $googleUser->getEmail(),
                'name' => $googleUser->getName(),
                'id' => $googleUser->getId(),
            ]);

            // Buscar usuario existente por google_id o email
            $user = User::where('google_id', $googleUser->getId())
                ->orWhere('email', $googleUser->getEmail())
                ->first();

            if ($user) {
                // Usuario existe - actualizar datos de Google si es necesario
                $user = $this->updateExistingUser($user, $googleUser);
            } else {
                // Nuevo usuario - crear cuenta
                $user = $this->createNewUser($googleUser, $request);
            }

            // Verificar si la cuenta está activa
            if (!$user->activo) {
                $this->logFailedAttempt($user, $request, 'Cuenta inactiva');
                return $this->error('Tu cuenta está inactiva. Contacta al administrador.', 403);
            }

            // Resetear intentos fallidos
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
                'subtipo_evento' => 'LOGIN_GOOGLE',
                'modulo' => 'Autenticación',
                'descripcion' => 'Inicio de sesión exitoso con Google',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'dispositivo' => $this->parseUserAgent($request->userAgent()),
            ]);

            // Cargar roles
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
                    'google_avatar' => $user->google_avatar,
                    'roles' => $roles->pluck('nombre'),
                    'force_password_change' => $user->force_password_change ?? false,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 'Inicio de sesión exitoso con Google');

        } catch (\Exception $e) {
            Log::error('Error en Google Sign-In: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Error al procesar autenticación con Google', 500);
        }
    }

    /**
     * Update existing user with Google data
     */
    private function updateExistingUser(User $user, $googleUser): User
    {
        // Actualizar google_id si no existe
        if (!$user->google_id) {
            $user->google_id = $googleUser->getId();
        }

        // Actualizar avatar de Google si está disponible
        if ($googleUser->getAvatar()) {
            $user->google_avatar = $googleUser->getAvatar();
        }

        // Google OAuth siempre verifica el email
        if ($googleUser->getEmail()) {
            $user->email_verified_at = $user->email_verified_at ?? now();
        }

        $user->save();

        return $user;
    }

    /**
     * Create new user from Google data
     */
    private function createNewUser($googleUser, Request $request): User
    {
        // Determinar rol según el email
        $isAdmin = strtolower($googleUser->getEmail()) === strtolower(self::ADMIN_EMAIL);
        $roleName = $isAdmin ? 'Administrador' : 'Operador';

        // Generar identificación única
        $identificacion = 'GGL-' . strtoupper(Str::random(10));

        // Separar nombre completo
        $nameParts = explode(' ', $googleUser->getName(), 2);
        $nombres = $nameParts[0] ?? 'Usuario';
        $apellidos = $nameParts[1] ?? 'Google';

        // Crear usuario
        $user = User::create([
            'identificacion' => $identificacion,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'email' => $googleUser->getEmail(),
            'google_id' => $googleUser->getId(),
            'google_avatar' => $googleUser->getAvatar(),
            'email_verified_at' => now(),
            'password' => Hash::make(Str::random(32)), // Contraseña aleatoria (no se usa con Google)
            'activo' => true,
            'force_password_change' => false,
            'password_expires_at' => Carbon::now()->addDays(365), // Largo plazo para Google auth
            'last_password_change' => Carbon::now(),
        ]);

        // Asignar rol
        $role = Role::where('nombre', $roleName)->first();
        if ($role) {
            $user->roles()->attach($role->id, [
                'asignado_por' => null, // Sistema
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
            'subtipo_evento' => 'REGISTRO_USUARIO_GOOGLE',
            'modulo' => 'Autenticación',
            'tabla' => 'users',
            'registro_id' => $user->id,
            'descripcion' => 'Registro de nuevo usuario mediante Google OAuth - Rol: ' . $roleName,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'dispositivo' => $this->parseUserAgent($request->userAgent()),
        ]);

        Log::info('Nuevo usuario creado con Google', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $roleName,
        ]);

        return $user;
    }

    /**
     * Log failed login attempt
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
            'descripcion' => 'Intento de inicio de sesión fallido con Google' . ($reason ? ": $reason" : ''),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'dispositivo' => $this->parseUserAgent($request->userAgent()),
        ]);
    }

    /**
     * Parse user agent
     */
    protected function parseUserAgent(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        if (preg_match('/\((.*?)\)/', $userAgent, $matches)) {
            return substr($matches[1], 0, 100);
        }

        return substr($userAgent, 0, 100);
    }
}
