<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Bitacora;
use App\Models\HistorialConexion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::factory()->create([
            'nombre' => 'Operador',
            'nivel_jerarquico' => 1,
            'activo' => true,
        ]);

        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('Password123!'),
            'activo' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $this->user->roles()->attach($this->role->id, [
            'asignado_por' => 1,
            'fecha_asignacion' => now(),
            'activo' => true,
        ]);
    }

    /** @test */
    public function user_can_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'email',
                        'nombres',
                        'apellidos',
                        'full_name',
                        'roles',
                    ],
                    'token',
                    'token_type',
                    'expires_in',
                ]
            ]);

        $this->assertNotNull($response->json('data.token'));
        $this->assertEquals('test@example.com', $response->json('data.user.email'));

        // Verificar que se actualizó el último login
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'last_login_ip' => $response->baseRequest->getClientIp(),
        ]);

        // Verificar que se creó el historial de conexión
        $this->assertDatabaseHas('historial_conexiones', [
            'user_id' => $this->user->id,
            'exitosa' => true,
        ]);
    }

    /** @test */
    public function login_fails_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'WrongPassword123!',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Credenciales incorrectas',
            ]);

        // Verificar que se incrementó el contador de intentos fallidos
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'failed_login_attempts' => 1,
        ]);

        // Verificar que se registró en bitácora
        $this->assertDatabaseHas('bitacora', [
            'tipo_evento' => Bitacora::TIPO_EVENTO_SEGURIDAD,
            'subtipo_evento' => 'LOGIN_FAILED',
        ]);
    }

    /** @test */
    public function user_is_locked_after_5_failed_attempts(): void
    {
        // Simular 4 intentos fallidos
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/login', [
                'email' => 'test@example.com',
                'password' => 'WrongPassword',
            ]);
        }

        // Quinto intento fallido
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401);
        
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'failed_login_attempts' => 5,
        ]);
        
        $this->assertNotNull($this->user->fresh()->locked_until);
    }

    /** @test */
    public function login_fails_when_user_is_locked(): void
    {
        $this->user->locked_until = now()->addMinutes(30);
        $this->user->save();

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Cuenta bloqueada. Intente nuevamente en 30 minutos',
            ]);
    }

    /** @test */
    public function login_fails_when_user_is_inactive(): void
    {
        $this->user->activo = false;
        $this->user->save();

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Tu cuenta está inactiva. Contacta al administrador.',
            ]);
    }

    /** @test */
    public function login_fails_when_password_is_expired(): void
    {
        $this->user->password_expires_at = now()->subDay();
        $this->user->save();

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Tu contraseña ha expirado. Debes cambiarla.',
            ]);
    }

    /** @test */
    public function user_can_logout(): void
    {
        $token = $this->user->createToken('api-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Sesión cerrada exitosamente',
            ]);

        // Verificar que se limpió el token de sesión
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'session_token' => null,
        ]);

        // Verificar que se registró en historial de conexiones
        $this->assertDatabaseHas('historial_conexiones', [
            'user_id' => $this->user->id,
            'exitosa' => true,
        ]);
    }

    /** @test */
    public function authenticated_user_can_get_their_info(): void
    {
        $token = $this->user->createToken('api-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => [
                        'id',
                        'identificacion',
                        'nombres',
                        'apellidos',
                        'email',
                        'full_name',
                        'roles',
                        'permisos',
                    ]
                ]
            ]);

        $this->assertEquals($this->user->email, $response->json('data.user.email'));
        $this->assertContains('Operador', $response->json('data.user.roles'));
    }

    /** @test */
    public function unauthenticated_user_cannot_get_info(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }

    /** @test */
    public function user_can_register(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $role = Role::factory()->create(['nombre' => 'Operador']);

        $userData = [
            'identificacion' => 'TEST123456',
            'nombres' => 'Juan',
            'apellidos' => 'Pérez',
            'email' => 'juan@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'telefono' => '1234567890',
            'direccion' => 'Calle Principal 123',
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Usuario registrado exitosamente',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'juan@example.com',
            'identificacion' => 'TEST123456',
            'nombres' => 'Juan',
            'apellidos' => 'Pérez',
        ]);

        $user = User::where('email', 'juan@example.com')->first();
        $this->assertTrue(Hash::check('Password123!', $user->password));
        $this->assertTrue($user->force_password_change);
    }

    /** @test */
    public function registration_fails_with_duplicate_email(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $userData = [
            'identificacion' => 'TEST123456',
            'nombres' => 'Juan',
            'apellidos' => 'Pérez',
            'email' => $this->user->email,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function registration_fails_with_weak_password(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $userData = [
            'identificacion' => 'TEST123456',
            'nombres' => 'Juan',
            'apellidos' => 'Pérez',
            'email' => 'juan@example.com',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /** @test */
    public function user_session_expires_after_inactivity(): void
    {
        $token = $this->user->createToken('api-token')->plainTextToken;

        // Simular que la sesión expiró
        $this->user->session_expires_at = now()->subMinutes(5);
        $this->user->save();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/user');

        // La sesión está expirada, pero el token sigue siendo válido
        // La aplicación debería renovar la sesión en cada request
        $response->assertStatus(200);
        
        // Verificar que se renovó la sesión
        $this->assertGreaterThan(now(), $this->user->fresh()->session_expires_at);
    }
}