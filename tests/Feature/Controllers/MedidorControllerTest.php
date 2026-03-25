<?php

namespace Tests\Feature\Controllers;

use App\Models\Medidor;
use App\Models\Instalacion;
use App\Models\Tanque;
use App\Models\User;
use App\Models\CatalogoValor;
use App\Models\HistorialCalibracionMedidor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedidorControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Instalacion $instalacion;
    private Tanque $tanque;
    private CatalogoValor $tecnologia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->instalacion = Instalacion::factory()->create(['activo' => true]);
        $this->tanque = Tanque::factory()->create([
            'instalacion_id' => $this->instalacion->id,
            'activo' => true,
        ]);
        $this->tecnologia = CatalogoValor::factory()->create();
    }

    /** @test */
    public function can_list_medidores(): void
    {
        Medidor::factory()->count(5)->create(['instalacion_id' => $this->instalacion->id]);

        $response = $this->getJson('/api/medidores');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'numero_serie',
                            'clave',
                            'estado',
                            'activo',
                            'instalacion',
                            'tanque',
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
    public function can_filter_medidores_by_instalacion(): void
    {
        $instalacion2 = Instalacion::factory()->create();

        Medidor::factory()->count(2)->create(['instalacion_id' => $this->instalacion->id]);
        Medidor::factory()->count(3)->create(['instalacion_id' => $instalacion2->id]);

        $response = $this->getJson('/api/medidores?instalacion_id=' . $this->instalacion->id);

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.data'));
        $this->assertEquals($this->instalacion->id, $response->json('data.data.0.instalacion_id'));
    }

    /** @test */
    public function can_filter_medidores_by_tipo_medicion(): void
    {
        Medidor::factory()->create(['tipo_medicion' => Medidor::TIPO_MEDICION_ESTATICA]);
        Medidor::factory()->create(['tipo_medicion' => Medidor::TIPO_MEDICION_DINAMICA]);

        $response = $this->getJson('/api/medidores?tipo_medicion=' . Medidor::TIPO_MEDICION_ESTATICA);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals(Medidor::TIPO_MEDICION_ESTATICA, $response->json('data.data.0.tipo_medicion'));
    }

    /** @test */
    public function can_filter_medidores_by_estado(): void
    {
        Medidor::factory()->create(['estado' => Medidor::ESTADO_OPERATIVO]);
        Medidor::factory()->create(['estado' => Medidor::ESTADO_CALIBRACION]);

        $response = $this->getJson('/api/medidores?estado=' . Medidor::ESTADO_OPERATIVO);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals(Medidor::ESTADO_OPERATIVO, $response->json('data.data.0.estado'));
    }

    /** @test */
    public function can_filter_medidores_by_protocolo_comunicacion(): void
    {
        Medidor::factory()->create(['protocolo_comunicacion' => 'modbus']);
        Medidor::factory()->create(['protocolo_comunicacion' => 'opc']);

        $response = $this->getJson('/api/medidores?protocolo_comunicacion=modbus');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('modbus', $response->json('data.data.0.protocolo_comunicacion'));
    }

    /** @test */
    public function can_create_medidor(): void
    {
        $medidorData = [
            'instalacion_id' => $this->instalacion->id,
            'tanque_id' => $this->tanque->id,
            'numero_serie' => 'MD-12345',
            'clave' => 'MED-001',
            'modelo' => 'Modelo X100',
            'fabricante' => 'Fabricante SA',
            'elemento_tipo' => Medidor::ELEMENTO_TIPO_PRIMARIO,
            'tipo_medicion' => Medidor::TIPO_MEDICION_DINAMICA,
            'tecnologia_id' => $this->tecnologia->id,
            'precision' => 0.5,
            'repetibilidad' => 0.1,
            'capacidad_maxima' => 1000,
            'capacidad_minima' => 10,
            'fecha_instalacion' => '2024-01-01',
            'ubicacion_fisica' => 'Área de bombas',
            'protocolo_comunicacion' => 'modbus',
            'direccion_ip' => '192.168.1.100',
            'puerto_comunicacion' => 502,
            'estado' => Medidor::ESTADO_OPERATIVO,
            'observaciones' => 'Medidor principal',
            'activo' => true,
        ];

        $response = $this->postJson('/api/medidores', $medidorData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Medidor creado exitosamente',
            ]);

        $this->assertDatabaseHas('medidores', [
            'numero_serie' => 'MD-12345',
            'clave' => 'MED-001',
            'precision' => 0.5,
        ]);
    }

    /** @test */
    public function create_medidor_fails_with_duplicate_numero_serie(): void
    {
        Medidor::factory()->create(['numero_serie' => 'MD-12345']);

        $medidorData = [
            'instalacion_id' => $this->instalacion->id,
            'numero_serie' => 'MD-12345',
            'clave' => 'MED-002',
            'elemento_tipo' => Medidor::ELEMENTO_TIPO_PRIMARIO,
            'tipo_medicion' => Medidor::TIPO_MEDICION_ESTATICA,
            'precision' => 0.5,
            'capacidad_maxima' => 1000,
            'estado' => Medidor::ESTADO_OPERATIVO,
        ];

        $response = $this->postJson('/api/medidores', $medidorData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['numero_serie']);
    }

    /** @test */
    public function create_medidor_fails_with_duplicate_clave(): void
    {
        Medidor::factory()->create(['clave' => 'MED-001']);

        $medidorData = [
            'instalacion_id' => $this->instalacion->id,
            'numero_serie' => 'MD-12346',
            'clave' => 'MED-001',
            'elemento_tipo' => Medidor::ELEMENTO_TIPO_PRIMARIO,
            'tipo_medicion' => Medidor::TIPO_MEDICION_ESTATICA,
            'precision' => 0.5,
            'capacidad_maxima' => 1000,
            'estado' => Medidor::ESTADO_OPERATIVO,
        ];

        $response = $this->postJson('/api/medidores', $medidorData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['clave']);
    }

    /** @test */
    public function create_medidor_fails_when_tanque_not_belong_to_instalacion(): void
    {
        $otraInstalacion = Instalacion::factory()->create();
        $tanqueOtraInstalacion = Tanque::factory()->create(['instalacion_id' => $otraInstalacion->id]);

        $medidorData = [
            'instalacion_id' => $this->instalacion->id,
            'tanque_id' => $tanqueOtraInstalacion->id,
            'numero_serie' => 'MD-12345',
            'clave' => 'MED-001',
            'elemento_tipo' => Medidor::ELEMENTO_TIPO_PRIMARIO,
            'tipo_medicion' => Medidor::TIPO_MEDICION_ESTATICA,
            'precision' => 0.5,
            'capacidad_maxima' => 1000,
            'estado' => Medidor::ESTADO_OPERATIVO,
        ];

        $response = $this->postJson('/api/medidores', $medidorData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'El tanque no pertenece a la instalación especificada',
            ]);
    }

    /** @test */
    public function can_view_single_medidor(): void
    {
        $medidor = Medidor::factory()->create([
            'instalacion_id' => $this->instalacion->id,
            'tanque_id' => $this->tanque->id,
        ]);

        $response = $this->getJson("/api/medidores/{$medidor->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'numero_serie',
                    'clave',
                    'estado',
                    'instalacion',
                    'tanque',
                    'historialCalibracionesMedidor',
                ]
            ]);

        $this->assertEquals($medidor->id, $response->json('data.id'));
        $this->assertEquals($medidor->numero_serie, $response->json('data.numero_serie'));
    }

    /** @test */
    public function can_update_medidor(): void
    {
        $medidor = Medidor::factory()->create([
            'precision' => 0.5,
            'estado' => Medidor::ESTADO_OPERATIVO,
        ]);

        $updateData = [
            'precision' => 0.3,
            'estado' => Medidor::ESTADO_CALIBRACION,
            'observaciones' => 'En proceso de calibración',
        ];

        $response = $this->putJson("/api/medidores/{$medidor->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Medidor actualizado exitosamente',
            ]);

        $this->assertDatabaseHas('medidores', [
            'id' => $medidor->id,
            'precision' => 0.3,
            'estado' => Medidor::ESTADO_CALIBRACION,
        ]);
    }

    /** @test */
    public function can_register_calibration(): void
    {
        $medidor = Medidor::factory()->create();

        $calibrationData = [
            'fecha_calibracion' => '2024-01-15',
            'fecha_proxima_calibracion' => '2025-01-15',
            'certificado_calibracion' => 'CERT-MED-001',
            'laboratorio_calibracion' => 'Laboratorio Nacional',
            'incertidumbre_calibracion' => 0.1,
            'precision' => 0.5,
            'repetibilidad' => 0.05,
            'observaciones' => 'Calibración anual',
        ];

        $response = $this->postJson("/api/medidores/{$medidor->id}/calibraciones", $calibrationData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Calibración registrada exitosamente',
            ]);

        $this->assertDatabaseHas('historial_calibraciones_medidores', [
            'medidor_id' => $medidor->id,
            'certificado_calibracion' => 'CERT-MED-001',
        ]);

        $medidorActualizado = Medidor::find($medidor->id);
        $this->assertEquals('2024-01-15', $medidorActualizado->fecha_ultima_calibracion->format('Y-m-d'));
        $this->assertEquals(0.5, $medidorActualizado->precision);
    }

    /** @test */
    public function can_test_communication(): void
    {
        $medidor = Medidor::factory()->create([
            'protocolo_comunicacion' => 'modbus',
            'direccion_ip' => '192.168.1.100',
            'puerto_comunicacion' => 502,
        ]);

        $response = $this->postJson("/api/medidores/{$medidor->id}/probar-comunicacion");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'exitosa',
                    'latencia_ms',
                    'protocolo',
                    'direccion',
                    'timestamp',
                    'mensaje',
                ]
            ]);

        // Verificar que se registró el intento
        $medidorActualizado = Medidor::find($medidor->id);
        $this->assertNotEmpty($medidorActualizado->historial_desconexiones);
    }

    /** @test */
    public function can_verify_medidor_status(): void
    {
        $medidor = Medidor::factory()->create([
            'estado' => Medidor::ESTADO_OPERATIVO,
            'fecha_proxima_calibracion' => now()->addDays(20),
        ]);

        $response = $this->getJson("/api/medidores/{$medidor->id}/verificar-estado");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'medidor_id',
                    'numero_serie',
                    'estado',
                    'activo',
                    'precision_actual',
                    'comunicacion',
                    'calibracion',
                    'alertas',
                    'fecha_verificacion',
                ]
            ]);

        $this->assertEquals($medidor->id, $response->json('data.medidor_id'));
        $this->assertNotEmpty($response->json('data.alertas')); // Alerta de calibración próxima
    }

    /** @test */
    public function verify_medidor_status_shows_calibration_alert(): void
    {
        $medidor = Medidor::factory()->create([
            'fecha_proxima_calibracion' => now()->subDays(10),
        ]);

        $response = $this->getJson("/api/medidores/{$medidor->id}/verificar-estado");

        $response->assertStatus(200);
        
        $alertas = $response->json('data.alertas');
        $this->assertNotEmpty($alertas);
        $this->assertEquals('CALIBRACION_VENCIDA', $alertas[0]['tipo']);
    }

    /** @test */
    public function verify_medidor_status_shows_alteration_alert(): void
    {
        $medidor = Medidor::factory()->create([
            'alerta_alteracion' => true,
        ]);

        $response = $this->getJson("/api/medidores/{$medidor->id}/verificar-estado");

        $response->assertStatus(200);
        
        $alertas = $response->json('data.alertas');
        $this->assertNotEmpty($alertas);
        $this->assertEquals('ALTERACION_DETECTADA', $alertas[0]['tipo']);
    }

    /** @test */
    public function can_get_calibration_history(): void
    {
        $medidor = Medidor::factory()->create();
        
        HistorialCalibracionMedidor::factory()->count(3)->create([
            'medidor_id' => $medidor->id,
        ]);

        $response = $this->getJson("/api/medidores/{$medidor->id}/historial-calibraciones");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'medidor_id',
                    'numero_serie',
                    'historial' => [
                        '*' => [
                            'id',
                            'fecha_calibracion',
                            'fecha_proxima_calibracion',
                            'certificado_calibracion',
                            'precision',
                        ]
                    ],
                ]
            ]);

        $this->assertCount(3, $response->json('data.historial'));
    }

    /** @test */
    public function can_delete_medidor(): void
    {
        $medidor = Medidor::factory()->create();

        $response = $this->deleteJson("/api/medidores/{$medidor->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Medidor eliminado exitosamente',
            ]);

        $this->assertSoftDeleted('medidores', [
            'id' => $medidor->id,
        ]);
    }
}