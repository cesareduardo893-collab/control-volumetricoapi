<?php

namespace Database\Factories;

use App\Models\Catalogo;
use Illuminate\Database\Eloquent\Factories\Factory;

class CatalogoFactory extends Factory
{
    protected $model = Catalogo::class;

    public function definition(): array
    {
        return [
            'nombre' => $this->faker->unique()->words(2, true),
            'clave' => $this->faker->unique()->bothify('CAT-####'),
            'descripcion' => $this->faker->optional()->sentence(),
            'activo' => true,
        ];
    }
}