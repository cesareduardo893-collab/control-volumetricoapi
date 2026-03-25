<?php

namespace Tests\Feature\Controllers;

use App\Models\Producto;
use App\Models\User;
use App\Models\Tanque;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductoControllerTest extends TestCase
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
    public function can_list_productos(): void
    {
        Producto::factory()->count(5)->create();

        $response = $this->getJson('/api/productos');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'clave_sat',
                            'codigo',
                            'nombre',
                            'tipo_hidrocarburo',
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
    public function can_filter_productos_by_tipo_hidrocarburo(): void
    {
        Producto::factory()->create(['tipo_hidrocarburo' => Producto::TIPO_HIDROCARBURO_GASOLINA]);
        Producto::factory()->create(['tipo_hidrocarburo' => Producto::TIPO_HIDROCARBURO_DIESEL]);

        $response = $this->getJson('/api/productos?tipo_hidrocarburo=' . Producto::TIPO_HIDROCARBURO_GASOLINA);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals(Producto::TIPO_HIDROCARBURO_GASOLINA, $response->json('data.data.0.tipo_hidrocarburo'));
    }

    /** @test */
    public function can_filter_productos_by_nombre(): void
    {
        Producto::factory()->create(['nombre' => 'Gasolina Premium']);
        Producto::factory()->create(['nombre' => 'Gasolina Regular']);

        $response = $this->getJson('/api/productos?nombre=Premium');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('Gasolina Premium', $response->json('data.data.0.nombre'));
    }

    /** @test */
    public function can_filter_productos_by_clave_sat(): void
    {
        Producto::factory()->create(['clave_sat' => '15101501']);
        Producto::factory()->create(['clave_sat' => '15101502']);

        $response = $this->getJson('/api/productos?clave_sat=15101501');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('15101501', $response->json('data.data.0.clave_sat'));
    }

    /** @test */
    public function can_create_producto(): void
    {
        $productoData = [
            'clave_sat' => '15101501',
            'codigo' => 'GAS-PREMIUM',
            'clave_identificacion' => 'GP-001',
            'nombre' => 'Gasolina Premium',
            'descripcion' => 'Gasolina de alto octanaje',
            'unidad_medida' => 'Litro',
            'tipo_hidrocarburo' => Producto::TIPO_HIDROCARBURO_GASOLINA,
            'densidad_api' => 87.5,
            'contenido_azufre' => 10,
            'octanaje_ron' => 92,
            'octanaje_mon' => 85,
            'indice_octano' => 88.5,
            'contiene_bioetanol' => false,
            'activo' => true,
        ];

        $response = $this->postJson('/api/productos', $productoData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Producto creado exitosamente',
            ]);

        $this->assertDatabaseHas('productos', [
            'clave_sat' => '15101501',
            'nombre' => 'Gasolina Premium',
            'tipo_hidrocarburo' => Producto::TIPO_HIDROCARBURO_GASOLINA,
        ]);
    }

    /** @test */
    public function create_producto_fails_with_duplicate_clave_sat(): void
    {
        Producto::factory()->create(['clave_sat' => '15101501']);

        $productoData = [
            'clave_sat' => '15101501',
            'codigo' => 'GAS-PREMIUM',
            'clave_identificacion' => 'GP-001',
            'nombre' => 'Gasolina Premium',
            'unidad_medida' => 'Litro',
            'tipo_hidrocarburo' => Producto::TIPO_HIDROCARBURO_GASOLINA,
        ];

        $response = $this->postJson('/api/productos', $productoData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['clave_sat']);
    }

    /** @test */
    public function create_producto_fails_with_duplicate_codigo(): void
    {
        Producto::factory()->create(['codigo' => 'GAS-PREMIUM']);

        $productoData = [
            'clave_sat' => '15101502',
            'codigo' => 'GAS-PREMIUM',
            'clave_identificacion' => 'GP-001',
            'nombre' => 'Gasolina Premium',
            'unidad_medida' => 'Litro',
            'tipo_hidrocarburo' => Producto::TIPO_HIDROCARBURO_GASOLINA,
        ];

        $response = $this->postJson('/api/productos', $productoData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['codigo']);
    }

    /** @test */
    public function create_producto_fails_with_invalid_tipo_hidrocarburo(): void
    {
        $productoData = [
            'clave_sat' => '15101501',
            'codigo' => 'GAS-PREMIUM',
            'clave_identificacion' => 'GP-001',
            'nombre' => 'Gasolina Premium',
            'unidad_medida' => 'Litro',
            'tipo_hidrocarburo' => 'INVALIDO',
        ];

        $response = $this->postJson('/api/productos', $productoData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tipo_hidrocarburo']);
    }

    /** @test */
    public function can_view_single_producto(): void
    {
        $producto = Producto::factory()->create();
        Tanque::factory()->count(2)->create(['producto_id' => $producto->id]);

        $response = $this->getJson("/api/productos/{$producto->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'clave_sat' => $producto->clave_sat,
                ]
            ]);

        $this->assertCount(2, $response->json('data.tanques'));
    }

    /** @test */
    public function can_update_producto(): void
    {
        $producto = Producto::factory()->create([
            'nombre' => 'Nombre Original',
            'densidad_api' => 85.0,
        ]);

        $updateData = [
            'nombre' => 'Nombre Actualizado',
            'densidad_api' => 90.0,
            'activo' => false,
        ];

        $response = $this->putJson("/api/productos/{$producto->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Producto actualizado exitosamente',
            ]);

        $this->assertDatabaseHas('productos', [
            'id' => $producto->id,
            'nombre' => 'Nombre Actualizado',
            'densidad_api' => 90.0,
            'activo' => false,
        ]);
    }

    /** @test */
    public function can_update_producto_especificaciones_tecnicas(): void
    {
        $producto = Producto::factory()->create();

        $updateData = [
            'especificaciones_tecnicas' => [
                'color' => 'Amarillo',
                'densidad' => 0.75,
                'viscosidad' => 2.5,
            ],
            'composicion_tipica' => [
                'hidrocarburos_aromáticos' => 35,
                'olefinas' => 10,
                'parafinas' => 55,
            ],
        ];

        $response = $this->putJson("/api/productos/{$producto->id}", $updateData);

        $response->assertStatus(200);

        $productoActualizado = Producto::find($producto->id);
        $this->assertEquals('Amarillo', $productoActualizado->especificaciones_tecnicas['color']);
        $this->assertEquals(35, $productoActualizado->composicion_tipica['hidrocarburos_aromáticos']);
    }

    /** @test */
    public function can_delete_producto(): void
    {
        $producto = Producto::factory()->create();

        $response = $this->deleteJson("/api/productos/{$producto->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Producto eliminado exitosamente',
            ]);

        $this->assertSoftDeleted('productos', [
            'id' => $producto->id,
        ]);
    }

    /** @test */
    public function cannot_delete_producto_with_associated_tanques(): void
    {
        $producto = Producto::factory()->create();
        Tanque::factory()->create(['producto_id' => $producto->id]);

        $response = $this->deleteJson("/api/productos/{$producto->id}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'No se puede eliminar el producto porque tiene 1 tanques asociados',
            ]);
    }

    /** @test */
    public function can_get_productos_by_tipo(): void
    {
        Producto::factory()->count(3)->create(['tipo_hidrocarburo' => Producto::TIPO_HIDROCARBURO_GASOLINA]);
        Producto::factory()->count(2)->create(['tipo_hidrocarburo' => Producto::TIPO_HIDROCARBURO_DIESEL]);

        $response = $this->getJson('/api/productos/tipo/' . Producto::TIPO_HIDROCARBURO_GASOLINA);

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function get_productos_by_tipo_fails_with_invalid_tipo(): void
    {
        $response = $this->getJson('/api/productos/tipo/INVALIDO');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Tipo de producto no válido',
            ]);
    }

    /** @test */
    public function can_get_catalogo_productos(): void
    {
        Producto::factory()->count(3)->create(['activo' => true]);
        Producto::factory()->create(['activo' => false]);

        $response = $this->getJson('/api/productos/catalogo');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Catálogo de productos obtenido exitosamente',
            ]);

        // Verificar que los productos activos están agrupados por tipo
        $data = $response->json('data');
        $this->assertArrayHasKey(Producto::TIPO_HIDROCARBURO_GASOLINA, $data);
    }

    /** @test */
    public function can_search_product_by_clave_sat(): void
    {
        $producto = Producto::factory()->create(['clave_sat' => '15101501', 'activo' => true]);

        $response = $this->getJson('/api/productos/buscar/clave-sat/15101501');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $producto->id,
                    'clave_sat' => '15101501',
                    'nombre' => $producto->nombre,
                ]
            ]);
    }

    /** @test */
    public function search_product_by_clave_sat_returns_404_when_not_found(): void
    {
        $response = $this->getJson('/api/productos/buscar/clave-sat/99999999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Producto no encontrado con esa clave SAT',
            ]);
    }

    /** @test */
    public function can_create_producto_with_biofuel_components(): void
    {
        $productoData = [
            'clave_sat' => '15101501',
            'codigo' => 'DIESEL-B10',
            'clave_identificacion' => 'DB-001',
            'nombre' => 'Diesel B10',
            'unidad_medida' => 'Litro',
            'tipo_hidrocarburo' => Producto::TIPO_HIDROCARBURO_DIESEL,
            'contiene_biodiesel' => true,
            'porcentaje_biodiesel' => 10,
            'contiene_bioetanol' => false,
            'densidad_api' => 35,
        ];

        $response = $this->postJson('/api/productos', $productoData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('productos', [
            'clave_sat' => '15101501',
            'contiene_biodiesel' => true,
            'porcentaje_biodiesel' => 10,
        ]);
    }

    /** @test */
    public function can_create_producto_with_gas_properties(): void
    {
        $productoData = [
            'clave_sat' => '15101502',
            'codigo' => 'GAS-NATURAL',
            'clave_identificacion' => 'GN-001',
            'nombre' => 'Gas Natural',
            'unidad_medida' => 'Metro cúbico',
            'tipo_hidrocarburo' => Producto::TIPO_HIDROCARBURO_GAS_NATURAL,
            'poder_calorifico' => 38000,
            'indice_wobbe' => 52.5,
            'clasificacion_gas' => 'L',
            'composicion_tipica' => [
                'metano' => 95,
                'etano' => 3,
                'propano' => 1,
                'butano' => 0.5,
                'otros' => 0.5,
            ],
        ];

        $response = $this->postJson('/api/productos', $productoData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('productos', [
            'clave_sat' => '15101502',
            'poder_calorifico' => 38000,
            'indice_wobbe' => 52.5,
        ]);
    }
}