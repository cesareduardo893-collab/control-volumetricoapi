<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'identificacion' => $this->faker->unique()->numerify('ID##########'),
            'nombres' => $this->faker->firstName(),
            'apellidos' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'telefono' => $this->faker->optional()->phoneNumber(),
            'direccion' => $this->faker->optional()->address(),
            'email_verified_at' => $this->faker->optional()->dateTime(),
            'password' => Hash::make('password'),
            'login_attempts' => 0,
            'last_login_at' => $this->faker->optional()->dateTime(),
            'password_expires_at' => $this->faker->optional()->dateTime('+90 days'),
            'last_password_change' => $this->faker->optional()->dateTime(),
            'force_password_change' => false,
            'session_token' => null,
            'session_expires_at' => null,
            'last_login_ip' => $this->faker->optional()->ipv4(),
            'last_login_user_agent' => $this->faker->optional()->userAgent(),
            'dispositivos_autorizados' => $this->faker->optional()->randomElements([
                [
                    'nombre' => $this->faker->randomElement(['Windows', 'Mac', 'Linux', 'iOS', 'Android']),
                    'fecha_autorizacion' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d H:i:s'),
                    'ip' => $this->faker->ipv4(),
                ],
            ]),
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'activo' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'locked_until' => now()->addMinutes(30),
            'failed_login_attempts' => 5,
        ]);
    }

    public function passwordExpired(): static
    {
        return $this->state(fn (array $attributes) => [
            'password_expires_at' => now()->subDay(),
        ]);
    }

    public function forcePasswordChange(): static
    {
        return $this->state(fn (array $attributes) => [
            'force_password_change' => true,
        ]);
    }
}