<?php

namespace Tests\Feature\Controllers;

use App\Models\Contribuyente;
use App\Models\Instalacion;
use App\Models\Alarma;
use App\Models\Existencia;
use App\Models\MovimientoDia;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
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
    public function can_get_dashboard_summary(): void
    {
        // Crear datos de prueba
        Contribuyente::factory()->count(5)->create(['activo' => true]);
        Contribuyente::factory()->count(2)->create(['activo' => false]);

        Instalacion::factory()->count(3)->create([
            'activo' => true,
            'estatus' => 'OPERACION',
        ]);
        Instalacion::factory()->count(1)->create([
            'activo' => true,
            'estatus' => 'SUSPENDIDA',
        ]);

        Alarma::factory()->count(4)->create(['atendida' => false]);
        Alarma::factory()->count(2)->create(['atendida' => true]);

        Existencia::factory()->create(['volumen_disponible' => 10000]);
        Existencia::factory()->create(['volumen_disponible' => 15000]);
        Existencia::factory()->create(['volumen_disponible' => 5000]);

        MovimientoDia::factory()->count(5)->create();

        $response = $this->getJson('/api/dashboard/resumen');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'contribuyentes_activos',
                    'instalaciones_activas',
                    'alarmas_activas',
                    'volumen_total',
                    'ultimos_movimientos',
                ]
            ]);

        $this->assertEquals(5, $response->json('data.contribuyentes_activos'));
        $this->assertEquals(3, $response->json('data.instalaciones_activas'));
        $this->assertEquals(4, $response->json('data.alarmas_activas'));
        $this->assertEquals(30000, $response->json('data.volumen_total'));
        $this->assertCount(5, $response->json('data.ultimos_movimientos'));
    }

    /** @test */
    public function can_get_real_time_data(): void
    {
        $hoy = now()->toDateString();

        MovimientoDia::factory()->create([
            'created_at' => now(),
            'volumen' => 1000,
            'presion' => 50.5,
            'temperatura' => 25.3,
            'tipo_movimiento' => 'RECEPCION',
        ]);

        MovimientoDia::factory()->create([
            'created_at' => now(),
            'volumen' => 800,
            'presion' => 49.8,
            'temperatura' => 24.9,
            'tipo_movimiento' => 'VENTA',
        ]);

        MovimientoDia::factory()->create([
            'created_at' => now()->subDay(),
            'volumen' => 500,
        ]);

        $response = $this->getJson('/api/dashboard/tiempo-real');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'volumen_actual',
                    'flujo',
                    'temperatura',
                    'presion',
                    'actualizado_at',
                ]
            ]);

        $this->assertEquals(1800, $response->json('data.volumen_actual'));
        $this->assertEquals(25.1, $response->json('data.temperatura')); // Promedio de 25.3 y 24.9
        $this->assertEquals(50.15, $response->json('data.presion')); // Promedio de 50.5 y 49.8
    }

    /** @test */
    public function can_get_movements_chart(): void
    {
        $hoy = now();
        
        // Crear movimientos para los últimos 7 días
        for ($i = 6; $i >= 0; $i--) {
            $fecha = $hoy->copy()->subDays($i);
            
            MovimientoDia::factory()->create([
                'created_at' => $fecha,
                'volumen' => 1000 + ($i * 100),
                'tipo_movimiento' => 'RECEPCION',
            ]);
            
            MovimientoDia::factory()->create([
                'created_at' => $fecha,
                'volumen' => 800 + ($i * 80),
                'tipo_movimiento' => 'VENTA',
            ]);
        }

        $response = $this->getJson('/api/dashboard/grafica-movimientos?dias=7');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'labels',
                    'entradas',
                    'salidas',
                ]
            ]);

        $this->assertCount(7, $response->json('data.labels'));
        $this->assertCount(7, $response->json('data.entradas'));
        $this->assertCount(7, $response->json('data.salidas'));
        
        // Verificar que los datos son numéricos
        foreach ($response->json('data.entradas') as $valor) {
            $this->assertIsFloat($valor);
        }
    }

    /** @test */
    public function can_get_products_chart(): void
    {
        $producto1 = Producto::factory()->create(['nombre' => 'Gasolina Premium']);
        $producto2 = Producto::factory()->create(['nombre' => 'Diesel']);
        
        Existencia::factory()->create([
            'producto_id' => $producto1->id,
            'volumen_disponible' => 5000,
        ]);
        Existencia::factory()->create([
            'producto_id' => $producto1->id,
            'volumen_disponible' => 3000,
        ]);
        Existencia::factory()->create([
            'producto_id' => $producto2->id,
            'volumen_disponible' => 4000,
        ]);

        $response = $this->getJson('/api/dashboard/grafica-productos');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'labels',
                    'valores',
                    'colores',
                ]
            ]);

        $this->assertCount(2, $response->json('data.labels'));
        $this->assertContains('Gasolina Premium', $response->json('data.labels'));
        $this->assertContains('Diesel', $response->json('data.labels'));
        
        // Verificar los valores
        $indiceGasolina = array_search('Gasolina Premium', $response->json('data.labels'));
        $this->assertEquals(8000, $response->json('data.valores')[$indiceGasolina]);
        
        $indiceDiesel = array_search('Diesel', $response->json('data.labels'));
        $this->assertEquals(4000, $response->json('data.valores')[$indiceDiesel]);
    }

    /** @test */
    public function movements_chart_uses_default_days_parameter(): void
    {
        $response = $this->getJson('/api/dashboard/grafica-movimientos');

        $response->assertStatus(200);
        
        // Por defecto debe usar 7 días
        $this->assertCount(7, $response->json('data.labels'));
    }

    /** @test */
    public function movements_chart_handles_custom_days(): void
    {
        $response = $this->getJson('/api/dashboard/grafica-movimientos?dias=15');

        $response->assertStatus(200);
        
        $this->assertCount(15, $response->json('data.labels'));
    }

    /** @test */
    public function real_time_data_returns_zero_when_no_data(): void
    {
        // No crear movimientos del día
        $response = $this->getJson('/api/dashboard/tiempo-real');

        $response->assertStatus(200);
        
        $this->assertEquals(0, $response->json('data.volumen_actual'));
        $this->assertEquals(0, $response->json('data.flujo'));
        $this->assertEquals(0, $response->json('data.temperatura'));
        $this->assertEquals(0, $response->json('data.presion'));
    }

    /** @test */
    public function products_chart_handles_no_existencias(): void
    {
        // No crear existencias
        $response = $this->getJson('/api/dashboard/grafica-productos');

        $response->assertStatus(200);
        
        $this->assertEmpty($response->json('data.labels'));
        $this->assertEmpty($response->json('data.valores'));
    }

    /** @test */
    public function dashboard_summary_handles_no_data(): void
    {
        $response = $this->getJson('/api/dashboard/resumen');

        $response->assertStatus(200);
        
        $this->assertEquals(0, $response->json('data.contribuyentes_activos'));
        $this->assertEquals(0, $response->json('data.instalaciones_activas'));
        $this->assertEquals(0, $response->json('data.alarmas_activas'));
        $this->assertEquals(0, $response->json('data.volumen_total'));
        $this->assertEmpty($response->json('data.ultimos_movimientos'));
    }
}