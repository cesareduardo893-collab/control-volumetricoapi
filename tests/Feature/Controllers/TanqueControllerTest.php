<?php

namespace Tests\Feature\Controllers;

use App\Models\Tanque;
use App\Models\Instalacion;
use App\Models\Producto;
use App\Models\User;
use App\Models\CatalogoValor;
use App\Models\HistorialCalibracion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TanqueControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Instalacion $instalacion;
    private Producto $producto;
    private CatalogoValor $tipoTanque;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->instalacion = Instalacion::factory()->create(['activo' => true]);
        $this->producto = Producto::factory()->create(['activo' => true]);
        $this->tipoTanque = CatalogoValor::factory()->create();
    }

    /** @test */
    public function can_list_tanques(): void
    {
        Tanque::factory()->count(5)->create(['instalacion_id' => $this->instalacion->id]);

        $response = $this->getJson('/api/tanques');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'identificador',
                            'capacidad_total',
                            'capacidad_operativa',
                            'estado',
                            'activo',
                            'instalacion',
                            'producto',
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
    public function can_filter_tanques_by_instalacion(): void
    {
        $instalacion2 = Instalacion::factory()->create();

        Tanque::factory()->count(2)->create(['instalacion_id' => $this->instalacion->id]);
        Tanque::factory()->count(3)->create(['instalacion_id' => $instalacion2->id]);

        $response = $this->getJson('/api/tanques?instalacion_id=' . $this->instalacion->id);

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.data'));
        $this->assertEquals($this->instalacion->id, $response->json('data.data.0.instalacion_id'));
    }

    /** @test */
    public function can_filter_tanques_by_estado(): void
    {
        Tanque::factory()->create(['estado' => Tanque::ESTADO_OPERATIVO]);
        Tanque::factory()->create(['estado' => Tanque::ESTADO_MANTENIMIENTO]);

        $response = $this->getJson('/api/tanques?estado=' . Tanque::ESTADO_OPERATIVO);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals(Tanque::ESTADO_OPERATIVO, $response->json('data.data.0.estado'));
    }

    /** @test */
    public function can_filter_tanques_by_producto(): void
    {
        $producto2 = Producto::factory()->create();

        Tanque::factory()->create(['producto_id' => $this->producto->id]);
        Tanque::factory()->create(['producto_id' => $producto2->id]);

        $response = $this->getJson('/api/tanques?producto_id=' . $this->producto->id);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals($this->producto->id, $response->json('data.data.0.producto_id'));
    }

    /** @test */
    public function can_filter_tanques_by_alerta_alteracion(): void
    {
        Tanque::factory()->create(['alerta_alteracion' => true]);
        Tanque::factory()->create(['alerta_alteracion' => false]);

        $response = $this->getJson('/api/tanques?alerta_alteracion=true');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertTrue($response->json('data.data.0.alerta_alteracion'));
    }

    /** @test */
    public function can_create_tanque(): void
    {
        $tanqueData = [
            'instalacion_id' => $this->instalacion->id,
            'producto_id' => $this->producto->id,
            'numero_serie' => 'TN-12345',
            'identificador' => 'TQ-001',
            'tipo_tanque_id' => $this->tipoTanque->id,
            'placas' => 'PLACA123',
            'numero_economico' => 'ECO-001',
            'modelo' => 'Modelo X',
            'fabricante' => 'Fabricante SA',
            'material' => 'Acero',
            'capacidad_total' => 50000,
            'capacidad_util' => 45000,
            'capacidad_operativa' => 40000,
            'capacidad_minima' => 1000,
            'capacidad_gas_talon' => 500,
            'fecha_fabricacion' => '2023-01-01',
            'fecha_instalacion' => '2023-06-01',
            'temperatura_referencia' => 20.00,
            'presion_referencia' => 101.325,
            'tipo_medicion' => Tanque::TIPO_MEDICION_ESTATICA,
            'estado' => Tanque::ESTADO_OPERATIVO,
            'observaciones' => 'Tanque principal',
            'activo' => true,
        ];

        $response = $this->postJson('/api/tanques', $tanqueData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Tanque creado exitosamente',
            ]);

        $this->assertDatabaseHas('tanques', [
            'identificador' => 'TQ-001',
            'numero_serie' => 'TN-12345',
            'capacidad_total' => 50000,
            'estado' => Tanque::ESTADO_OPERATIVO,
        ]);
    }

    /** @test */
    public function create_tanque_fails_with_duplicate_identificador(): void
    {
        Tanque::factory()->create(['identificador' => 'TQ-001']);

        $tanqueData = [
            'instalacion_id' => $this->instalacion->id,
            'identificador' => 'TQ-001',
            'material' => 'Acero',
            'capacidad_total' => 50000,
            'capacidad_util' => 45000,
            'capacidad_operativa' => 40000,
            'capacidad_minima' => 1000,
            'temperatura_referencia' => 20.00,
            'presion_referencia' => 101.325,
            'tipo_medicion' => Tanque::TIPO_MEDICION_ESTATICA,
            'estado' => Tanque::ESTADO_OPERATIVO,
        ];

        $response = $this->postJson('/api/tanques', $tanqueData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['identificador']);
    }

    /** @test */
    public function create_tanque_fails_with_invalid_capacities(): void
    {
        $tanqueData = [
            'instalacion_id' => $this->instalacion->id,
            'identificador' => 'TQ-001',
            'material' => 'Acero',
            'capacidad_total' => 50000,
            'capacidad_util' => 55000, // Mayor que capacidad_total
            'capacidad_operativa' => 60000, // Mayor que capacidad_util
            'capacidad_minima' => 1000,
            'temperatura_referencia' => 20.00,
            'presion_referencia' => 101.325,
            'tipo_medicion' => Tanque::TIPO_MEDICION_ESTATICA,
            'estado' => Tanque::ESTADO_OPERATIVO,
        ];

        $response = $this->postJson('/api/tanques', $tanqueData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['capacidad_util', 'capacidad_operativa']);
    }

    /** @test */
    public function create_tanque_fails_with_inactive_instalacion(): void
    {
        $instalacionInactiva = Instalacion::factory()->create(['activo' => false]);

        $tanqueData = [
            'instalacion_id' => $instalacionInactiva->id,
            'identificador' => 'TQ-001',
            'material' => 'Acero',
            'capacidad_total' => 50000,
            'capacidad_util' => 45000,
            'capacidad_operativa' => 40000,
            'capacidad_minima' => 1000,
            'temperatura_referencia' => 20.00,
            'presion_referencia' => 101.325,
            'tipo_medicion' => Tanque::TIPO_MEDICION_ESTATICA,
            'estado' => Tanque::ESTADO_OPERATIVO,
        ];

        $response = $this->postJson('/api/tanques', $tanqueData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'La instalación no está activa',
            ]);
    }

    /** @test */
    public function can_view_single_tanque(): void
    {
        $tanque = Tanque::factory()->create([
            'instalacion_id' => $this->instalacion->id,
            'producto_id' => $this->producto->id,
        ]);

        $response = $this->getJson("/api/tanques/{$tanque->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'identificador',
                    'capacidad_total',
                    'estado',
                    'instalacion',
                    'producto',
                    'medidores',
                    'historialCalibraciones',
                ]
            ]);

        $this->assertEquals($tanque->id, $response->json('data.id'));
        $this->assertEquals($tanque->identificador, $response->json('data.identificador'));
    }

    /** @test */
    public function can_update_tanque(): void
    {
        $tanque = Tanque::factory()->create([
            'identificador' => 'TQ-001',
            'capacidad_operativa' => 40000,
            'estado' => Tanque::ESTADO_OPERATIVO,
        ]);

        $updateData = [
            'identificador' => 'TQ-002',
            'capacidad_operativa' => 45000,
            'estado' => Tanque::ESTADO_MANTENIMIENTO,
            'observaciones' => 'En mantenimiento preventivo',
        ];

        $response = $this->putJson("/api/tanques/{$tanque->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Tanque actualizado exitosamente',
            ]);

        $this->assertDatabaseHas('tanques', [
            'id' => $tanque->id,
            'identificador' => 'TQ-002',
            'capacidad_operativa' => 45000,
            'estado' => Tanque::ESTADO_MANTENIMIENTO,
        ]);
    }

    /** @test */
    public function can_update_tanque_with_calibration_data(): void
    {
        $tanque = Tanque::factory()->create();

        $updateData = [
            'fecha_ultima_calibracion' => '2024-01-15',
            'fecha_proxima_calibracion' => '2025-01-15',
            'certificado_calibracion' => 'CERT-001',
            'entidad_calibracion' => 'Laboratorio Nacional',
            'incertidumbre_medicion' => 0.5,
            'tabla_aforo' => [
                ['nivel' => 0, 'volumen' => 0],
                ['nivel' => 100, 'volumen' => 5000],
                ['nivel' => 200, 'volumen' => 10000],
            ],
        ];

        $response = $this->putJson("/api/tanques/{$tanque->id}", $updateData);

        $response->assertStatus(200);

        $tanqueActualizado = Tanque::find($tanque->id);
        $this->assertEquals('2024-01-15', $tanqueActualizado->fecha_ultima_calibracion->format('Y-m-d'));
        $this->assertEquals('CERT-001', $tanqueActualizado->certificado_calibracion);
        $this->assertCount(3, $tanqueActualizado->tabla_aforo);
    }

    /** @test */
    public function can_register_calibration(): void
    {
        $tanque = Tanque::factory()->create();

        $calibrationData = [
            'fecha_calibracion' => '2024-01-15',
            'fecha_proxima_calibracion' => '2025-01-15',
            'certificado_calibracion' => 'CERT-001',
            'entidad_calibracion' => 'Laboratorio Nacional',
            'incertidumbre_medicion' => 0.5,
            'tabla_aforo' => [
                ['nivel' => 0, 'volumen' => 0],
                ['nivel' => 100, 'volumen' => 5000],
            ],
            'curvas_calibracion' => [
                ['temperatura' => 20, 'factor' => 1.0],
                ['temperatura' => 30, 'factor' => 1.002],
            ],
            'observaciones' => 'Calibración anual',
        ];

        $response = $this->postJson("/api/tanques/{$tanque->id}/calibraciones", $calibrationData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Calibración registrada exitosamente',
            ]);

        $this->assertDatabaseHas('historial_calibraciones', [
            'tanque_id' => $tanque->id,
            'certificado_calibracion' => 'CERT-001',
        ]);

        $tanqueActualizado = Tanque::find($tanque->id);
        $this->assertEquals('2024-01-15', $tanqueActualizado->fecha_ultima_calibracion->format('Y-m-d'));
        $this->assertEquals('CERT-001', $tanqueActualizado->certificado_calibracion);
    }

    /** @test */
    public function register_calibration_fails_with_invalid_data(): void
    {
        $tanque = Tanque::factory()->create();

        $calibrationData = [
            'fecha_calibracion' => '2025-01-15',
            'fecha_proxima_calibracion' => '2024-01-15', // Fecha anterior
            'certificado_calibracion' => 'CERT-001',
            'entidad_calibracion' => 'Laboratorio',
            'tabla_aforo' => [],
        ];

        $response = $this->postJson("/api/tanques/{$tanque->id}/calibraciones", $calibrationData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_proxima_calibracion', 'tabla_aforo']);
    }

    /** @test */
    public function can_verify_tanque_status(): void
    {
        $tanque = Tanque::factory()->create([
            'estado' => Tanque::ESTADO_OPERATIVO,
            'fecha_proxima_calibracion' => now()->addDays(15),
        ]);

        $response = $this->getJson("/api/tanques/{$tanque->id}/verificar-estado");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tanque_id',
                    'identificador',
                    'estado',
                    'activo',
                    'producto',
                    'capacidad_operativa',
                    'calibracion',
                    'alertas',
                    'fecha_verificacion',
                ]
            ]);

        $this->assertEquals($tanque->id, $response->json('data.tanque_id'));
        $this->assertEquals(Tanque::ESTADO_OPERATIVO, $response->json('data.estado'));
        $this->assertNotEmpty($response->json('data.alertas')); // Alerta de calibración próxima
    }

    /** @test */
    public function verify_tanque_status_shows_calibration_alert(): void
    {
        $tanque = Tanque::factory()->create([
            'fecha_proxima_calibracion' => now()->subDays(5), // Vencida
        ]);

        $response = $this->getJson("/api/tanques/{$tanque->id}/verificar-estado");

        $response->assertStatus(200);
        
        $alertas = $response->json('data.alertas');
        $this->assertNotEmpty($alertas);
        $this->assertEquals('CALIBRACION_VENCIDA', $alertas[0]['tipo']);
        $this->assertEquals('CRITICA', $alertas[0]['severidad']);
    }

    /** @test */
    public function verify_tanque_status_shows_alteration_alert(): void
    {
        $tanque = Tanque::factory()->create([
            'alerta_alteracion' => true,
            'ultima_deteccion_alteracion' => now(),
        ]);

        $response = $this->getJson("/api/tanques/{$tanque->id}/verificar-estado");

        $response->assertStatus(200);
        
        $alertas = $response->json('data.alertas');
        $this->assertNotEmpty($alertas);
        $this->assertEquals('ALTERACION_DETECTADA', $alertas[0]['tipo']);
        $this->assertEquals('ALTA', $alertas[0]['severidad']);
    }

    /** @test */
    public function can_change_tanque_product(): void
    {
        $tanque = Tanque::factory()->create([
            'producto_id' => $this->producto->id,
        ]);
        
        $nuevoProducto = Producto::factory()->create();

        $changeData = [
            'producto_id' => $nuevoProducto->id,
            'motivo' => 'Cambio de producto por nueva especificación',
            'fecha_cambio' => now()->toDateString(),
            'observaciones' => 'Se realizó limpieza del tanque',
        ];

        $response = $this->postJson("/api/tanques/{$tanque->id}/cambiar-producto", $changeData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Producto del tanque actualizado exitosamente',
            ]);

        $this->assertDatabaseHas('tanques', [
            'id' => $tanque->id,
            'producto_id' => $nuevoProducto->id,
        ]);

        // Verificar que se registró en evidencias
        $tanqueActualizado = Tanque::find($tanque->id);
        $this->assertNotEmpty($tanqueActualizado->evidencias_alteracion);
        $this->assertEquals('CAMBIO_PRODUCTO', $tanqueActualizado->evidencias_alteracion[0]['tipo']);
    }

    /** @test */
    public function change_tanque_product_fails_without_motivo(): void
    {
        $tanque = Tanque::factory()->create();
        $nuevoProducto = Producto::factory()->create();

        $response = $this->postJson("/api/tanques/{$tanque->id}/cambiar-producto", [
            'producto_id' => $nuevoProducto->id,
            'fecha_cambio' => now()->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['motivo']);
    }

    /** @test */
    public function can_get_calibration_curve(): void
    {
        $tanque = Tanque::factory()->create([
            'tabla_aforo' => [
                ['nivel' => 0, 'volumen' => 0],
                ['nivel' => 100, 'volumen' => 5000],
                ['nivel' => 200, 'volumen' => 10000],
            ],
            'curvas_calibracion' => [
                ['temperatura' => 20, 'factor' => 1.0],
                ['temperatura' => 30, 'factor' => 1.002],
            ],
        ]);

        $response = $this->getJson("/api/tanques/{$tanque->id}/curva-calibracion");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tanque_id',
                    'identificador',
                    'fecha_ultima_calibracion',
                    'certificado',
                    'tabla_aforo',
                    'curvas_calibracion',
                ]
            ]);

        $this->assertCount(3, $response->json('data.tabla_aforo'));
        $this->assertCount(2, $response->json('data.curvas_calibracion'));
    }

    /** @test */
    public function get_calibration_curve_returns_404_when_no_table(): void
    {
        $tanque = Tanque::factory()->create(['tabla_aforo' => null]);

        $response = $this->getJson("/api/tanques/{$tanque->id}/curva-calibracion");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'El tanque no tiene tabla de aforo',
            ]);
    }

    /** @test */
    public function can_get_calibration_history(): void
    {
        $tanque = Tanque::factory()->create();
        
        HistorialCalibracion::factory()->count(3)->create([
            'tanque_id' => $tanque->id,
        ]);

        $response = $this->getJson("/api/tanques/{$tanque->id}/historial-calibraciones");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tanque_id',
                    'identificador',
                    'historial' => [
                        '*' => [
                            'id',
                            'fecha_calibracion',
                            'fecha_proxima_calibracion',
                            'certificado_calibracion',
                        ]
                    ],
                ]
            ]);

        $this->assertCount(3, $response->json('data.historial'));
    }

    /** @test */
    public function can_delete_tanque(): void
    {
        $tanque = Tanque::factory()->create();

        $response = $this->deleteJson("/api/tanques/{$tanque->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Tanque eliminado exitosamente',
            ]);

        $this->assertSoftDeleted('tanques', [
            'id' => $tanque->id,
        ]);
    }

    /** @test */
    public function cannot_delete_tanque_with_active_medidores(): void
    {
        $tanque = Tanque::factory()->create();
        \App\Models\Medidor::factory()->create([
            'tanque_id' => $tanque->id,
            'activo' => true,
        ]);

        $response = $this->deleteJson("/api/tanques/{$tanque->id}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'No se puede eliminar el tanque porque tiene 1 medidores asociados',
            ]);
    }
}