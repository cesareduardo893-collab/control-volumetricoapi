<?php

namespace Database\Factories;

use App\Models\Dispensario;
use App\Models\Instalacion;
use Illuminate\Database\Eloquent\Factories\Factory;

class DispensarioFactory extends Factory
{
    protected $model = Dispensario::class;

    public function definition(): array
    {
        return [
            'instalacion_id' => Instalacion::factory(),
            'clave' => $this->faker->unique()->bothify('DIS-####'),
            'descripcion' => $this->faker->optional()->sentence(),
            'modelo' => $this->faker->optional()->bothify('MOD-####'),
            'fabricante' => $this->faker->optional()->company(),
            'estado' => $this->faker->randomElement([
                Dispensario::ESTADO_OPERATIVO,
                Dispensario::ESTADO_MANTENIMIENTO,
                Dispensario::ESTADO_FUERA_SERVICIO
            ]),
            'activo' => true,
        ];
    }

    public function operativo(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => Dispensario::ESTADO_OPERATIVO,
            'activo' => true,
        ]);
    }

    public function mantenimiento(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => Dispensario::ESTADO_MANTENIMIENTO,
        ]);
    }
}