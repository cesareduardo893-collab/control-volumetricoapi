<?php

namespace Database\Factories;

use App\Models\Contribuyente;
use App\Models\CatalogoValor;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContribuyenteFactory extends Factory
{
    protected $model = Contribuyente::class;

    public function definition(): array
    {
        return [
            'rfc' => $this->faker->unique()->bothify('???###########'),
            'razon_social' => $this->faker->company() . ' S.A. de C.V.',
            'nombre_comercial' => $this->faker->company(),
            'regimen_fiscal' => $this->faker->randomElement([
                '601 - General de Ley',
                '603 - Personas Morales con Fines no Lucrativos',
                '605 - Sueldos y Salarios',
                '606 - Arrendamiento',
                '608 - Demás ingresos',
                '612 - Personas Físicas con Actividades Empresariales',
            ]),
            'domicilio_fiscal' => $this->faker->address(),
            'codigo_postal' => $this->faker->numerify('#####'),
            'telefono' => $this->faker->numerify('##########'),
            'email' => $this->faker->companyEmail(),
            'representante_legal' => $this->faker->name(),
            'representante_rfc' => $this->faker->bothify('???###########'),
            'caracter_actua_id' => CatalogoValor::factory(),
            'numero_permiso' => $this->faker->optional()->bothify('PERM-####'),
            'tipo_permiso' => $this->faker->optional()->randomElement([
                'Importación',
                'Exportación',
                'Almacenamiento',
                'Transporte',
            ]),
            'proveedor_equipos_rfc' => $this->faker->optional()->bothify('???###########'),
            'proveedor_equipos_nombre' => $this->faker->optional()->company(),
            'certificados_vigentes' => null,
            'ultima_verificacion' => $this->faker->optional()->dateTimeBetween('-1 year', 'now'),
            'proxima_verificacion' => $this->faker->optional()->dateTimeBetween('now', '+1 year'),
            'estatus_verificacion' => $this->faker->randomElement([
                'VIGENTE',
                'VENCIDO',
                'EN_PROCESO',
                'PENDIENTE',
            ]),
            'activo' => $this->faker->boolean(80),
            'fecha_registro' => $this->faker->dateTimeBetween('-2 years', 'now'),
        ];
    }

    public function activo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => true,
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }

    public function conVerificacionVigente(): static
    {
        return $this->state(fn (array $attributes) => [
            'ultima_verificacion' => now()->subDays(30),
            'proxima_verificacion' => now()->addDays(335),
            'estatus_verificacion' => 'VIGENTE',
        ]);
    }

    public function conVerificacionVencida(): static
    {
        return $this->state(fn (array $attributes) => [
            'ultima_verificacion' => now()->subYear(),
            'proxima_verificacion' => now()->subDays(30),
            'estatus_verificacion' => 'VENCIDO',
        ]);
    }
}