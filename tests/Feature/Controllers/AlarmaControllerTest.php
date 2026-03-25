<?php

namespace Tests\Feature\Controllers;

use App\Models\Alarma;
use App\Models\User;
use App\Models\CatalogoValor;
use App\Models\Instalacion;
use App\Models\Bitacora;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlarmaControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private CatalogoValor $tipoAlarma;
    private Instalacion $instalacion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->tipoAlarma = CatalogoValor::factory()->create([
            'catalogo_id' => CatalogoValor::factory()->create()->id,
            'valor' => 'Alarma de temperatura',
            'clave' => 'TEMP_ALARM',
        ]);

        $this->instalacion = Instalacion::factory()->create([
            'activo' => true,
            'estatus' => 'OPERACION',
        ]);
    }

    /** @test */
    public function can_list_alarms(): void
    {
        Alarma::factory()->count(5)->create();

        $response = $this->getJson('/api/alarmas');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'numero_registro',
                            'descripcion',
                            'gravedad',
                            'estado_atencion',
                            'fecha_hora',
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
    public function can_filter_alarms_by_severity(): void
    {
        Alarma::factory()->create(['gravedad' => Alarma::GRAVEDAD_CRITICA]);
        Alarma::factory()->create(['gravedad' => Alarma::GRAVEDAD_ALTA]);
        Alarma::factory()->create(['gravedad' => Alarma::GRAVEDAD_MEDIA]);

        $response = $this->getJson('/api/alarmas?gravedad=' . Alarma::GRAVEDAD_CRITICA);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals(Alarma::GRAVEDAD_CRITICA, $response->json('data.data.0.gravedad'));
    }

    /** @test */
    public function can_filter_alarms_by_attention_state(): void
    {
        Alarma::factory()->create(['estado_atencion' => Alarma::ESTADO_PENDIENTE]);
        Alarma::factory()->create(['estado_atencion' => Alarma::ESTADO_EN_PROCESO]);
        Alarma::factory()->create(['estado_atencion' => Alarma::ESTADO_RESUELTA]);

        $response = $this->getJson('/api/alarmas?estado_atencion=' . Alarma::ESTADO_PENDIENTE);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals(Alarma::ESTADO_PENDIENTE, $response->json('data.data.0.estado_atencion'));
    }

    /** @test */
    public function can_filter_alarms_by_date_range(): void
    {
        Alarma::factory()->create(['fecha_hora' => now()->subDays(5)]);
        Alarma::factory()->create(['fecha_hora' => now()->subDays(3)]);
        Alarma::factory()->create(['fecha_hora' => now()->subDay()]);

        $response = $this->getJson('/api/alarmas?fecha_inicio=' . now()->subDays(4)->toDateString() . '&fecha_fin=' . now()->toDateString());

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.data'));
    }

    /** @test */
    public function can_create_alarm(): void
    {
        $alarmData = [
            'numero_registro' => 'AL-12345',
            'fecha_hora' => now()->toDateTimeString(),
            'componente_tipo' => 'tanque',
            'componente_id' => $this->instalacion->id,
            'componente_identificador' => 'TQ-001',
            'tipo_alarma_id' => $this->tipoAlarma->id,
            'gravedad' => Alarma::GRAVEDAD_CRITICA,
            'descripcion' => 'Alarma de prueba',
            'estado_atencion' => Alarma::ESTADO_PENDIENTE,
            'requiere_atencion_inmediata' => true,
        ];

        $response = $this->postJson('/api/alarmas', $alarmData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Alarma creada exitosamente',
            ]);

        $this->assertDatabaseHas('alarmas', [
            'numero_registro' => 'AL-12345',
            'descripcion' => 'Alarma de prueba',
            'gravedad' => Alarma::GRAVEDAD_CRITICA,
        ]);

        // Verificar que se registró en bitácora
        $this->assertDatabaseHas('bitacora', [
            'subtipo_evento' => 'CREACION_ALARMA',
            'tabla' => 'alarmas',
        ]);
    }

    /** @test */
    public function create_alarm_fails_with_invalid_data(): void
    {
        $invalidData = [
            'numero_registro' => '',
            'gravedad' => 'INVALID_SEVERITY',
            'estado_atencion' => 'INVALID_STATE',
        ];

        $response = $this->postJson('/api/alarmas', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['numero_registro', 'gravedad', 'estado_atencion']);
    }

    /** @test */
    public function create_alarm_fails_with_duplicate_registration_number(): void
    {
        Alarma::factory()->create(['numero_registro' => 'AL-12345']);

        $alarmData = [
            'numero_registro' => 'AL-12345',
            'fecha_hora' => now()->toDateTimeString(),
            'componente_tipo' => 'tanque',
            'componente_identificador' => 'TQ-001',
            'tipo_alarma_id' => $this->tipoAlarma->id,
            'gravedad' => Alarma::GRAVEDAD_CRITICA,
            'descripcion' => 'Alarma duplicada',
            'estado_atencion' => Alarma::ESTADO_PENDIENTE,
        ];

        $response = $this->postJson('/api/alarmas', $alarmData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['numero_registro']);
    }

    /** @test */
    public function can_view_single_alarm(): void
    {
        $alarma = Alarma::factory()->create([
            'tipo_alarma_id' => $this->tipoAlarma->id,
            'atendida_por' => $this->user->id,
        ]);

        $response = $this->getJson("/api/alarmas/{$alarma->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $alarma->id,
                    'numero_registro' => $alarma->numero_registro,
                    'descripcion' => $alarma->descripcion,
                ]
            ]);
    }

    /** @test */
    public function view_nonexistent_alarm_returns_404(): void
    {
        $response = $this->getJson('/api/alarmas/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Alarma no encontrada',
            ]);
    }

    /** @test */
    public function can_attend_alarm(): void
    {
        $alarma = Alarma::factory()->create([
            'atendida' => false,
            'estado_atencion' => Alarma::ESTADO_PENDIENTE,
        ]);

        $response = $this->postJson("/api/alarmas/{$alarma->id}/atender", [
            'acciones_tomadas' => 'Se verificó el tanque y se restableció el sistema',
            'estado_atencion' => Alarma::ESTADO_RESUELTA,
            'observaciones' => 'Se reinició el sistema de monitoreo',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Alarma atendida exitosamente',
            ]);

        $this->assertDatabaseHas('alarmas', [
            'id' => $alarma->id,
            'atendida' => true,
            'estado_atencion' => Alarma::ESTADO_RESUELTA,
            'atendida_por' => $this->user->id,
        ]);

        // Verificar historial de cambios
        $alarmaActualizada = Alarma::find($alarma->id);
        $this->assertNotNull($alarmaActualizada->historial_cambios_estado);
        $this->assertCount(1, $alarmaActualizada->historial_cambios_estado);
    }

    /** @test */
    public function attend_alarm_fails_if_already_attended(): void
    {
        $alarma = Alarma::factory()->create([
            'atendida' => true,
            'estado_atencion' => Alarma::ESTADO_RESUELTA,
        ]);

        $response = $this->postJson("/api/alarmas/{$alarma->id}/atender", [
            'acciones_tomadas' => 'Algo',
            'estado_atencion' => Alarma::ESTADO_RESUELTA,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'La alarma ya ha sido atendida',
            ]);
    }

    /** @test */
    public function can_update_alarm_state(): void
    {
        $alarma = Alarma::factory()->create([
            'estado_atencion' => Alarma::ESTADO_PENDIENTE,
            'atendida' => false,
        ]);

        $response = $this->putJson("/api/alarmas/{$alarma->id}/estado", [
            'estado_atencion' => Alarma::ESTADO_EN_PROCESO,
            'observaciones' => 'Iniciando proceso de atención',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Estado de alarma actualizado exitosamente',
            ]);

        $this->assertDatabaseHas('alarmas', [
            'id' => $alarma->id,
            'estado_atencion' => Alarma::ESTADO_EN_PROCESO,
            'atendida' => false, // No cambia a atendida hasta que se resuelva
        ]);
    }

    /** @test */
    public function get_alarm_statistics(): void
    {
        // Crear alarmas de prueba
        Alarma::factory()->create([
            'componente_tipo' => 'instalacion',
            'componente_id' => $this->instalacion->id,
            'fecha_hora' => now(),
            'gravedad' => Alarma::GRAVEDAD_CRITICA,
            'atendida' => false,
            'estado_atencion' => Alarma::ESTADO_PENDIENTE,
        ]);

        Alarma::factory()->create([
            'componente_tipo' => 'instalacion',
            'componente_id' => $this->instalacion->id,
            'fecha_hora' => now()->subDays(1),
            'gravedad' => Alarma::GRAVEDAD_ALTA,
            'atendida' => true,
            'estado_atencion' => Alarma::ESTADO_RESUELTA,
            'fecha_atencion' => now(),
        ]);

        Alarma::factory()->create([
            'componente_tipo' => 'instalacion',
            'componente_id' => $this->instalacion->id,
            'fecha_hora' => now()->subDays(2),
            'gravedad' => Alarma::GRAVEDAD_MEDIA,
            'atendida' => false,
            'estado_atencion' => Alarma::ESTADO_PENDIENTE,
        ]);

        $response = $this->getJson("/api/alarmas/estadisticas?instalacion_id={$this->instalacion->id}&fecha_inicio=" . now()->subDays(3)->toDateString() . "&fecha_fin=" . now()->toDateString());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'instalacion_id',
                    'periodo',
                    'resumen' => [
                        'total_alarmas',
                        'atendidas',
                        'pendientes',
                        'requieren_atencion',
                    ],
                    'por_gravedad',
                    'por_estado',
                    'tendencia_diaria',
                    'tiempo_promedio_respuesta',
                ]
            ]);

        $this->assertEquals(3, $response->json('data.resumen.total_alarmas'));
        $this->assertEquals(1, $response->json('data.resumen.atendidas'));
        $this->assertEquals(2, $response->json('data.resumen.pendientes'));
    }

    /** @test */
    public function get_active_alarms(): void
    {
        Alarma::factory()->create(['atendida' => false, 'gravedad' => Alarma::GRAVEDAD_CRITICA]);
        Alarma::factory()->create(['atendida' => false, 'gravedad' => Alarma::GRAVEDAD_ALTA]);
        Alarma::factory()->create(['atendida' => false, 'gravedad' => Alarma::GRAVEDAD_MEDIA]);
        Alarma::factory()->create(['atendida' => true]);

        $response = $this->getJson('/api/alarmas/activas');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total' => 3,
                    'criticas' => 1,
                    'altas' => 1,
                ]
            ]);

        $this->assertCount(3, $response->json('data.alarmas'));
    }

    /** @test */
    public function can_filter_active_alarms_by_component(): void
    {
        Alarma::factory()->create([
            'atendida' => false,
            'componente_tipo' => 'tanque',
            'componente_id' => 1,
        ]);

        Alarma::factory()->create([
            'atendida' => false,
            'componente_tipo' => 'medidor',
            'componente_id' => 1,
        ]);

        $response = $this->getJson('/api/alarmas/activas?componente_tipo=tanque&componente_id=1');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.alarmas'));
        $this->assertEquals('tanque', $response->json('data.alarmas.0.componente_tipo'));
    }

    /** @test */
    public function can_update_alarm(): void
    {
        $alarma = Alarma::factory()->create([
            'descripcion' => 'Descripción original',
        ]);

        $response = $this->putJson("/api/alarmas/{$alarma->id}", [
            'descripcion' => 'Descripción actualizada',
            'gravedad' => Alarma::GRAVEDAD_ALTA,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Alarma actualizada exitosamente',
            ]);

        $this->assertDatabaseHas('alarmas', [
            'id' => $alarma->id,
            'descripcion' => 'Descripción actualizada',
            'gravedad' => Alarma::GRAVEDAD_ALTA,
        ]);
    }

    /** @test */
    public function can_delete_alarm(): void
    {
        $alarma = Alarma::factory()->create();

        $response = $this->deleteJson("/api/alarmas/{$alarma->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Alarma eliminada exitosamente',
            ]);

        $this->assertSoftDeleted('alarmas', [
            'id' => $alarma->id,
        ]);
    }

    /** @test */
    public function delete_alarm_updates_bitacora(): void
    {
        $alarma = Alarma::factory()->create();

        $response = $this->deleteJson("/api/alarmas/{$alarma->id}");

        $response->assertStatus(200);

        $this->assertDatabaseHas('bitacora', [
            'subtipo_evento' => 'ELIMINACION_ALARMA',
            'tabla' => 'alarmas',
            'registro_id' => $alarma->id,
        ]);
    }

    /** @test */
    public function alarm_response_time_calculation_is_correct(): void
    {
        $fechaHora = now()->subHours(2);
        $fechaAtencion = now()->subHour();

        $alarma = Alarma::factory()->create([
            'fecha_hora' => $fechaHora,
            'fecha_atencion' => $fechaAtencion,
            'atendida' => true,
        ]);

        $response = $this->getJson("/api/alarmas/estadisticas?instalacion_id={$alarma->componente_id}&fecha_inicio=" . now()->subDays(1)->toDateString() . "&fecha_fin=" . now()->toDateString());

        $response->assertStatus(200);
        
        $tiempoPromedio = $response->json('data.tiempo_promedio_respuesta');
        $this->assertNotNull($tiempoPromedio);
        $this->assertEquals(60, $tiempoPromedio['promedio_minutos']); // 1 hora = 60 minutos
    }
}