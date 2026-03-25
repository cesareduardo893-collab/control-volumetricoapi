<?php

namespace Tests\Feature\Controllers;

use App\Models\Bitacora;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BitacoraControllerTest extends TestCase
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
    public function can_list_bitacora_events(): void
    {
        Bitacora::factory()->count(5)->create([
            'usuario_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/bitacora');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'numero_registro',
                            'tipo_evento',
                            'subtipo_evento',
                            'modulo',
                            'descripcion',
                            'created_at',
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
    public function can_filter_bitacora_by_user(): void
    {
        $user2 = User::factory()->create();

        Bitacora::factory()->create(['usuario_id' => $this->user->id]);
        Bitacora::factory()->create(['usuario_id' => $user2->id]);

        $response = $this->getJson('/api/bitacora?usuario_id=' . $this->user->id);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals($this->user->id, $response->json('data.data.0.usuario_id'));
    }

    /** @test */
    public function can_filter_bitacora_by_event_type(): void
    {
        Bitacora::factory()->create([
            'tipo_evento' => Bitacora::TIPO_EVENTO_SEGURIDAD,
        ]);
        Bitacora::factory()->create([
            'tipo_evento' => Bitacora::TIPO_EVENTO_ADMINISTRACION,
        ]);

        $response = $this->getJson('/api/bitacora?tipo_evento=' . Bitacora::TIPO_EVENTO_SEGURIDAD);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals(Bitacora::TIPO_EVENTO_SEGURIDAD, $response->json('data.data.0.tipo_evento'));
    }

    /** @test */
    public function can_filter_bitacora_by_module(): void
    {
        Bitacora::factory()->create(['modulo' => 'Autenticación']);
        Bitacora::factory()->create(['modulo' => 'Inventarios']);

        $response = $this->getJson('/api/bitacora?modulo=Autenticación');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('Autenticación', $response->json('data.data.0.modulo'));
    }

    /** @test */
    public function can_filter_bitacora_by_date_range(): void
    {
        Bitacora::factory()->create(['created_at' => now()->subDays(5)]);
        Bitacora::factory()->create(['created_at' => now()->subDays(3)]);
        Bitacora::factory()->create(['created_at' => now()->subDay()]);

        $response = $this->getJson('/api/bitacora?fecha_inicio=' . now()->subDays(4)->toDateString() . '&fecha_fin=' . now()->toDateString());

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.data'));
    }

    /** @test */
    public function can_view_single_bitacora_event(): void
    {
        $evento = Bitacora::factory()->create();

        $response = $this->getJson("/api/bitacora/{$evento->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $evento->id,
                    'numero_registro' => $evento->numero_registro,
                    'descripcion' => $evento->descripcion,
                ]
            ]);
    }

    /** @test */
    public function get_activity_summary(): void
    {
        Bitacora::factory()->create([
            'tipo_evento' => Bitacora::TIPO_EVENTO_SEGURIDAD,
            'modulo' => 'Autenticación',
            'created_at' => now()->subDays(2),
        ]);

        Bitacora::factory()->create([
            'tipo_evento' => Bitacora::TIPO_EVENTO_ADMINISTRACION,
            'modulo' => 'Usuarios',
            'created_at' => now()->subDays(1),
        ]);

        Bitacora::factory()->create([
            'tipo_evento' => Bitacora::TIPO_EVENTO_OPERACIONES,
            'modulo' => 'Inventarios',
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/bitacora/resumen-actividad?fecha_inicio=' . now()->subDays(3)->toDateString() . '&fecha_fin=' . now()->toDateString());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'periodo',
                    'total_eventos',
                    'por_tipo_evento',
                    'por_modulo',
                    'actividad_usuarios',
                    'tendencia_diaria',
                ]
            ]);

        $this->assertEquals(3, $response->json('data.total_eventos'));
        $this->assertArrayHasKey(Bitacora::TIPO_EVENTO_SEGURIDAD, $response->json('data.por_tipo_evento'));
        $this->assertArrayHasKey('Autenticación', $response->json('data.por_modulo'));
    }

    /** @test */
    public function get_user_activity(): void
    {
        Bitacora::factory()->count(3)->create([
            'usuario_id' => $this->user->id,
            'created_at' => now()->subDays(2),
        ]);

        $response = $this->getJson("/api/bitacora/usuario/{$this->user->id}?fecha_inicio=" . now()->subDays(3)->toDateString() . "&fecha_fin=" . now()->toDateString());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'usuario_id',
                    'usuario_nombre',
                    'periodo',
                    'total_eventos',
                    'por_modulo',
                    'por_tipo_evento',
                    'eventos',
                ]
            ]);

        $this->assertEquals(3, $response->json('data.total_eventos'));
        $this->assertEquals($this->user->id, $response->json('data.usuario_id'));
    }

    /** @test */
    public function get_module_activity(): void
    {
        Bitacora::factory()->count(3)->create([
            'modulo' => 'Inventarios',
            'created_at' => now()->subDays(2),
        ]);

        $response = $this->getJson('/api/bitacora/modulo/Inventarios?fecha_inicio=' . now()->subDays(3)->toDateString() . '&fecha_fin=' . now()->toDateString());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'modulo',
                    'periodo',
                    'total_eventos',
                    'por_tipo_evento',
                    'por_usuario',
                    'eventos',
                ]
            ]);

        $this->assertEquals(3, $response->json('data.total_eventos'));
        $this->assertEquals('Inventarios', $response->json('data.modulo'));
    }

    /** @test */
    public function get_table_activity(): void
    {
        Bitacora::factory()->create([
            'tabla' => 'users',
            'registro_id' => 1,
            'created_at' => now()->subDays(2),
        ]);

        Bitacora::factory()->create([
            'tabla' => 'users',
            'registro_id' => 1,
            'created_at' => now()->subDays(1),
        ]);

        Bitacora::factory()->create([
            'tabla' => 'alarmas',
            'registro_id' => 1,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/bitacora/tabla/users/1?fecha_inicio=' . now()->subDays(3)->toDateString() . '&fecha_fin=' . now()->toDateString());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tabla',
                    'registro_id',
                    'periodo',
                    'total_eventos',
                    'por_tipo_evento',
                    'eventos',
                ]
            ]);

        $this->assertEquals(2, $response->json('data.total_eventos'));
        $this->assertEquals('users', $response->json('data.tabla'));
        $this->assertEquals(1, $response->json('data.registro_id'));
    }

    /** @test */
    public function can_export_bitacora_in_json_format(): void
    {
        Bitacora::factory()->count(3)->create();

        $response = $this->getJson('/api/bitacora/exportar?formato=JSON&fecha_inicio=' . now()->subDays(1)->toDateString() . '&fecha_fin=' . now()->toDateString());

        $response->assertStatus(200)
            ->assertHeader('Content-Disposition', 'attachment; filename="bitacora.json"')
            ->assertJsonStructure([
                'generado',
                'usuario',
                'periodo',
                'total_registros',
                'eventos',
            ]);
    }

    /** @test */
    public function can_export_bitacora_in_csv_format(): void
    {
        Bitacora::factory()->count(3)->create();

        $response = $this->get('/api/bitacora/exportar?formato=CSV&fecha_inicio=' . now()->subDays(1)->toDateString() . '&fecha_fin=' . now()->toDateString());

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv')
            ->assertHeader('Content-Disposition', 'attachment; filename="bitacora.csv"');
    }

    /** @test */
    public function can_export_bitacora_in_pdf_format(): void
    {
        Bitacora::factory()->count(3)->create();

        $response = $this->get('/api/bitacora/exportar?formato=PDF&fecha_inicio=' . now()->subDays(1)->toDateString() . '&fecha_fin=' . now()->toDateString());

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="bitacora.pdf"');
    }

    /** @test */
    public function export_fails_with_invalid_format(): void
    {
        $response = $this->getJson('/api/bitacora/exportar?formato=XML&fecha_inicio=' . now()->subDays(1)->toDateString() . '&fecha_fin=' . now()->toDateString());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['formato']);
    }

    /** @test */
    public function export_fails_without_date_range(): void
    {
        $response = $this->getJson('/api/bitacora/exportar?formato=JSON');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_inicio', 'fecha_fin']);
    }

    /** @test */
    public function activity_summary_requires_valid_date_range(): void
    {
        $response = $this->getJson('/api/bitacora/resumen-actividad');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_inicio', 'fecha_fin']);
    }

    /** @test */
    public function activity_summary_fails_when_end_date_before_start_date(): void
    {
        $response = $this->getJson('/api/bitacora/resumen-actividad?fecha_inicio=2024-01-10&fecha_fin=2024-01-01');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_fin']);
    }

    /** @test */
    public function user_activity_requires_valid_date_range(): void
    {
        $response = $this->getJson("/api/bitacora/usuario/{$this->user->id}");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_inicio', 'fecha_fin']);
    }

    /** @test */
    public function user_activity_fails_with_nonexistent_user(): void
    {
        $response = $this->getJson('/api/bitacora/usuario/99999?fecha_inicio=2024-01-01&fecha_fin=2024-01-31');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Usuario no encontrado',
            ]);
    }

    /** @test */
    public function module_activity_requires_valid_date_range(): void
    {
        $response = $this->getJson('/api/bitacora/modulo/Inventarios');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_inicio', 'fecha_fin']);
    }

    /** @test */
    public function table_activity_requires_valid_date_range(): void
    {
        $response = $this->getJson('/api/bitacora/tabla/users/1');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_inicio', 'fecha_fin']);
    }
}