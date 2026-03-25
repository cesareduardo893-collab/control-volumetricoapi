<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $roles = ['Administrador', 'Supervisor', 'Operador', 'Auditor', 'Consultor'];
        
        return [
            'nombre' => $this->faker->unique()->randomElement($roles),
            'descripcion' => $this->faker->optional()->sentence(),
            'permisos_detallados' => $this->faker->optional()->randomElements([
                'crear' => $this->faker->boolean(80),
                'editar' => $this->faker->boolean(70),
                'eliminar' => $this->faker->boolean(50),
                'ver' => $this->faker->boolean(90),
            ]),
            'nivel_jerarquico' => $this->faker->numberBetween(1, 100),
            'es_administrador' => false,
            'restricciones_acceso' => $this->faker->optional()->randomElements([
                'ip_permitidas' => $this->faker->boolean(30),
                'horario_permitido' => $this->faker->boolean(40),
                'dias_permitidos' => $this->faker->boolean(50),
            ]),
            'configuracion_ui' => $this->faker->optional()->randomElements([
                'tema' => $this->faker->randomElement(['claro', 'oscuro']),
                'idioma' => $this->faker->randomElement(['es', 'en']),
                'densidad' => $this->faker->randomElement(['compacta', 'normal', 'amplia']),
            ]),
            'activo' => true,
        ];
    }

    public function administrador(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Administrador',
            'es_administrador' => true,
            'nivel_jerarquico' => 100,
        ]);
    }

    public function supervisor(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Supervisor',
            'es_administrador' => false,
            'nivel_jerarquico' => 50,
        ]);
    }

    public function operador(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Operador',
            'es_administrador' => false,
            'nivel_jerarquico' => 10,
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }
}