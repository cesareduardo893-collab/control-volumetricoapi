<?php

namespace Database\Factories;

use App\Models\Bitacora;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BitacoraFactory extends Factory
{
    protected $model = Bitacora::class;

    public function definition(): array
    {
        $tiposEvento = [
            Bitacora::TIPO_EVENTO_ADMINISTRACION,
            Bitacora::TIPO_EVENTO_UCC,
            Bitacora::TIPO_EVENTO_PROGRAMAS,
            Bitacora::TIPO_EVENTO_COMUNICACION,
            Bitacora::TIPO_EVENTO_OPERACIONES,
            Bitacora::TIPO_EVENTO_VERIFICACIONES,
            Bitacora::TIPO_EVENTO_INCONSISTENCIAS,
            Bitacora::TIPO_EVENTO_SEGURIDAD,
        ];

        return [
            'numero_registro' => 'BIT-' . Str::uuid(),
            'usuario_id' => User::factory(),
            'tipo_evento' => $this->faker->randomElement($tiposEvento),
            'subtipo_evento' => $this->faker->randomElement(['CREACION', 'ACTUALIZACION', 'ELIMINACION', 'LOGIN', 'LOGOUT']),
            'modulo' => $this->faker->randomElement(['Usuarios', 'Inventarios', 'Autenticación', 'Reportes']),
            'tabla' => $this->faker->optional()->randomElement(['users', 'productos', 'tanques']),
            'registro_id' => $this->faker->optional()->numberBetween(1, 100),
            'datos_anteriores' => $this->faker->optional()->randomElements([
                'nombre' => $this->faker->name(),
                'email' => $this->faker->email(),
                'activo' => $this->faker->boolean(80),
            ]),
            'datos_nuevos' => $this->faker->optional()->randomElements([
                'nombre' => $this->faker->name(),
                'email' => $this->faker->email(),
                'activo' => $this->faker->boolean(80),
            ]),
            'descripcion' => $this->faker->sentence(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'dispositivo' => $this->faker->randomElement(['Windows', 'Mac', 'Linux', 'iOS', 'Android']),
            'metadatos_seguridad' => $this->faker->optional()->randomElements([
                'ip_origen' => $this->faker->ipv4(),
                'pais' => $this->faker->country(),
                'ciudad' => $this->faker->city(),
            ]),
            'observaciones' => $this->faker->optional()->sentence(),
            'hash_anterior' => $this->faker->optional()->sha256(),
            'hash_actual' => $this->faker->sha256(),
            'firma_digital' => $this->faker->optional()->sha256(),
        ];
    }

    public function seguridad(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_evento' => Bitacora::TIPO_EVENTO_SEGURIDAD,
            'subtipo_evento' => $this->faker->randomElement(['LOGIN', 'LOGOUT', 'CAMBIO_PASSWORD']),
        ]);
    }

    public function operaciones(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_evento' => Bitacora::TIPO_EVENTO_OPERACIONES,
            'modulo' => 'Inventarios',
        ]);
    }
}