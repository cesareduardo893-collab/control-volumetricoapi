<?php

namespace Database\Factories;

use App\Models\HistorialCalibracion;
use App\Models\Tanque;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HistorialCalibracionFactory extends Factory
{
    protected $model = HistorialCalibracion::class;

    public function definition(): array
    {
        return [
            'tanque_id' => Tanque::factory(),
            'fecha_calibracion' => $this->faker->date(),
            'fecha_proxima_calibracion' => $this->faker->date('+1 year'),
            'certificado_calibracion' => $this->faker->bothify('CERT-####'),
            'entidad_calibracion' => $this->faker->company(),
            'incertidumbre_medicion' => $this->faker->optional()->randomFloat(3, 0.1, 2.0),
            'tabla_aforo' => $this->faker->optional()->randomElements([
                ['nivel' => 0, 'volumen' => 0],
                ['nivel' => 100, 'volumen' => 5000],
                ['nivel' => 200, 'volumen' => 10000],
            ]),
            'curvas_calibracion' => $this->faker->optional()->randomElements([
                ['temperatura' => 20, 'factor' => 1.0],
                ['temperatura' => 30, 'factor' => 1.002],
            ]),
            'observaciones' => $this->faker->optional()->sentence(),
            'usuario_id' => User::factory(),
        ];
    }
}