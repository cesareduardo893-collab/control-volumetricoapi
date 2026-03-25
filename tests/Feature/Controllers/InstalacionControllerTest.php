<?php

namespace Tests\Feature\Controllers;

use App\Models\Instalacion;
use App\Models\Contribuyente;
use App\Models\User;
use App\Models\Tanque;
use App\Models\Medidor;
use App\Models\Dispensario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstalacionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Contribuyente $contribuyente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->contribuyente = Contribuyente::factory()->create(['activo' => true]);
    }

    /** @test */
    public function can_list_instalaciones(): void
    {
        Instalacion::factory()->count(5)->create(['contribuyente_id' => $this->contribuyente->id]);

        $response = $this->getJson('/api/instalaciones');

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
                            'activo',
                            'contribuyente',
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
    public function can_filter_instalaciones_by_contribuyente(): void
    {
        $contribuyente2 = Contribuyente::factory()->create();

        Instalacion::factory()->count(2)->create(['contribuyente_id' => $this->contribuyente->id]);
        Instalacion::factory()->count(3)->create(['contribuyente_id' => $contribuyente2->id]);

        $response = $this->getJson('/api/instalaciones?contribuyente_id=' . $this->contribuyente->id);

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.data'));
        $this->assertEquals($this->contribuyente->id, $response->json('data.data.0.contribuyente_id'));
    }

    /** @test */
    public function can_filter_instalaciones_by_estatus(): void
    {
        Instalacion::factory()->create(['estatus' => 'OPERACION']);
        Instalacion::factory()->create(['estatus' => 'SUSPENDIDA']);

        $response = $this->getJson('/api/instalaciones?estatus=OPERACION');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('OPERACION', $response->json('data.data.0.estatus'));
    }

    /** @test */
    public function can_filter_instalaciones_by_municipio(): void
    {
        Instalacion::factory()->create(['municipio' => 'Guadalajara']);
        Instalacion::factory()->create(['municipio' => 'Zapopan']);

        $response = $this->getJson('/api/instalaciones?municipio=Guadalajara');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('Guadalajara', $response->json('data.data.0.municipio'));
    }

    /** @test */
    public function can_create_instalacion(): void
    {
        $instalacionData = [
            'contribuyente_id' => $this->contribuyente->id,
            'clave_instalacion' => 'INST-001',
            'nombre' => 'Estación de Servicio Central',
            'tipo_instalacion' => 'Gasolinera',
            'domicilio' => 'Av. Principal 123',
            'codigo_postal' => '44100',
            'municipio' => 'Guadalajara',
            'estado' => 'Jalisco',
            'latitud' => 20.659,
            'longitud' => -103.349,
            'telefono' => '3312345678',
            'responsable' => 'Juan Pérez',
            'fecha_operacion' => '2024-01-01',
            'estatus' => 'OPERACION',
            'activo' => true,
        ];

        $response = $this->postJson('/api/instalaciones', $instalacionData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Instalación creada exitosamente',
            ]);

        $this->assertDatabaseHas('instalaciones', [
            'clave_instalacion' => 'INST-001',
            'nombre' => 'Estación de Servicio Central',
            'estatus' => 'OPERACION',
        ]);
    }

    /** @test */
    public function create_instalacion_fails_with_duplicate_clave(): void
    {
        Instalacion::factory()->create(['clave_instalacion' => 'INST-001']);

        $instalacionData = [
            'contribuyente_id' => $this->contribuyente->id,
            'clave_instalacion' => 'INST-001',
            'nombre' => 'Otra Estación',
            'tipo_instalacion' => 'Gasolinera',
            'domicilio' => 'Otra Calle 456',
            'codigo_postal' => '44100',
            'municipio' => 'Guadalajara',
            'estado' => 'Jalisco',
            'estatus' => 'OPERACION',
        ];

        $response = $this->postJson('/api/instalaciones', $instalacionData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['clave_instalacion']);
    }

    /** @test */
    public function create_instalacion_fails_with_inactive_contribuyente(): void
    {
        $contribuyenteInactivo = Contribuyente::factory()->create(['activo' => false]);

        $instalacionData = [
            'contribuyente_id' => $contribuyenteInactivo->id,
            'clave_instalacion' => 'INST-001',
            'nombre' => 'Estación de Servicio',
            'tipo_instalacion' => 'Gasolinera',
            'domicilio' => 'Av. Principal 123',
            'codigo_postal' => '44100',
            'municipio' => 'Guadalajara',
            'estado' => 'Jalisco',
            'estatus' => 'OPERACION',
        ];

        $response = $this->postJson('/api/instalaciones', $instalacionData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'El contribuyente no está activo',
            ]);
    }

    /** @test */
    public function can_view_single_instalacion(): void
    {
        $instalacion = Instalacion::factory()->create(['contribuyente_id' => $this->contribuyente->id]);
        Tanque::factory()->count(2)->create(['instalacion_id' => $instalacion->id]);
        Medidor::factory()->count(3)->create(['instalacion_id' => $instalacion->id]);
        Dispensario::factory()->count(2)->create(['instalacion_id' => $instalacion->id]);

        $response = $this->getJson("/api/instalaciones/{$instalacion->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'clave_instalacion',
                    'nombre',
                    'contribuyente',
                    'tanques',
                    'medidores',
                    'dispensarios',
                    'estadisticas',
                ]
            ]);

        $this->assertEquals($instalacion->id, $response->json('data.id'));
        $this->assertCount(2, $response->json('data.tanques'));
        $this->assertCount(3, $response->json('data.medidores'));
        $this->assertCount(2, $response->json('data.dispensarios'));
    }

    /** @test */
    public function can_update_instalacion(): void
    {
        $instalacion = Instalacion::factory()->create([
            'nombre' => 'Nombre Original',
            'telefono' => '1111111111',
        ]);

        $updateData = [
            'nombre' => 'Nombre Actualizado',
            'telefono' => '9999999999',
            'estatus' => 'SUSPENDIDA',
            'observaciones' => 'En mantenimiento',
        ];

        $response = $this->putJson("/api/instalaciones/{$instalacion->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Instalación actualizada exitosamente',
            ]);

        $this->assertDatabaseHas('instalaciones', [
            'id' => $instalacion->id,
            'nombre' => 'Nombre Actualizado',
            'telefono' => '9999999999',
            'estatus' => 'SUSPENDIDA',
        ]);
    }

    /** @test */
    public function can_update_instalacion_with_monitoring_config(): void
    {
        $instalacion = Instalacion::factory()->create();

        $updateData = [
            'configuracion_monitoreo' => [
                'frecuencia' => 5,
                'unidad' => 'minutos',
                'alertas' => ['temperatura', 'presion'],
            ],
            'parametros_volumetricos' => [
                'factor_correccion' => 0.998,
                'temperatura_referencia' => 20,
            ],
            'umbrales_alarma' => [
                'temperatura_min' => -10,
                'temperatura_max' => 50,
                'presion_min' => 0,
                'presion_max' => 100,
            ],
        ];

        $response = $this->putJson("/api/instalaciones/{$instalacion->id}", $updateData);

        $response->assertStatus(200);

        $instalacionActualizada = Instalacion::find($instalacion->id);
        $this->assertEquals(5, $instalacionActualizada->configuracion_monitoreo['frecuencia']);
        $this->assertEquals(0.998, $instalacionActualizada->parametros_volumetricos['factor_correccion']);
        $this->assertEquals(50, $instalacionActualizada->umbrales_alarma['temperatura_max']);
    }

    /** @test */
    public function can_get_instalacion_tanques(): void
    {
        $instalacion = Instalacion::factory()->create();
        Tanque::factory()->count(4)->create(['instalacion_id' => $instalacion->id]);

        $response = $this->getJson("/api/instalaciones/{$instalacion->id}/tanques");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'identificador',
                            'capacidad_operativa',
                            'estado',
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
    public function can_get_instalacion_medidores(): void
    {
        $instalacion = Instalacion::factory()->create();
        Medidor::factory()->count(3)->create(['instalacion_id' => $instalacion->id]);

        $response = $this->getJson("/api/instalaciones/{$instalacion->id}/medidores");

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data.data'));
    }

    /** @test */
    public function can_filter_medidores_by_calibracion_proxima(): void
    {
        $instalacion = Instalacion::factory()->create();
        Medidor::factory()->create([
            'instalacion_id' => $instalacion->id,
            'fecha_proxima_calibracion' => now()->addDays(15),
        ]);
        Medidor::factory()->create([
            'instalacion_id' => $instalacion->id,
            'fecha_proxima_calibracion' => now()->addDays(60),
        ]);

        $response = $this->getJson("/api/instalaciones/{$instalacion->id}/medidores?calibracion_proxima=true");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
    }

    /** @test */
    public function can_get_instalacion_dispensarios(): void
    {
        $instalacion = Instalacion::factory()->create();
        Dispensario::factory()->count(2)->create(['instalacion_id' => $instalacion->id]);

        $response = $this->getJson("/api/instalaciones/{$instalacion->id}/dispensarios");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.data'));
    }

    /** @test */
    public function can_get_operational_summary(): void
    {
        $instalacion = Instalacion::factory()->create();
        Tanque::factory()->count(3)->create([
            'instalacion_id' => $instalacion->id,
            'estado' => 'OPERATIVO',
            'capacidad_total' => 10000,
            'capacidad_operativa' => 9000,
        ]);
        Medidor::factory()->count(2)->create([
            'instalacion_id' => $instalacion->id,
            'estado' => 'OPERATIVO',
            'fecha_proxima_calibracion' => now()->addDays(15),
        ]);

        $response = $this->getJson("/api/instalaciones/{$instalacion->id}/resumen-operativo");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'instalacion',
                    'tanques',
                    'medidores',
                    'dispensarios',
                    'actividad_reciente',
                    'fecha_consulta',
                ]
            ]);

        $this->assertEquals(3, $response->json('data.tanques.total'));
        $this->assertEquals(3, $response->json('data.tanques.operativos'));
        $this->assertEquals(30000, $response->json('data.tanques.capacidad_total'));
    }

    /** @test */
    public function can_delete_instalacion(): void
    {
        $instalacion = Instalacion::factory()->create();

        $response = $this->deleteJson("/api/instalaciones/{$instalacion->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Instalación eliminada exitosamente',
            ]);

        $this->assertSoftDeleted('instalaciones', [
            'id' => $instalacion->id,
        ]);
    }

    /** @test */
    public function cannot_delete_instalacion_with_active_tanques(): void
    {
        $instalacion = Instalacion::factory()->create();
        Tanque::factory()->create([
            'instalacion_id' => $instalacion->id,
            'activo' => true,
        ]);

        $response = $this->deleteJson("/api/instalaciones/{$instalacion->id}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'No se puede eliminar la instalación porque tiene 1 tanques activos',
            ]);
    }

    /** @test */
    public function cannot_delete_instalacion_with_active_medidores(): void
    {
        $instalacion = Instalacion::factory()->create();
        Medidor::factory()->create([
            'instalacion_id' => $instalacion->id,
            'activo' => true,
        ]);

        $response = $this->deleteJson("/api/instalaciones/{$instalacion->id}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'No se puede eliminar la instalación porque tiene 1 medidores activos',
            ]);
    }

    /** @test */
    public function delete_instalacion_changes_status_to_cancelada(): void
    {
        $instalacion = Instalacion::factory()->create(['estatus' => 'OPERACION']);

        $response = $this->deleteJson("/api/instalaciones/{$instalacion->id}");

        $response->assertStatus(200);

        $this->assertDatabaseHas('instalaciones', [
            'id' => $instalacion->id,
            'estatus' => 'CANCELADA',
            'activo' => false,
        ]);
    }
}