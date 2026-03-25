<?php

namespace Database\Factories;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductoFactory extends Factory
{
    protected $model = Producto::class;

    public function definition(): array
    {
        $tipos = [
            Producto::TIPO_HIDROCARBURO_GASOLINA,
            Producto::TIPO_HIDROCARBURO_DIESEL,
            Producto::TIPO_HIDROCARBURO_GAS_NATURAL,
            Producto::TIPO_HIDROCARBURO_PETROLEO,
        ];

        return [
            'clave_sat' => $this->faker->unique()->numerify('##########'),
            'codigo' => $this->faker->unique()->bothify('PROD-####'),
            'clave_identificacion' => $this->faker->unique()->bothify('ID-####'),
            'nombre' => $this->faker->randomElement(['Gasolina Premium', 'Gasolina Regular', 'Diesel', 'Gas Natural']),
            'descripcion' => $this->faker->optional()->sentence(),
            'unidad_medida' => $this->faker->randomElement(['Litro', 'Galón', 'Metro cúbico']),
            'tipo_hidrocarburo' => $this->faker->randomElement($tipos),
            'densidad_api' => $this->faker->optional()->randomFloat(2, 30, 90),
            'contenido_azufre' => $this->faker->optional()->randomFloat(2, 0, 100),
            'clasificacion_azufre' => $this->faker->optional()->randomElement(['Bajo', 'Medio', 'Alto']),
            'clasificacion_api' => $this->faker->optional()->randomElement(['Ligero', 'Medio', 'Pesado']),
            'poder_calorifico' => $this->faker->optional()->randomFloat(4, 30000, 50000),
            'composicion_tipica' => $this->faker->optional()->randomElements([
                'metano' => $this->faker->randomFloat(2, 0, 100),
                'etano' => $this->faker->randomFloat(2, 0, 50),
                'propano' => $this->faker->randomFloat(2, 0, 30),
                'butano' => $this->faker->randomFloat(2, 0, 20),
            ]),
            'especificaciones_tecnicas' => $this->faker->optional()->randomElements([
                'color' => $this->faker->randomElement(['Amarillo', 'Azul', 'Rojo', 'Verde']),
                'densidad' => $this->faker->randomFloat(2, 0.7, 0.9),
                'viscosidad' => $this->faker->randomFloat(2, 1.0, 5.0),
            ]),
            'octanaje_ron' => $this->faker->optional()->randomFloat(2, 87, 98),
            'octanaje_mon' => $this->faker->optional()->randomFloat(2, 82, 90),
            'indice_octano' => $this->faker->optional()->randomFloat(2, 85, 94),
            'contiene_bioetanol' => $this->faker->boolean(20),
            'porcentaje_bioetanol' => $this->faker->optional()->randomFloat(2, 0, 100),
            'contiene_biodiesel' => $this->faker->boolean(20),
            'porcentaje_biodiesel' => $this->faker->optional()->randomFloat(2, 0, 100),
            'contiene_bioturbosina' => $this->faker->boolean(10),
            'porcentaje_bioturbosina' => $this->faker->optional()->randomFloat(2, 0, 100),
            'fame' => $this->faker->optional()->randomFloat(2, 0, 100),
            'porcentaje_propano' => $this->faker->optional()->randomFloat(2, 0, 100),
            'porcentaje_butano' => $this->faker->optional()->randomFloat(2, 0, 100),
            'propano_normalizado' => $this->faker->optional()->randomFloat(2, 0, 100),
            'butano_normalizado' => $this->faker->optional()->randomFloat(2, 0, 100),
            'indice_wobbe' => $this->faker->optional()->randomFloat(4, 40, 60),
            'clasificacion_gas' => $this->faker->optional()->randomElement(['L', 'M', 'H']),
            'color_identificacion' => $this->faker->optional()->hexColor(),
            'activo' => true,
        ];
    }

    public function gasolina(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_hidrocarburo' => Producto::TIPO_HIDROCARBURO_GASOLINA,
            'octanaje_ron' => $this->faker->randomFloat(2, 87, 98),
        ]);
    }

    public function diesel(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_hidrocarburo' => Producto::TIPO_HIDROCARBURO_DIESEL,
            'contenido_azufre' => $this->faker->randomFloat(2, 0, 50),
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }
}