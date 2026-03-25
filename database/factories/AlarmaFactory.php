<?php

namespace Database\Factories;

use App\Models\Alarma;
use App\Models\CatalogoValor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AlarmaFactory extends Factory
{
    protected $model = Alarma::class;

    public function definition(): array
    {
        return [
            'numero_registro' => 'AL-' . Str::uuid(),
            'fecha_hora' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'componente_tipo' => $this->faker->randomElement(['tanque', 'medidor', 'instalacion', 'manguera']),
            'componente_id' => $this->faker->optional()->numberBetween(1, 100),
            'componente_identificador' => $this->faker->bothify('COMP-####'),
            'tipo_alarma_id' => CatalogoValor::factory(),
            'gravedad' => $this->faker->randomElement([Alarma::GRAVEDAD_BAJA, Alarma::GRAVEDAD_MEDIA, Alarma::GRAVEDAD_ALTA, Alarma::GRAVEDAD_CRITICA]),
            'descripcion' => $this->faker->sentence(),
            'datos_contexto' => $this->faker->optional()->randomElements([
                'temperatura' => $this->faker->randomFloat(2, 15, 35),
                'presion' => $this->faker->randomFloat(2, 100, 150),
                'nivel' => $this->faker->randomFloat(2, 0, 100),
            ]),
            'diferencia_detectada' => $this->faker->optional()->randomFloat(4, 0, 1000),
            'porcentaje_diferencia' => $this->faker->optional()->randomFloat(4, 0, 100),
            'limite_permitido' => $this->faker->optional()->randomFloat(4, 0, 100),
            'diagnostico_automatico' => $this->faker->optional()->randomElements([
                'tipo' => $this->faker->randomElement(['FUGA', 'ALTERACION', 'ERROR_MEDICION']),
                'severidad' => $this->faker->randomElement(['BAJA', 'MEDIA', 'ALTA']),
                'descripcion' => $this->faker->sentence(),
            ]),
            'recomendaciones' => $this->faker->optional()->randomElements([
                'accion' => $this->faker->randomElement(['VERIFICAR', 'REPARAR', 'REEMPLAZAR']),
                'prioridad' => $this->faker->randomElement(['INMEDIATA', 'ALTA', 'MEDIA']),
                'plazo' => $this->faker->randomElement(['24h', '48h', '7d']),
            ]),
            'atendida' => $this->faker->boolean(30),
            'fecha_atencion' => $this->faker->optional()->dateTimeBetween('-29 days', 'now'),
            'atendida_por' => $this->faker->optional()->numberBetween(1, 10),
            'acciones_tomadas' => $this->faker->optional()->sentence(),
            'estado_atencion' => $this->faker->randomElement([
                Alarma::ESTADO_PENDIENTE,
                Alarma::ESTADO_EN_PROCESO,
                Alarma::ESTADO_RESUELTA,
                Alarma::ESTADO_IGNORADA
            ]),
            'requiere_atencion_inmediata' => $this->faker->boolean(20),
            'fecha_limite_atencion' => $this->faker->optional()->dateTimeBetween('now', '+2 days'),
            'historial_cambios_estado' => $this->faker->optional()->randomElements([
                [
                    'estado_anterior' => Alarma::ESTADO_PENDIENTE,
                    'estado_nuevo' => Alarma::ESTADO_EN_PROCESO,
                    'fecha_cambio' => $this->faker->dateTimeBetween('-5 days', 'now')->format('Y-m-d H:i:s'),
                    'usuario' => $this->faker->name(),
                ],
            ]),
        ];
    }

    public function atendida(): static
    {
        return $this->state(fn (array $attributes) => [
            'atendida' => true,
            'estado_atencion' => Alarma::ESTADO_RESUELTA,
            'fecha_atencion' => now(),
        ]);
    }

    public function critica(): static
    {
        return $this->state(fn (array $attributes) => [
            'gravedad' => Alarma::GRAVEDAD_CRITICA,
            'requiere_atencion_inmediata' => true,
        ]);
    }
}