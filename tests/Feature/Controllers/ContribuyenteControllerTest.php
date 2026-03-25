<?php

namespace Tests\Feature\Controllers;

use App\Models\Contribuyente;
use App\Models\User;
use App\Models\CatalogoValor;
use App\Models\Instalacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContribuyenteControllerTest extends TestCase
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
    public function can_list_contribuyentes(): void
    {
        Contribuyente::factory()->count(5)->create();

        $response = $this->getJson('/api/contribuyentes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'rfc',
                            'razon_social',
                            'activo',
                            'fecha_registro',
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
    public function can_filter_contribuyentes_by_rfc(): void
    {
        Contribuyente::factory()->create(['rfc' => 'ABC123456789']);
        Contribuyente::factory()->create(['rfc' => 'XYZ987654321']);

        $response = $this->getJson('/api/contribuyentes?rfc=ABC');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('ABC123456789', $response->json('data.data.0.rfc'));
    }

    /** @test */
    public function can_filter_contribuyentes_by_razon_social(): void
    {
        Contribuyente::factory()->create(['razon_social' => 'Empresa ABC S.A. de C.V.']);
        Contribuyente::factory()->create(['razon_social' => 'Corporación XYZ S.A.']);

        $response = $this->getJson('/api/contribuyentes?razon_social=ABC');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('Empresa ABC S.A. de C.V.', $response->json('data.data.0.razon_social'));
    }

    /** @test */
    public function can_filter_contribuyentes_by_active_status(): void
    {
        Contribuyente::factory()->create(['activo' => true]);
        Contribuyente::factory()->create(['activo' => false]);

        $response = $this->getJson('/api/contribuyentes?activo=true');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertTrue($response->json('data.data.0.activo'));
    }

    /** @test */
    public function can_filter_contribuyentes_by_numero_permiso(): void
    {
        Contribuyente::factory()->create(['numero_permiso' => 'PERM-001']);
        Contribuyente::factory()->create(['numero_permiso' => 'PERM-002']);

        $response = $this->getJson('/api/contribuyentes?numero_permiso=PERM-001');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('PERM-001', $response->json('data.data.0.numero_permiso'));
    }

    /** @test */
    public function can_create_contribuyente(): void
    {
        $caracterActua = CatalogoValor::factory()->create();

        $contribuyenteData = [
            'rfc' => 'ABC123456789',
            'razon_social' => 'Empresa de Prueba S.A.',
            'nombre_comercial' => 'Empresa Prueba',
            'regimen_fiscal' => '601 - General de Ley',
            'domicilio_fiscal' => 'Calle Principal 123',
            'codigo_postal' => '12345',
            'telefono' => '1234567890',
            'email' => 'contacto@empresa.com',
            'representante_legal' => 'Juan Pérez',
            'representante_rfc' => 'PEREJ123456',
            'caracter_actua_id' => $caracterActua->id,
            'numero_permiso' => 'PERM-001',
            'tipo_permiso' => 'Importación',
            'activo' => true,
        ];

        $response = $this->postJson('/api/contribuyentes', $contribuyenteData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Contribuyente creado exitosamente',
            ]);

        $this->assertDatabaseHas('contribuyentes', [
            'rfc' => 'ABC123456789',
            'razon_social' => 'Empresa de Prueba S.A.',
            'email' => 'contacto@empresa.com',
        ]);
    }

    /** @test */
    public function create_contribuyente_fails_with_duplicate_rfc(): void
    {
        Contribuyente::factory()->create(['rfc' => 'ABC123456789']);

        $contribuyenteData = [
            'rfc' => 'ABC123456789',
            'razon_social' => 'Otra Empresa S.A.',
            'regimen_fiscal' => '601 - General de Ley',
            'domicilio_fiscal' => 'Calle Principal 123',
            'codigo_postal' => '12345',
        ];

        $response = $this->postJson('/api/contribuyentes', $contribuyenteData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rfc']);
    }

    /** @test */
    public function create_contribuyente_fails_with_invalid_codigo_postal(): void
    {
        $contribuyenteData = [
            'rfc' => 'ABC123456789',
            'razon_social' => 'Empresa de Prueba S.A.',
            'regimen_fiscal' => '601 - General de Ley',
            'domicilio_fiscal' => 'Calle Principal 123',
            'codigo_postal' => '1234', // Código postal de 5 dígitos requerido
        ];

        $response = $this->postJson('/api/contribuyentes', $contribuyenteData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['codigo_postal']);
    }

    /** @test */
    public function can_view_single_contribuyente(): void
    {
        $contribuyente = Contribuyente::factory()->create();
        Instalacion::factory()->count(2)->create(['contribuyente_id' => $contribuyente->id]);

        $response = $this->getJson("/api/contribuyentes/{$contribuyente->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'rfc',
                    'razon_social',
                    'instalaciones',
                    'dictamenes',
                    'certificadosVerificacion',
                    'pedimentos',
                ]
            ]);

        $this->assertEquals($contribuyente->id, $response->json('data.id'));
        $this->assertCount(2, $response->json('data.instalaciones'));
    }

    /** @test */
    public function can_update_contribuyente(): void
    {
        $contribuyente = Contribuyente::factory()->create([
            'razon_social' => 'Nombre Original',
            'telefono' => '1111111111',
        ]);

        $updateData = [
            'razon_social' => 'Nombre Actualizado',
            'telefono' => '9999999999',
            'activo' => false,
        ];

        $response = $this->putJson("/api/contribuyentes/{$contribuyente->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Contribuyente actualizado exitosamente',
            ]);

        $this->assertDatabaseHas('contribuyentes', [
            'id' => $contribuyente->id,
            'razon_social' => 'Nombre Actualizado',
            'telefono' => '9999999999',
            'activo' => false,
        ]);
    }

    /** @test */
    public function can_get_contribuyente_instalaciones(): void
    {
        $contribuyente = Contribuyente::factory()->create();
        Instalacion::factory()->count(3)->create(['contribuyente_id' => $contribuyente->id]);

        $response = $this->getJson("/api/contribuyentes/{$contribuyente->id}/instalaciones");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'clave_instalacion',
                            'nombre',
                            'estatus',
                        ]
                    ],
                    'current_page',
                    'last_page',
                    'total',
                ]
            ]);

        $this->assertCount(3, $response->json('data.data'));
    }

    /** @test */
    public function can_filter_contribuyente_instalaciones_by_status(): void
    {
        $contribuyente = Contribuyente::factory()->create();
        Instalacion::factory()->create([
            'contribuyente_id' => $contribuyente->id,
            'estatus' => 'OPERACION',
        ]);
        Instalacion::factory()->create([
            'contribuyente_id' => $contribuyente->id,
            'estatus' => 'SUSPENDIDA',
        ]);

        $response = $this->getJson("/api/contribuyentes/{$contribuyente->id}/instalaciones?estatus=OPERACION");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('OPERACION', $response->json('data.data.0.estatus'));
    }

    /** @test */
    public function can_get_compliance_summary(): void
    {
        $contribuyente = Contribuyente::factory()->create();
        $instalacion = Instalacion::factory()->create(['contribuyente_id' => $contribuyente->id]);

        $response = $this->getJson("/api/contribuyentes/{$contribuyente->id}/cumplimiento");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'contribuyente' => [
                        'id',
                        'rfc',
                        'razon_social',
                    ],
                    'certificados',
                    'instalaciones',
                    'tanques',
                    'medidores',
                    'fecha_consulta',
                ]
            ]);

        $this->assertEquals($contribuyente->id, $response->json('data.contribuyente.id'));
        $this->assertEquals(1, $response->json('data.instalaciones.total'));
    }

    /** @test */
    public function can_get_catalogo_contribuyentes(): void
    {
        Contribuyente::factory()->count(3)->create(['activo' => true]);
        Contribuyente::factory()->create(['activo' => false]);

        $response = $this->getJson('/api/contribuyentes/catalogo');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Catálogo de contribuyentes obtenido exitosamente',
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function can_delete_contribuyente(): void
    {
        $contribuyente = Contribuyente::factory()->create();

        $response = $this->deleteJson("/api/contribuyentes/{$contribuyente->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Contribuyente eliminado exitosamente',
            ]);

        $this->assertSoftDeleted('contribuyentes', [
            'id' => $contribuyente->id,
        ]);
    }

    /** @test */
    public function cannot_delete_contribuyente_with_active_installations(): void
    {
        $contribuyente = Contribuyente::factory()->create();
        Instalacion::factory()->create([
            'contribuyente_id' => $contribuyente->id,
            'estatus' => 'OPERACION',
        ]);

        $response = $this->deleteJson("/api/contribuyentes/{$contribuyente->id}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'No se puede eliminar el contribuyente porque tiene 1 instalaciones activas',
            ]);
    }

    /** @test */
    public function can_update_contribuyente_verification_dates(): void
    {
        $contribuyente = Contribuyente::factory()->create([
            'ultima_verificacion' => null,
            'proxima_verificacion' => null,
        ]);

        $updateData = [
            'ultima_verificacion' => '2024-01-01',
            'proxima_verificacion' => '2024-12-31',
            'estatus_verificacion' => 'EN_PROCESO',
        ];

        $response = $this->putJson("/api/contribuyentes/{$contribuyente->id}", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('contribuyentes', [
            'id' => $contribuyente->id,
            'ultima_verificacion' => '2024-01-01',
            'proxima_verificacion' => '2024-12-31',
            'estatus_verificacion' => 'EN_PROCESO',
        ]);
    }
}