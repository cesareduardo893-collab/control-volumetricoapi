<?php

namespace Tests\Feature\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Bitacora;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** @test */
    public function can_list_permissions(): void
    {
        Permission::factory()->count(5)->create();

        $response = $this->getJson('/api/permisos');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'slug',
                            'modulo',
                            'activo',
                        ]
                    ],
                    'current_page',
                    'last_page',
                    'total',
                ]
            ]);

        $this->assertCount(5, $response->json('data.data'));
    }

    /** @test */
    public function can_filter_permissions_by_modulo(): void
    {
        Permission::factory()->create(['modulo' => 'Usuarios']);
        Permission::factory()->create(['modulo' => 'Inventarios']);

        $response = $this->getJson('/api/permisos?modulo=Usuarios');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('Usuarios', $response->json('data.data.0.modulo'));
    }

    /** @test */
    public function can_filter_permissions_by_name(): void
    {
        Permission::factory()->create(['name' => 'Crear Usuario']);
        Permission::factory()->create(['name' => 'Editar Usuario']);

        $response = $this->getJson('/api/permisos?name=Crear');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('Crear Usuario', $response->json('data.data.0.name'));
    }

    /** @test */
    public function can_create_permission(): void
    {
        $permissionData = [
            'name' => 'Ver Dashboard',
            'slug' => 'ver-dashboard',
            'description' => 'Permite ver el dashboard',
            'modulo' => 'Dashboard',
            'reglas' => ['permiso' => 'lectura'],
            'activo' => true,
        ];

        $response = $this->postJson('/api/permisos', $permissionData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Permiso creado exitosamente',
            ]);

        $this->assertDatabaseHas('permissions', [
            'name' => 'Ver Dashboard',
            'slug' => 'ver-dashboard',
            'modulo' => 'Dashboard',
        ]);
    }

    /** @test */
    public function create_permission_fails_with_duplicate_slug(): void
    {
        Permission::factory()->create(['slug' => 'ver-dashboard']);

        $permissionData = [
            'name' => 'Ver Dashboard',
            'slug' => 'ver-dashboard',
            'modulo' => 'Dashboard',
        ];

        $response = $this->postJson('/api/permisos', $permissionData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    /** @test */
    public function can_view_single_permission(): void
    {
        $permission = Permission::factory()->create();
        $role = Role::factory()->create();
        $role->permissions()->attach($permission->id);

        $response = $this->getJson("/api/permisos/{$permission->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'modulo',
                    'roles',
                ]
            ]);

        $this->assertEquals($permission->id, $response->json('data.id'));
        $this->assertCount(1, $response->json('data.roles'));
    }

    /** @test */
    public function can_update_permission(): void
    {
        $permission = Permission::factory()->create([
            'name' => 'Nombre Original',
            'description' => 'Descripción original',
        ]);

        $updateData = [
            'name' => 'Nombre Actualizado',
            'description' => 'Descripción actualizada',
            'activo' => false,
        ];

        $response = $this->putJson("/api/permisos/{$permission->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Permiso actualizado exitosamente',
            ]);

        $this->assertDatabaseHas('permissions', [
            'id' => $permission->id,
            'name' => 'Nombre Actualizado',
            'description' => 'Descripción actualizada',
            'activo' => false,
        ]);
    }

    /** @test */
    public function can_get_permissions_by_module(): void
    {
        Permission::factory()->count(3)->create(['modulo' => 'Usuarios', 'activo' => true]);
        Permission::factory()->count(2)->create(['modulo' => 'Inventarios', 'activo' => true]);
        Permission::factory()->create(['modulo' => 'Usuarios', 'activo' => false]);

        $response = $this->getJson('/api/permisos/por-modulo');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'modulo',
                        'permisos',
                    ]
                ]
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data); // Solo módulos con permisos activos
        
        $moduloUsuarios = collect($data)->firstWhere('modulo', 'Usuarios');
        $this->assertCount(3, $moduloUsuarios['permisos']);
        
        $moduloInventarios = collect($data)->firstWhere('modulo', 'Inventarios');
        $this->assertCount(2, $moduloInventarios['permisos']);
    }

    /** @test */
    public function can_sync_permissions(): void
    {
        // Esta prueba solo debe ejecutarse en entorno local
        if (!app()->environment('local', 'development')) {
            $this->markTestSkipped('Solo ejecutable en entorno local/development');
        }

        $permisosData = [
            [
                'name' => 'Nuevo Permiso 1',
                'slug' => 'nuevo-permiso-1',
                'modulo' => 'Dashboard',
                'description' => 'Descripción 1',
            ],
            [
                'name' => 'Nuevo Permiso 2',
                'slug' => 'nuevo-permiso-2',
                'modulo' => 'Dashboard',
                'description' => 'Descripción 2',
            ],
        ];

        $response = $this->postJson('/api/permisos/sincronizar', [
            'permisos' => $permisosData,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'creados',
                    'actualizados',
                    'errores',
                ]
            ]);

        $this->assertCount(2, $response->json('data.creados'));
        $this->assertDatabaseHas('permissions', [
            'slug' => 'nuevo-permiso-1',
        ]);
        $this->assertDatabaseHas('permissions', [
            'slug' => 'nuevo-permiso-2',
        ]);
    }

    /** @test */
    public function sync_permissions_updates_existing(): void
    {
        if (!app()->environment('local', 'development')) {
            $this->markTestSkipped('Solo ejecutable en entorno local/development');
        }

        $existingPermission = Permission::factory()->create([
            'slug' => 'permiso-existente',
            'name' => 'Nombre Antiguo',
            'modulo' => 'Modulo Antiguo',
        ]);

        $permisosData = [
            [
                'name' => 'Nombre Nuevo',
                'slug' => 'permiso-existente',
                'modulo' => 'Modulo Nuevo',
                'description' => 'Descripción nueva',
            ],
        ];

        $response = $this->postJson('/api/permisos/sincronizar', [
            'permisos' => $permisosData,
        ]);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.actualizados'));

        $this->assertDatabaseHas('permissions', [
            'id' => $existingPermission->id,
            'name' => 'Nombre Nuevo',
            'modulo' => 'Modulo Nuevo',
        ]);
    }

    /** @test */
    public function can_verify_permission(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $permission = Permission::factory()->create(['slug' => 'ver-usuarios']);

        $user->roles()->attach($role->id, [
            'asignado_por' => $this->user->id,
            'fecha_asignacion' => now(),
            'activo' => true,
        ]);
        
        $role->permissions()->attach($permission->id);

        $response = $this->postJson('/api/permisos/verificar', [
            'user_id' => $user->id,
            'permiso_slug' => 'ver-usuarios',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user_id' => $user->id,
                    'permiso' => $permission->name,
                    'tiene_permiso' => true,
                ]
            ]);

        $this->assertNotEmpty($response->json('data.via_roles'));
    }

    /** @test */
    public function verify_permission_returns_false_when_no_permission(): void
    {
        $user = User::factory()->create();
        Permission::factory()->create(['slug' => 'permiso-inexistente']);

        $response = $this->postJson('/api/permisos/verificar', [
            'user_id' => $user->id,
            'permiso_slug' => 'permiso-inexistente',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user_id' => $user->id,
                    'tiene_permiso' => false,
                ]
            ]);
    }

    /** @test */
    public function verify_permission_fails_with_invalid_user(): void
    {
        $response = $this->postJson('/api/permisos/verificar', [
            'user_id' => 99999,
            'permiso_slug' => 'ver-usuarios',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    /** @test */
    public function can_delete_permission(): void
    {
        $permission = Permission::factory()->create();

        $response = $this->deleteJson("/api/permisos/{$permission->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Permiso eliminado exitosamente',
            ]);

        $this->assertSoftDeleted('permissions', [
            'id' => $permission->id,
        ]);
    }

    /** @test */
    public function cannot_delete_permission_assigned_to_roles(): void
    {
        $permission = Permission::factory()->create();
        $role = Role::factory()->create();
        $role->permissions()->attach($permission->id);

        $response = $this->deleteJson("/api/permisos/{$permission->id}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'No se puede eliminar el permiso porque está asignado a 1 roles',
            ]);
    }

    /** @test */
    public function delete_permission_updates_activo_status(): void
    {
        $permission = Permission::factory()->create(['activo' => true]);

        $response = $this->deleteJson("/api/permisos/{$permission->id}");

        $response->assertStatus(200);

        $this->assertDatabaseHas('permissions', [
            'id' => $permission->id,
            'activo' => false,
        ]);
    }
}