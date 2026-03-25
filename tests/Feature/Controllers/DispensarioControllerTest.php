<?php

namespace Tests\Feature\Controllers;

use App\Models\Dispensario;
use App\Models\Instalacion;
use App\Models\User;
use App\Models\Manguera;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispensarioControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Instalacion $instalacion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->instalacion = Instalacion::factory()->create(['activo' => true]);
    }

    /** @test */
    public function can_list_dispensarios(): void
    {
        Dispensario::factory()->count(5)->create(['instalacion_id' => $this->instalacion->id]);

        $response = $this->getJson('/api/dispensarios');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'clave',
                            'descripcion',
                            'estado',
                            'activo',
                            'instalacion',
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
    public function can_filter_dispensarios_by_instalacion(): void
    {
        $instalacion2 = Instalacion::factory()->create();

        Dispensario::factory()->count(2)->create(['instalacion_id' => $this->instalacion->id]);
        Dispensario::factory()->count(3)->create(['instalacion_id' => $instalacion2->id]);

        $response = $this->getJson('/api/dispensarios?instalacion_id=' . $this->instalacion->id);

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.data'));
        $this->assertEquals($this->instalacion->id, $response->json('data.data.0.instalacion_id'));
    }

    /** @test */
    public function can_filter_dispensarios_by_estado(): void
    {
        Dispensario::factory()->create(['estado' => Dispensario::ESTADO_OPERATIVO]);
        Dispensario::factory()->create(['estado' => Dispensario::ESTADO_MANTENIMIENTO]);

        $response = $this->getJson('/api/dispensarios?estado=' . Dispensario::ESTADO_OPERATIVO);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals(Dispensario::ESTADO_OPERATIVO, $response->json('data.data.0.estado'));
    }

    /** @test */
    public function can_create_dispensario(): void
    {
        $dispensarioData = [
            'instalacion_id' => $this->instalacion->id,
            'clave' => 'DIS-001',
            'descripcion' => 'Dispensario principal',
            'modelo' => 'Modelo X200',
            'fabricante' => 'Fabricante SA',
            'estado' => Dispensario::ESTADO_OPERATIVO,
            'activo' => true,
        ];

        $response = $this->postJson('/api/dispensarios', $dispensarioData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Dispensario creado exitosamente',
            ]);

        $this->assertDatabaseHas('dispensarios', [
            'clave' => 'DIS-001',
            'descripcion' => 'Dispensario principal',
            'estado' => Dispensario::ESTADO_OPERATIVO,
        ]);
    }

    /** @test */
    public function create_dispensario_fails_with_duplicate_clave(): void
    {
        Dispensario::factory()->create(['clave' => 'DIS-001']);

        $dispensarioData = [
            'instalacion_id' => $this->instalacion->id,
            'clave' => 'DIS-001',
            'estado' => Dispensario::ESTADO_OPERATIVO,
        ];

        $response = $this->postJson('/api/dispensarios', $dispensarioData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['clave']);
    }

    /** @test */
    public function create_dispensario_fails_with_inactive_instalacion(): void
    {
        $instalacionInactiva = Instalacion::factory()->create(['activo' => false]);

        $dispensarioData = [
            'instalacion_id' => $instalacionInactiva->id,
            'clave' => 'DIS-001',
            'estado' => Dispensario::ESTADO_OPERATIVO,
        ];

        $response = $this->postJson('/api/dispensarios', $dispensarioData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'La instalación no está activa',
            ]);
    }

    /** @test */
    public function can_view_single_dispensario(): void
    {
        $dispensario = Dispensario::factory()->create([
            'instalacion_id' => $this->instalacion->id,
        ]);
        Manguera::factory()->count(3)->create(['dispensario_id' => $dispensario->id]);

        $response = $this->getJson("/api/dispensarios/{$dispensario->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'clave',
                    'descripcion',
                    'instalacion',
                    'mangueras',
                ]
            ]);

        $this->assertEquals($dispensario->id, $response->json('data.id'));
        $this->assertCount(3, $response->json('data.mangueras'));
    }

    /** @test */
    public function can_update_dispensario(): void
    {
        $dispensario = Dispensario::factory()->create([
            'descripcion' => 'Descripción original',
            'estado' => Dispensario::ESTADO_OPERATIVO,
        ]);

        $updateData = [
            'descripcion' => 'Descripción actualizada',
            'estado' => Dispensario::ESTADO_MANTENIMIENTO,
            'modelo' => 'Modelo Actualizado',
        ];

        $response = $this->putJson("/api/dispensarios/{$dispensario->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Dispensario actualizado exitosamente',
            ]);

        $this->assertDatabaseHas('dispensarios', [
            'id' => $dispensario->id,
            'descripcion' => 'Descripción actualizada',
            'estado' => Dispensario::ESTADO_MANTENIMIENTO,
        ]);
    }

    /** @test */
    public function can_get_dispensario_mangueras(): void
    {
        $dispensario = Dispensario::factory()->create();
        Manguera::factory()->count(4)->create(['dispensario_id' => $dispensario->id]);

        $response = $this->getJson("/api/dispensarios/{$dispensario->id}/mangueras");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'clave',
                            'estado',
                            'activo',
                            'medidor',
                        ]
                    ],
                    'current_page',
                    'last_page',
                    'total',
                ]
            ]);

        $this->assertCount(4, $response->json('data.data'));
    }

    /** @test */
    public function can_filter_mangueras_by_estado(): void
    {
        $dispensario = Dispensario::factory()->create();
        Manguera::factory()->create([
            'dispensario_id' => $dispensario->id,
            'estado' => 'OPERATIVO',
        ]);
        Manguera::factory()->create([
            'dispensario_id' => $dispensario->id,
            'estado' => 'MANTENIMIENTO',
        ]);

        $response = $this->getJson("/api/dispensarios/{$dispensario->id}/mangueras?estado=OPERATIVO");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('OPERATIVO', $response->json('data.data.0.estado'));
    }

    /** @test */
    public function can_verify_dispensario_status(): void
    {
        $dispensario = Dispensario::factory()->create([
            'estado' => Dispensario::ESTADO_OPERATIVO,
            'activo' => true,
        ]);
        Manguera::factory()->count(2)->create([
            'dispensario_id' => $dispensario->id,
            'estado' => 'OPERATIVO',
        ]);
        Manguera::factory()->create([
            'dispensario_id' => $dispensario->id,
            'estado' => 'FUERA_SERVICIO',
        ]);

        $response = $this->getJson("/api/dispensarios/{$dispensario->id}/verificar-estado");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'dispensario_id',
                    'clave',
                    'estado',
                    'activo',
                    'mangueras' => [
                        'total',
                        'operativas',
                        'en_falla',
                    ],
                    'alertas',
                    'fecha_verificacion',
                ]
            ]);

        $this->assertEquals($dispensario->id, $response->json('data.dispensario_id'));
        $this->assertEquals(3, $response->json('data.mangueras.total'));
        $this->assertEquals(2, $response->json('data.mangueras.operativas'));
        $this->assertEquals(1, $response->json('data.mangueras.en_falla'));
        $this->assertNotEmpty($response->json('data.alertas'));
    }

    /** @test */
    public function can_delete_dispensario(): void
    {
        $dispensario = Dispensario::factory()->create();

        $response = $this->deleteJson("/api/dispensarios/{$dispensario->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Dispensario eliminado exitosamente',
            ]);

        $this->assertSoftDeleted('dispensarios', [
            'id' => $dispensario->id,
        ]);
    }

    /** @test */
    public function cannot_delete_dispensario_with_active_mangueras(): void
    {
        $dispensario = Dispensario::factory()->create();
        Manguera::factory()->create([
            'dispensario_id' => $dispensario->id,
            'activo' => true,
        ]);

        $response = $this->deleteJson("/api/dispensarios/{$dispensario->id}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'No se puede eliminar el dispensario porque tiene 1 mangueras activas',
            ]);
    }
}