<?php

namespace Database\Factories;

use App\Models\Cfdi;
use App\Models\Producto;
use App\Models\RegistroVolumetrico;
use Illuminate\Database\Eloquent\Factories\Factory;

class CfdiFactory extends Factory
{
    protected $model = Cfdi::class;

    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(4, 1000, 100000);
        $iva = $subtotal * 0.16;
        $ieps = $subtotal * 0.05;
        $total = $subtotal + $iva + $ieps;

        return [
            'uuid' => $this->faker->unique()->uuid(),
            'rfc_emisor' => $this->faker->regexify('[A-Z]{3}[0-9]{9}'),
            'nombre_emisor' => $this->faker->company(),
            'rfc_receptor' => $this->faker->regexify('[A-Z]{3}[0-9]{9}'),
            'nombre_receptor' => $this->faker->company(),
            'tipo_operacion' => $this->faker->randomElement(['adquisicion', 'enajenacion', 'servicio']),
            'producto_id' => $this->faker->optional()->passthrough(Producto::factory()),
            'volumen' => $this->faker->optional()->randomFloat(4, 100, 100000),
            'unidad_medida' => $this->faker->optional()->randomElement(['Litro', 'Galón', 'Metro cúbico']),
            'precio_unitario' => $this->faker->optional()->randomFloat(4, 10, 100),
            'subtotal' => $subtotal,
            'iva' => $iva,
            'ieps' => $ieps,
            'total' => $total,
            'tipo_servicio' => $this->faker->optional()->randomElement(['TRANSPORTE', 'ALMACENAJE', 'MANIOBRA']),
            'descripcion_servicio' => $this->faker->optional()->sentence(),
            'fecha_emision' => $this->faker->dateTime(),
            'fecha_certificacion' => $this->faker->optional()->dateTime(),
            'registro_volumetrico_id' => $this->faker->optional()->passthrough(RegistroVolumetrico::factory()),
            'xml' => $this->faker->optional()->text(),
            'ruta_xml' => $this->faker->optional()->filePath(),
            'metadatos' => $this->faker->optional()->randomElements([
                'serie' => $this->faker->bothify('A-####'),
                'folio' => $this->faker->bothify('FOL-####'),
                'forma_pago' => $this->faker->randomElement(['01', '02', '03']),
                'metodo_pago' => $this->faker->randomElement(['PUE', 'PPD']),
            ]),
            'estado' => $this->faker->randomElement(['VIGENTE', 'CANCELADO']),
            'fecha_cancelacion' => $this->faker->optional()->date(),
            'uuid_relacionado' => $this->faker->optional()->uuid(),
        ];
    }

    public function vigente(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'VIGENTE',
        ]);
    }

    public function cancelado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'CANCELADO',
            'fecha_cancelacion' => now(),
        ]);
    }
}