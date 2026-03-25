<?php

namespace Database\Factories;

use App\Models\HistorialConexion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HistorialConexionFactory extends Factory
{
    protected $model = HistorialConexion::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'fecha_hora' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'dispositivo' => $this->faker->randomElement(['Windows', 'Mac', 'Linux', 'iOS', 'Android']),
            'exitosa' => $this->faker->boolean(90),
        ];
    }

    public function exitosa(): static
    {
        return $this->state(fn (array $attributes) => [
            'exitosa' => true,
        ]);
    }

    public function fallida(): static
    {
        return $this->state(fn (array $attributes) => [
            'exitosa' => false,
        ]);
    }
}