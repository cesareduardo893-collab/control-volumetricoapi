<?php

namespace Database\Factories;

use App\Models\Medidor;
use App\Models\Instalacion;
use App\Models\Tanque;
use Illuminate\Database\Eloquent\Factories\Factory;

class MedidorFactory extends Factory
{
    protected $model = Medidor::class;

    public function definition(): array
    {
        return [
            'tanque_id' => $this->faker->optional()->passthrough(Tanque::factory()),
            'instalacion_id' => Instalacion::factory(),
            'numero_serie' => $this->faker->unique()->bothify('MD-####-####'),
            'clave' => $this->faker->unique()->bothify('MED-####'),
            'modelo' => $this->faker->optional()->bothify('MOD-####'),
            'fabricante' => $this->faker->optional()->company(),
            'elemento_tipo' => $this->faker->randomElement([
                Medidor::ELEMENTO_TIPO_PRIMARIO,
                Medidor::ELEMENTO_TIPO_SECUNDARIO,
                Medidor::ELEMENTO_TIPO_TERCIARIO
            ]),
            'tipo_medicion' => $this->faker->randomElement([
                Medidor::TIPO_MEDICION_ESTATICA,
                Medidor::TIPO_MEDICION_DINAMICA
            ]),
            'tecnologia_id' => $this->faker->optional()->numberBetween(1, 10),
            'precision' => $this->faker->randomFloat(3, 0.1, 2.0),
            'repetibilidad' => $this->faker->optional()->randomFloat(3, 0.05, 1.0),
            'capacidad_maxima' => $this->faker->randomFloat(4, 1000, 100000),
            'capacidad_minima' => $this->faker->optional()->randomFloat(4, 10, 100),
            'fecha_instalacion' => $this->faker->optional()->date(),
            'ubicacion_fisica' => $this->faker->optional()->sentence(),
            'fecha_ultima_calibracion' => $this->faker->optional()->date(),
            'fecha_proxima_calibracion' => $this->faker->optional()->date('+1 year'),
            'certificado_calibracion' => $this->faker->optional()->bothify('CERT-####'),
            'laboratorio_calibracion' => $this->faker->optional()->company(),
            'incertidumbre_calibracion' => $this->faker->optional()->randomFloat(3, 0.1, 1.0),
            'protocolo_comunicacion' => $this->faker->optional()->randomElement(['modbus', 'opc', 'serial', 'ethernet']),
            'direccion_ip' => $this->faker->optional()->ipv4(),
            'puerto_comunicacion' => $this->faker->optional()->numberBetween(1024, 65535),
            'parametros_conexion' => $this->faker->optional()->randomElements([
                'timeout' => $this->faker->numberBetween(1000, 5000),
                'reintentos' => $this->faker->numberBetween(1, 5),
                'intervalo_lectura' => $this->faker->randomElement(['1s', '5s', '10s']),
            ]),
            'mecanismos_seguridad' => $this->faker->optional()->randomElements([
                'autenticacion' => $this->faker->randomElement(['basica', 'digest', 'token']),
                'encriptacion' => $this->faker->randomElement(['AES-128', 'AES-256', 'DES']),
                'certificado' => $this->faker->boolean(50),
            ]),
            'evidencias_alteracion' => $this->faker->optional()->randomElements([
                [
                    'tipo' => 'DESCONEXION',
                    'fecha' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d H:i:s'),
                    'duracion' => $this->faker->numberBetween(1, 60) . ' minutos',
                ],
            ]),
            'ultima_deteccion_alteracion' => $this->faker->optional()->dateTime(),
            'alerta_alteracion' => false,
            'historial_desconexiones' => $this->faker->optional()->randomElements([
                [
                    'fecha' => $this->faker->dateTimeBetween('-7 days', 'now')->format('Y-m-d H:i:s'),
                    'tipo' => 'DESCONEXION',
                    'mensaje' => 'Timeout de comunicación',
                    'duracion' => $this->faker->numberBetween(1, 30) . ' minutos',
                ],
            ]),
            'estado' => $this->faker->randomElement([
                Medidor::ESTADO_OPERATIVO,
                Medidor::ESTADO_CALIBRACION,
                Medidor::ESTADO_MANTENIMIENTO,
                Medidor::ESTADO_FUERA_SERVICIO,
                Medidor::ESTADO_FALLA_COMUNICACION
            ]),
            'activo' => true,
            'observaciones' => $this->faker->optional()->sentence(),
        ];
    }

    public function operativo(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => Medidor::ESTADO_OPERATIVO,
            'activo' => true,
        ]);
    }

    public function conFallaComunicacion(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => Medidor::ESTADO_FALLA_COMUNICACION,
            'historial_desconexiones' => [
                [
                    'fecha' => now()->subHours(2),
                    'tipo' => 'DESCONEXION',
                    'mensaje' => 'Timeout de comunicación',
                ]
            ],
        ]);
    }
}