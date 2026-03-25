<?php

namespace Tests\Feature\Controllers;

use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use App\Models\Bitacora;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleControllerTest extends TestCase
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
    public function can_list_roles(): void
    {
        Role::factory()->count(5)->create();

        $response = $this->getJson('/api/roles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'nombre',
                            'descripcion',
                            'nivel_jerarquico',
                            'es_administrador',
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
    public function can_filter_roles_by_nombre(): void
    {
        Role::factory()->create(['nombre' => 'Administrador']);
        Role::factory()->create(['nombre' => 'Operador']);

        $response = $this->getJson('/api/roles?nombre=Administrador');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('Administrador', $response->json('data.data.0.nombre'));
    }

    /** @test */
    public function can_filter_roles_by_activo(): void
    {
        Role::factory()->create(['activo' => true]);
        Role::factory()->create(['activo' => false]);

        $response = $this->getJson('/api/roles?activo=true');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertTrue($response->json('data.data.0.activo'));
    }

    /** @test */
    public function can_filter_roles_by_es_administrador(): void
    {
        Role::factory()->create(['es_administrador' => true]);
        Role::factory()->create(['es_administrador' => false]);

        $response = $this->getJson('/api/roles?es_administrador=true');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertTrue($response->json('data.data.0.es_administrador'));
    }

    /** @test */
    public function can_filter_roles_by_nivel_minimo(): void
    {
        Role::factory()->create(['nivel_jerarquico' => 1]);
        Role::factory()->create(['nivel_jerarquico' => 5]);
        Role::factory()->create(['nivel_jerarquico' => 10]);

        $response = $this->getJson('/api/roles?nivel_minimo=5');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.data'));
        
        $niveles = array_column($response->json('data.data'), 'nivel_jerarquico');
        $this->assertContains(5, $niveles);
        $this->assertContains(10, $niveles);
    }

    /** @test */
    public function can_create_role(): void
    {
        $permission1 = Permission::factory()->create();
        $permission2 = Permission::factory()->create();

        $roleData = [
            'nombre' => 'Supervisor',
            'descripcion' => 'Rol de supervisión',
            'nivel_jerarquico' => 50,
            'es_administrador' => false,
            'activo' => true,
            'permisos' => [$permission1->id, $permission2->id],
            'restricciones_acceso' => ['horario' => '08:00-18:00'],
            'configuracion_ui' => ['tema' => 'oscuro'],
        ];

        $response = $this->postJson('/api/roles', $roleData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Rol creado exitosamente',
            ]);

        $this->assertDatabaseHas('roles', [
            'nombre' => 'Supervisor',
            'nivel_jerarquico' => 50,
        ]);

        // Verificar que se asignaron los permisos
        $role = Role::where('nombre', 'Supervisor')->first();
        $this->assertCount(2, $role->permissions);
        $this->assertTrue($role->permissions->contains($permission1));
        $this->assertTrue($role->permissions->contains($permission2));
    }

    /** @test */
    public function create_role_fails_with_duplicate_nombre(): void
    {
        Role::factory()->create(['nombre' => 'Supervisor']);

        $roleData = [
            'nombre' => 'Supervisor',
            'nivel_jerarquico' => 50,
        ];

        $response = $this->postJson('/api/roles', $roleData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nombre']);
    }

    /** @test */
    public function create_role_fails_with_invalid_nivel_jerarquico(): void
    {
        $roleData = [
            'nombre' => 'Rol Inválido',
            'nivel_jerarquico' => 200, // Mayor que 100
        ];

        $response = $this->postJson('/api/roles', $roleData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nivel_jerarquico']);
    }

    /** @test */
    public function can_view_single_role(): void
    {
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();
        $role->permissions()->attach($permission->id);

        // Asignar usuario al rol
        $user = User::factory()->create();
        $user->roles()->attach($role->id, [
            'asignado_por' => $this->user->id,
            'fecha_asignacion' => now(),
            'activo' => true,
        ]);

        $response = $this->getJson("/api/roles/{$role->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'nombre',
                    'descripcion',
                    'permissions',
                    'usuarios_activos',
                ]
            ]);

        $this->assertEquals($role->id, $response->json('data.id'));
        $this->assertEquals(1, $response->json('data.usuarios_activos'));
        $this->assertCount(1, $response->json('data.permissions'));
    }

    /** @test */
    public function can_update_role(): void
    {
        $role = Role::factory()->create([
            'nombre' => 'Nombre Original',
            'nivel_jerarquico' => 10,
        ]);

        $updateData = [
            'nombre' => 'Nombre Actualizado',
            'nivel_jerarquico' => 20,
            'descripcion' => 'Descripción actualizada',
            'activo' => false,
        ];

        $response = $this->putJson("/api/roles/{$role->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Rol actualizado exitosamente',
            ]);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'nombre' => 'Nombre Actualizado',
            'nivel_jerarquico' => 20,
            'activo' => false,
        ]);
    }

    /** @test */
    public function can_assign_permissions_to_role(): void
    {
        $role = Role::factory()->create();
        $permission1 = Permission::factory()->create();
        $permission2 = Permission::factory()->create();

        $response = $this->postJson("/api/roles/{$role->id}/permisos", [
            'permisos' => [$permission1->id, $permission2->id],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Permisos asignados exitosamente',
            ]);

        $this->assertCount(2, $role->fresh()->permissions);
    }

    /** @test */
    public function can_get_permission_matrix(): void
    {
        $role1 = Role::factory()->create(['nombre' => 'Admin', 'activo' => true]);
        $role2 = Role::factory()->create(['nombre' => 'Operador', 'activo' => true]);
        
        $permission1 = Permission::factory()->create(['modulo' => 'Usuarios', 'activo' => true]);
        $permission2 = Permission::factory()->create(['modulo' => 'Inventarios', 'activo' => true]);
        
        $role1->permissions()->attach($permission1->id);
        $role2->permissions()->attach($permission2->id);

        $response = $this->getJson('/api/roles/matriz-permisos');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'roles',
                    'permisos',
                    'matriz',
                ]
            ]);

        $this->assertCount(2, $response->json('data.roles'));
        $this->assertCount(2, $response->json('data.permisos'));
        $this->assertCount(2, $response->json('data.matriz'));
        
        // Verificar que la matriz contiene las asignaciones correctas
        $matriz = $response->json('data.matriz');
        $permisoAdmin = array_filter($matriz, function($item) use ($permission1) {
            return $item['permiso']['id'] == $permission1->id;
        });
        $this->assertTrue(reset($permisoAdmin)['roles'][$role1->id]);
    }

    /** @test */
    public function can_clone_role(): void
    {
        $roleOriginal = Role::factory()->create([
            'nombre' => 'Rol Original',
            'descripcion' => 'Descripción original',
            'nivel_jerarquico' => 15,
        ]);
        
        $permission = Permission::factory()->create();
        $roleOriginal->permissions()->attach($permission->id);

        $cloneData = [
            'nombre' => 'Rol Clonado',
            'incluir_permisos' => true,
        ];

        $response = $this->postJson("/api/roles/{$roleOriginal->id}/clonar", $cloneData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Rol clonado exitosamente',
            ]);

        $this->assertDatabaseHas('roles', [
            'nombre' => 'Rol Clonado',
            'descripcion' => 'Descripción original',
            'nivel_jerarquico' => 15,
            'es_administrador' => false,
        ]);

        $rolClonado = Role::where('nombre', 'Rol Clonado')->first();
        $this->assertCount(1, $rolClonado->permissions);
        $this->assertTrue($rolClonado->permissions->contains($permission));
    }

    /** @test */
    public function clone_role_without_permissions(): void
    {
        $roleOriginal = Role::factory()->create();
        $permission = Permission::factory()->create();
        $roleOriginal->permissions()->attach($permission->id);

        $cloneData = [
            'nombre' => 'Rol Sin Permisos',
            'incluir_permisos' => false,
        ];

        $response = $this->postJson("/api/roles/{$roleOriginal->id}/clonar", $cloneData);

        $response->assertStatus(201);

        $rolClonado = Role::where('nombre', 'Rol Sin Permisos')->first();
        $this->assertCount(0, $rolClonado->permissions);
    }

    /** @test */
    public function can_get_role_permissions(): void
    {
        $role = Role::factory()->create();
        $permission1 = Permission::factory()->create(['modulo' => 'Usuarios']);
        $permission2 = Permission::factory()->create(['modulo' => 'Usuarios']);
        $permission3 = Permission::factory()->create(['modulo' => 'Inventarios']);
        
        $role->permissions()->attach([$permission1->id, $permission2->id, $permission3->id]);

        $response = $this->getJson("/api/roles/{$role->id}/permisos");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'rol',
                    'permisos',
                    'agrupados',
                ]
            ]);

        $this->assertCount(3, $response->json('data.permisos'));
        $this->assertArrayHasKey('Usuarios', $response->json('data.agrupados'));
        $this->assertArrayHasKey('Inventarios', $response->json('data.agrupados'));
        $this->assertCount(2, $response->json('data.agrupados.Usuarios'));
        $this->assertCount(1, $response->json('data.agrupados.Inventarios'));
    }

    /** @test */
    public function can_delete_role(): void
    {
        $role = Role::factory()->create();

        $response = $this->deleteJson("/api/roles/{$role->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Rol eliminado exitosamente',
            ]);

        $this->assertSoftDeleted('roles', [
            'id' => $role->id,
        ]);
    }

    /** @test */
    public function cannot_delete_role_with_active_users(): void
    {
        $role = Role::factory()->create();
        
        $user = User::factory()->create();
        $user->roles()->attach($role->id, [
            'asignado_por' => $this->user->id,
            'fecha_asignacion' => now(),
            'activo' => true,
        ]);

        $response = $this->deleteJson("/api/roles/{$role->id}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'No se puede eliminar el rol porque tiene 1 usuarios asignados',
            ]);
    }

    /** @test */
    public function delete_role_updates_activo_status(): void
    {
        $role = Role::factory()->create(['activo' => true]);

        $response = $this->deleteJson("/api/roles/{$role->id}");

        $response->assertStatus(200);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'activo' => false,
        ]);
    }
}