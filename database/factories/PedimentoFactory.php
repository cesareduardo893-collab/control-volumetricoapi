<?php

namespace Database\Factories;

use App\Models\Pedimento;
use App\Models\Contribuyente;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PedimentoFactory extends Factory
{
    protected $model = Pedimento::class;

    public function definition(): array
    {
        return [
            'numero_pedimento' => 'PED-' . Str::uuid(),
            'contribuyente_id' => Contribuyente::factory(),
            'producto_id' => Producto::factory(),
            'punto_exportacion' => $this->faker->optional()->city(),
            'punto_internacion' => $this->faker->optional()->city(),
            'pais_destino' => $this->faker->countryCode(),
            'pais_origen' => $this->faker->countryCode(),
            'medio_transporte_entrada' => $this->faker->randomElement(['MARITIMO', 'TERRESTRE', 'AEREO']),
            'medio_transporte_salida' => $this->faker->optional()->randomElement(['MARITIMO', 'TERRESTRE', 'AEREO']),
            'incoterms' => $this->faker->randomElement(['CIF', 'FOB', 'EXW']),
            'volumen' => $this->faker->randomFloat(4, 1000, 1000000),
            'unidad_medida' => $this->faker->randomElement(['Litro', 'Galón', 'Metro cúbico']),
            'valor_comercial' => $this->faker->randomFloat(4, 10000, 10000000),
            'moneda' => $this->faker->randomElement(['USD', 'MXN', 'EUR']),
            'fecha_pedimento' => $this->faker->date(),
            'fecha_arribo' => $this->faker->optional()->date(),
            'fecha_pago' => $this->faker->optional()->date(),
            'registro_volumetrico_id' => $this->faker->optional()->numberBetween(1, 100),
            'estado' => $this->faker->randomElement(['ACTIVO', 'UTILIZADO', 'CANCELADO']),
            'metadatos_aduana' => $this->faker->optional()->randomElements([
                'aduana' => $this->faker->city(),
                'agente_aduanal' => $this->faker->name(),
                'patente' => $this->faker->bothify('PAT-####'),
            ]),
        ];
    }

    public function activo(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'ACTIVO',
        ]);
    }

    public function utilizado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'UTILIZADO',
        ]);
    }
}