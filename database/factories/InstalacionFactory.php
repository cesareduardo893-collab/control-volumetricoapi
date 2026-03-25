<?php

namespace Database\Factories;

use App\Models\Instalacion;
use App\Models\Contribuyente;
use Illuminate\Database\Eloquent\Factories\Factory;

class InstalacionFactory extends Factory
{
    protected $model = Instalacion::class;

    public function definition(): array
    {
        return [
            'contribuyente_id' => Contribuyente::factory(),
            'clave_instalacion' => $this->faker->unique()->bothify('INST-####'),
            'nombre' => $this->faker->company() . ' ' . $this->faker->randomElement(['Norte', 'Sur', 'Centro']),
            'tipo_instalacion' => $this->faker->randomElement(['Gasolinera', 'Estación de Servicio', 'Planta de Almacenamiento']),
            'domicilio' => $this->faker->address(),
            'codigo_postal' => $this->faker->postcode(),
            'municipio' => $this->faker->city(),
            'estado' => $this->faker->state(),
            'latitud' => $this->faker->optional()->latitude(),
            'longitud' => $this->faker->optional()->longitude(),
            'telefono' => $this->faker->optional()->phoneNumber(),
            'responsable' => $this->faker->optional()->name(),
            'fecha_operacion' => $this->faker->optional()->date(),
            'estatus' => $this->faker->randomElement(['OPERACION', 'SUSPENDIDA', 'CANCELADA']),
            'configuracion_monitoreo' => $this->faker->optional()->randomElements([
                'intervalo_lectura' => $this->faker->randomElement(['5min', '10min', '15min']),
                'umbral_presion' => $this->faker->randomFloat(2, 100, 150),
                'umbral_temperatura' => $this->faker->randomFloat(2, 20, 40),
            ]),
            'parametros_volumetricos' => $this->faker->optional()->randomElements([
                'factor_correccion' => $this->faker->randomFloat(4, 0.95, 1.05),
                'temperatura_referencia' => $this->faker->randomFloat(2, 15, 25),
                'presion_referencia' => $this->faker->randomFloat(2, 100, 110),
            ]),
            'umbrales_alarma' => $this->faker->optional()->randomElements([
                'diferencia_volumen' => $this->faker->randomFloat(2, 1, 5),
                'porcentaje_diferencia' => $this->faker->randomFloat(2, 0.5, 2),
                'tiempo_respuesta' => $this->faker->randomElement(['1h', '2h', '4h']),
            ]),
            'activo' => true,
            'observaciones' => $this->faker->optional()->sentence(),
        ];
    }

    public function operacion(): static
    {
        return $this->state(fn (array $attributes) => [
            'estatus' => 'OPERACION',
            'activo' => true,
        ]);
    }

    public function suspendida(): static
    {
        return $this->state(fn (array $attributes) => [
            'estatus' => 'SUSPENDIDA',
        ]);
    }

    public function cancelada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estatus' => 'CANCELADA',
            'activo' => false,
        ]);
    }
}