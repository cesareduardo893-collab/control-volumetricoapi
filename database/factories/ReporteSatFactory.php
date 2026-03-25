<?php

namespace Database\Factories;

use App\Models\ReporteSat;
use App\Models\Instalacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ReporteSatFactory extends Factory
{
    protected $model = ReporteSat::class;

    public function definition(): array
    {
        return [
            'instalacion_id' => Instalacion::factory(),
            'usuario_genera_id' => User::factory(),
            'folio' => 'RPT-' . Str::uuid(),
            'periodo' => $this->faker->date('Y-m'),
            'tipo_reporte' => $this->faker->randomElement(['MENSUAL', 'ANUAL', 'ESPECIAL']),
            'ruta_xml' => $this->faker->optional()->filePath(),
            'ruta_pdf' => $this->faker->optional()->filePath(),
            'hash_sha256' => $this->faker->optional()->sha256(),
            'cadena_original' => $this->faker->optional()->text(),
            'sello_digital' => $this->faker->optional()->sha256(),
            'certificado_sat' => $this->faker->optional()->bothify('CERT-####'),
            'fecha_firma' => $this->faker->optional()->dateTime(),
            'datos_firma' => $this->faker->optional()->randomElements([
                'certificado' => $this->faker->bothify('CERT-####'),
                'sello' => $this->faker->sha256(),
                'fecha_firma' => $this->faker->dateTime()->format('Y-m-d H:i:s'),
            ]),
            'folio_firma' => $this->faker->optional()->uuid(),
            'estado' => $this->faker->randomElement([
                ReporteSat::ESTADO_PENDIENTE,
                ReporteSat::ESTADO_GENERADO,
                ReporteSat::ESTADO_FIRMADO,
                ReporteSat::ESTADO_ENVIADO,
                ReporteSat::ESTADO_ACEPTADO,
                ReporteSat::ESTADO_RECHAZADO
            ]),
            'fecha_generacion' => $this->faker->optional()->date(),
            'fecha_envio' => $this->faker->optional()->date(),
            'acuse_sat' => $this->faker->optional()->bothify('ACUSE-####'),
            'mensaje_respuesta' => $this->faker->optional()->sentence(),
            'detalle_respuesta' => $this->faker->optional()->randomElements([
                'codigo' => $this->faker->bothify('COD-####'),
                'mensaje' => $this->faker->sentence(),
                'fecha_respuesta' => $this->faker->dateTime()->format('Y-m-d H:i:s'),
            ]),
            'datos_reporte' => $this->faker->optional()->randomElements([
                'total_registros' => $this->faker->numberBetween(100, 10000),
                'periodo_inicio' => $this->faker->date('Y-m-d'),
                'periodo_fin' => $this->faker->date('Y-m-d'),
            ]),
            'detalle_errores' => $this->faker->optional()->randomElements([
                [
                    'codigo' => $this->faker->bothify('ERR-####'),
                    'mensaje' => $this->faker->sentence(),
                    'campo' => $this->faker->randomElement(['volumen', 'fecha', 'producto']),
                ],
            ]),
            'numero_intentos' => $this->faker->numberBetween(0, 5),
        ];
    }

    public function pendiente(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => ReporteSat::ESTADO_PENDIENTE,
        ]);
    }

    public function enviado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => ReporteSat::ESTADO_ENVIADO,
            'fecha_envio' => now(),
        ]);
    }

    public function aceptado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => ReporteSat::ESTADO_ACEPTADO,
            'fecha_envio' => now(),
        ]);
    }
}