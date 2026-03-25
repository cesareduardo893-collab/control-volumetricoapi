<?php

namespace Database\Factories;

use App\Models\Manguera;
use App\Models\Dispensario;
use App\Models\Medidor;
use Illuminate\Database\Eloquent\Factories\Factory;

class MangueraFactory extends Factory
{
    protected $model = Manguera::class;

    public function definition(): array
    {
        return [
            'dispensario_id' => Dispensario::factory(),
            'clave' => $this->faker->unique()->bothify('MANG-####'),
            'descripcion' => $this->faker->optional()->sentence(),
            'medidor_id' => $this->faker->optional()->passthrough(Medidor::factory()),
            'estado' => $this->faker->randomElement([
                Manguera::ESTADO_OPERATIVO,
                Manguera::ESTADO_MANTENIMIENTO,
                Manguera::ESTADO_FUERA_SERVICIO
            ]),
            'activo' => true,
        ];
    }

    public function conMedidor(): static
    {
        return $this->state(fn (array $attributes) => [
            'medidor_id' => Medidor::factory(),
        ]);
    }

    public function operativa(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => Manguera::ESTADO_OPERATIVO,
            'activo' => true,
        ]);
    }
}