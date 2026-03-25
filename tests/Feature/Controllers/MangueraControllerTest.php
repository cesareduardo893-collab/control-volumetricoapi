<?php

namespace Tests\Feature\Controllers;

use App\Models\Manguera;
use App\Models\Dispensario;
use App\Models\Medidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MangueraControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Dispensario $dispensario;
    private Medidor $medidor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->dispensario = Dispensario::factory()->create(['activo' => true]);
        $this->medidor = Medidor::factory()->create(['activo' => true]);
    }

    /** @test */
    public function can_list_mangueras(): void
    {
        Manguera::factory()->count(5)->create(['dispensario_id' => $this->dispensario->id]);

        $response = $this->getJson('/api/mangueras');

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
                            'dispensario',
                            'medidor',
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
    public function can_filter_mangueras_by_dispensario(): void
    {
        $dispensario2 = Dispensario::factory()->create();

        Manguera::factory()->count(2)->create(['dispensario_id' => $this->dispensario->id]);
        Manguera::factory()->count(3)->create(['dispensario_id' => $dispensario2->id]);

        $response = $this->getJson('/api/mangueras?dispensario_id=' . $this->dispensario->id);

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.data'));
        $this->assertEquals($this->dispensario->id, $response->json('data.data.0.dispensario_id'));
    }

    /** @test */
    public function can_filter_mangueras_by_estado(): void
    {
        Manguera::factory()->create(['estado' => 'OPERATIVO']);
        Manguera::factory()->create(['estado' => 'MANTENIMIENTO']);

        $response = $this->getJson('/api/mangueras?estado=OPERATIVO');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('OPERATIVO', $response->json('data.data.0.estado'));
    }

    /** @test */
    public function can_create_manguera(): void
    {
        $mangueraData = [
            'dispensario_id' => $this->dispensario->id,
            'clave' => 'MANG-001',
            'descripcion' => 'Manguera principal',
            'medidor_id' => $this->medidor->id,
            'estado' => 'OPERATIVO',
            'activo' => true,
        ];

        $response = $this->postJson('/api/mangueras', $mangueraData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Manguera creada exitosamente',
            ]);

        $this->assertDatabaseHas('mangueras', [
            'clave' => 'MANG-001',
            'descripcion' => 'Manguera principal',
            'estado' => 'OPERATIVO',
        ]);
    }

    /** @test */
    public function create_manguera_fails_when_medidor_already_assigned(): void
    {
        // Asignar medidor a otra manguera
        Manguera::factory()->create([
            'medidor_id' => $this->medidor->id,
            'activo' => true,
        ]);

        $mangueraData = [
            'dispensario_id' => $this->dispensario->id,
            'clave' => 'MANG-001',
            'medidor_id' => $this->medidor->id,
            'estado' => 'OPERATIVO',
        ];

        $response = $this->postJson('/api/mangueras', $mangueraData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'El medidor ya está asignado a otra manguera',
            ]);
    }

    /** @test */
    public function can_view_single_manguera(): void
    {
        $manguera = Manguera::factory()->create([
            'dispensario_id' => $this->dispensario->id,
            'medidor_id' => $this->medidor->id,
        ]);

        $response = $this->getJson("/api/mangueras/{$manguera->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'clave',
                    'descripcion',
                    'dispensario',
                    'medidor',
                ]
            ]);

        $this->assertEquals($manguera->id, $response->json('data.id'));
        $this->assertEquals($manguera->clave, $response->json('data.clave'));
    }

    /** @test */
    public function can_update_manguera(): void
    {
        $manguera = Manguera::factory()->create([
            'descripcion' => 'Descripción original',
            'estado' => 'OPERATIVO',
        ]);

        $updateData = [
            'descripcion' => 'Descripción actualizada',
            'estado' => 'MANTENIMIENTO',
        ];

        $response = $this->putJson("/api/mangueras/{$manguera->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Manguera actualizada exitosamente',
            ]);

        $this->assertDatabaseHas('mangueras', [
            'id' => $manguera->id,
            'descripcion' => 'Descripción actualizada',
            'estado' => 'MANTENIMIENTO',
        ]);
    }

    /** @test */
    public function can_assign_medidor_to_manguera(): void
    {
        $manguera = Manguera::factory()->create(['medidor_id' => null]);
        $nuevoMedidor = Medidor::factory()->create();

        $response = $this->postJson("/api/mangueras/{$manguera->id}/asignar-medidor", [
            'medidor_id' => $nuevoMedidor->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Medidor asignado exitosamente',
            ]);

        $this->assertDatabaseHas('mangueras', [
            'id' => $manguera->id,
            'medidor_id' => $nuevoMedidor->id,
        ]);
    }

    /** @test */
    public function assign_medidor_fails_when_medidor_already_assigned(): void
    {
        $manguera1 = Manguera::factory()->create(['medidor_id' => $this->medidor->id]);
        $manguera2 = Manguera::factory()->create(['medidor_id' => null]);

        $response = $this->postJson("/api/mangueras/{$manguera2->id}/asignar-medidor", [
            'medidor_id' => $this->medidor->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'El medidor ya está asignado a otra manguera',
            ]);
    }

    /** @test */
    public function can_remove_medidor_from_manguera(): void
    {
        $manguera = Manguera::factory()->create(['medidor_id' => $this->medidor->id]);

        $response = $this->deleteJson("/api/mangueras/{$manguera->id}/quitar-medidor");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Medidor quitado exitosamente',
            ]);

        $this->assertDatabaseHas('mangueras', [
            'id' => $manguera->id,
            'medidor_id' => null,
        ]);
    }

    /** @test */
    public function can_delete_manguera(): void
    {
        $manguera = Manguera::factory()->create();

        $response = $this->deleteJson("/api/mangueras/{$manguera->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Manguera eliminada exitosamente',
            ]);

        $this->assertSoftDeleted('mangueras', [
            'id' => $manguera->id,
        ]);
    }

    /** @test */
    public function delete_manguera_updates_status(): void
    {
        $manguera = Manguera::factory()->create(['estado' => 'OPERATIVO']);

        $response = $this->deleteJson("/api/mangueras/{$manguera->id}");

        $response->assertStatus(200);

        $this->assertDatabaseHas('mangueras', [
            'id' => $manguera->id,
            'estado' => 'FUERA_SERVICIO',
            'activo' => false,
        ]);
    }
}