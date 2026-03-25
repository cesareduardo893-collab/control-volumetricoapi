<?php

namespace Database\Factories;

use App\Models\CertificadoVerificacion;
use App\Models\Contribuyente;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CertificadoVerificacionFactory extends Factory
{
    protected $model = CertificadoVerificacion::class;

    public function definition(): array
    {
        return [
            'folio' => 'CERT-' . Str::uuid(),
            'contribuyente_id' => Contribuyente::factory(),
            'proveedor_rfc' => $this->faker->regexify('[A-Z]{3}[0-9]{9}'),
            'proveedor_nombre' => $this->faker->company(),
            'fecha_emision' => $this->faker->date(),
            'fecha_inicio_verificacion' => $this->faker->date(),
            'fecha_fin_verificacion' => $this->faker->date(),
            'resultado' => $this->faker->randomElement(['acreditado', 'no_acreditado']),
            'tabla_cumplimiento' => [
                ['requisito' => 'Documentación', 'cumple' => true],
                ['requisito' => 'Infraestructura', 'cumple' => true],
                ['requisito' => 'Operación', 'cumple' => true],
            ],
            'hallazgos' => $this->faker->optional()->randomElements([
                [
                    'tipo' => $this->faker->randomElement(['MENOR', 'MAYOR', 'CRITICO']),
                    'descripcion' => $this->faker->sentence(),
                    'requisito' => $this->faker->randomElement(['Documentación', 'Infraestructura', 'Operación']),
                ],
            ]),
            'recomendaciones_especificas' => $this->faker->optional()->randomElements([
                [
                    'area' => $this->faker->randomElement(['Documentación', 'Infraestructura', 'Operación']),
                    'recomendacion' => $this->faker->sentence(),
                    'prioridad' => $this->faker->randomElement(['ALTA', 'MEDIA', 'BAJA']),
                ],
            ]),
            'observaciones' => $this->faker->optional()->sentence(),
            'recomendaciones' => $this->faker->optional()->sentence(),
            'archivo_pdf' => $this->faker->optional()->filePath(),
            'archivo_xml' => $this->faker->optional()->filePath(),
            'archivo_json' => $this->faker->optional()->filePath(),
            'archivos_adicionales' => $this->faker->optional()->randomElements([
                [
                    'nombre' => 'evidencia.pdf',
                    'tipo' => 'application/pdf',
                    'tamaño' => $this->faker->numberBetween(1000, 10000),
                ],
            ]),
            'vigente' => true,
            'fecha_caducidad' => $this->faker->date('+1 year'),
            'requiere_verificacion_extraordinaria' => false,
        ];
    }

    public function acreditado(): static
    {
        return $this->state(fn (array $attributes) => [
            'resultado' => 'acreditado',
            'vigente' => true,
        ]);
    }

    public function noAcreditado(): static
    {
        return $this->state(fn (array $attributes) => [
            'resultado' => 'no_acreditado',
            'vigente' => false,
        ]);
    }
}