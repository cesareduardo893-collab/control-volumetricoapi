<?php

namespace Database\Factories;

use App\Models\MovimientoDia;
use App\Models\Existencia;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MovimientoDiaFactory extends Factory
{
    protected $model = MovimientoDia::class;

    public function definition(): array
    {
        return [
            'existencia_id' => Existencia::factory(),
            'tipo_movimiento' => $this->faker->randomElement([
                MovimientoDia::TIPO_MOVIMIENTO_RECEPCION,
                MovimientoDia::TIPO_MOVIMIENTO_VENTA,
                MovimientoDia::TIPO_MOVIMIENTO_ENTREGA,
                MovimientoDia::TIPO_MOVIMIENTO_TRASPASO,
                MovimientoDia::TIPO_MOVIMIENTO_AJUSTE
            ]),
            'volumen' => $this->faker->randomFloat(4, 100, 10000),
            'temperatura' => $this->faker->optional()->randomFloat(2, 15, 35),
            'presion' => $this->faker->optional()->randomFloat(3, 100, 110),
            'densidad' => $this->faker->optional()->randomFloat(4, 0.7, 0.95),
            'volumen_corregido' => $this->faker->randomFloat(4, 99, 9900),
            'documento_referencia' => $this->faker->optional()->bothify('DOC-####'),
            'rfc_contraparte' => $this->faker->optional()->regexify('[A-Z]{3}[0-9]{9}'),
            'observaciones' => $this->faker->optional()->sentence(),
            'usuario_id' => User::factory(),
        ];
    }
}