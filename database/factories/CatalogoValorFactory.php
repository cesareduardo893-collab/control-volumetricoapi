<?php

namespace Database\Factories;

use App\Models\CatalogoValor;
use App\Models\Catalogo;
use Illuminate\Database\Eloquent\Factories\Factory;

class CatalogoValorFactory extends Factory
{
    protected $model = CatalogoValor::class;

    public function definition(): array
    {
        return [
            'catalogo_id' => Catalogo::factory(),
            'valor' => $this->faker->word(),
            'clave' => $this->faker->unique()->bothify('VAL-####'),
            'descripcion' => $this->faker->optional()->sentence(),
            'orden' => $this->faker->numberBetween(0, 100),
            'activo' => true,
        ];
    }
}