<?php

namespace Database\Factories;

use App\Models\Tanque;
use App\Models\Instalacion;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

class TanqueFactory extends Factory
{
    protected $model = Tanque::class;

    public function definition(): array
    {
        $capacidadTotal = $this->faker->randomFloat(4, 10000, 100000);
        $capacidadUtil = $capacidadTotal * $this->faker->randomFloat(4, 0.8, 0.95);
        $capacidadOperativa = $capacidadUtil * $this->faker->randomFloat(4, 0.85, 0.98);

        return [
            'instalacion_id' => Instalacion::factory(),
            'producto_id' => $this->faker->optional(0.7)->passthrough(Producto::factory()),
            'numero_serie' => $this->faker->optional()->bothify('TNK-####-####'),
            'identificador' => $this->faker->unique()->bothify('TQ-####'),
            'tipo_tanque_id' => $this->faker->optional()->numberBetween(1, 10),
            'placas' => $this->faker->optional()->bothify('PLA-####'),
            'numero_economico' => $this->faker->optional()->bothify('ECO-####'),
            'modelo' => $this->faker->optional()->bothify('MOD-####'),
            'fabricante' => $this->faker->optional()->company(),
            'material' => $this->faker->randomElement(['Acero', 'Acero Inoxidable', 'Fibra de Vidrio', 'Concreto']),
            'capacidad_total' => $capacidadTotal,
            'capacidad_util' => $capacidadUtil,
            'capacidad_operativa' => $capacidadOperativa,
            'capacidad_minima' => $this->faker->randomFloat(4, 500, 5000),
            'capacidad_gas_talon' => $this->faker->optional()->randomFloat(4, 100, 2000),
            'fecha_fabricacion' => $this->faker->optional()->date(),
            'fecha_instalacion' => $this->faker->optional()->date(),
            'fecha_ultima_calibracion' => $this->faker->optional()->date(),
            'fecha_proxima_calibracion' => $this->faker->optional()->date('+1 year'),
            'certificado_calibracion' => $this->faker->optional()->bothify('CERT-####'),
            'entidad_calibracion' => $this->faker->optional()->company(),
            'incertidumbre_medicion' => $this->faker->optional()->randomFloat(3, 0.1, 2.0),
            'temperatura_referencia' => 20.00,
            'presion_referencia' => 101.325,
            'tipo_medicion' => $this->faker->randomElement([Tanque::TIPO_MEDICION_ESTATICA, Tanque::TIPO_MEDICION_DINAMICA]),
            'estado' => $this->faker->randomElement([
                Tanque::ESTADO_OPERATIVO,
                Tanque::ESTADO_MANTENIMIENTO,
                Tanque::ESTADO_CALIBRACION,
                Tanque::ESTADO_FUERA_SERVICIO
            ]),
            'tabla_aforo' => $this->faker->optional()->randomElements([
                ['nivel' => 0, 'volumen' => 0],
                ['nivel' => 100, 'volumen' => 5000],
                ['nivel' => 200, 'volumen' => 10000],
            ]),
            'curvas_calibracion' => $this->faker->optional()->randomElements([
                ['temperatura' => 20, 'factor' => 1.0],
                ['temperatura' => 30, 'factor' => 1.002],
            ]),
            'evidencias_alteracion' => $this->faker->optional()->randomElements([
                [
                    'tipo' => 'CAMBIO_PRODUCTO',
                    'fecha' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
                    'descripcion' => $this->faker->sentence(),
                ],
            ]),
            'ultima_deteccion_alteracion' => $this->faker->optional()->dateTime(),
            'alerta_alteracion' => false,
            'activo' => true,
            'observaciones' => $this->faker->optional()->sentence(),
        ];
    }

    public function operativo(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => Tanque::ESTADO_OPERATIVO,
            'activo' => true,
        ]);
    }

    public function mantenimiento(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => Tanque::ESTADO_MANTENIMIENTO,
        ]);
    }

    public function conAlerta(): static
    {
        return $this->state(fn (array $attributes) => [
            'alerta_alteracion' => true,
            'ultima_deteccion_alteracion' => now(),
        ]);
    }
}