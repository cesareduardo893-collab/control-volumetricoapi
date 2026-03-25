<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        $modulos = ['Usuarios', 'Roles', 'Productos', 'Tanques', 'Medidores', 'Reportes', 'Dashboard'];
        $acciones = ['crear', 'editar', 'eliminar', 'ver', 'exportar', 'importar'];
        
        $modulo = $this->faker->randomElement($modulos);
        $accion = $this->faker->randomElement($acciones);
        
        return [
            'name' => ucfirst($accion) . ' ' . $modulo,
            'slug' => strtolower($accion) . '-' . strtolower($modulo),
            'description' => $this->faker->optional()->sentence(),
            'modulo' => $modulo,
            'reglas' => $this->faker->optional()->randomElements([
                'requiere_auth' => $this->faker->boolean(80),
                'requiere_rol' => $this->faker->boolean(60),
                'requiere_permiso' => $this->faker->boolean(70),
            ]),
            'activo' => true,
        ];
    }

    public function usuarios(): static
    {
        return $this->state(fn (array $attributes) => [
            'modulo' => 'Usuarios',
        ]);
    }

    public function productos(): static
    {
        return $this->state(fn (array $attributes) => [
            'modulo' => 'Productos',
        ]);
    }

    public function reportes(): static
    {
        return $this->state(fn (array $attributes) => [
            'modulo' => 'Reportes',
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }
}