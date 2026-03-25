<?php

namespace Database\Factories;

use App\Models\Dictamen;
use App\Models\Contribuyente;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DictamenFactory extends Factory
{
    protected $model = Dictamen::class;

    public function definition(): array
    {
        return [
            'folio' => 'DIC-' . Str::uuid(),
            'numero_lote' => 'LOTE-' . Str::uuid(),
            'contribuyente_id' => Contribuyente::factory(),
            'laboratorio_rfc' => $this->faker->regexify('[A-Z]{3}[0-9]{9}'),
            'laboratorio_nombre' => $this->faker->company(),
            'laboratorio_numero_acreditacion' => $this->faker->bothify('ACR-####'),
            'fecha_emision' => $this->faker->date(),
            'fecha_toma_muestra' => $this->faker->date(),
            'fecha_pruebas' => $this->faker->date(),
            'fecha_resultados' => $this->faker->date(),
            'instalacion_id' => $this->faker->optional()->numberBetween(1, 100),
            'ubicacion_muestra' => $this->faker->optional()->sentence(),
            'producto_id' => Producto::factory(),
            'volumen_muestra' => $this->faker->randomFloat(2, 100, 10000),
            'unidad_medida_muestra' => $this->faker->randomElement(['Litro', 'Galón', 'Metro cúbico']),
            'metodo_muestreo' => $this->faker->bothify('ASTM-####'),
            'metodo_ensayo' => $this->faker->bothify('ASTM-####'),
            'metodos_aplicados' => $this->faker->optional()->randomElements([
                'ASTM D445' => 'Viscosidad',
                'ASTM D93' => 'Punto de inflamación',
                'ASTM D86' => 'Destilación',
            ]),
            'densidad_api' => $this->faker->optional()->randomFloat(2, 30, 90),
            'azufre' => $this->faker->optional()->randomFloat(2, 0, 100),
            'clasificacion_azufre' => $this->faker->optional()->randomElement(['Bajo', 'Medio', 'Alto']),
            'clasificacion_api' => $this->faker->optional()->randomElement(['Ligero', 'Medio', 'Pesado']),
            'composicion_molar' => $this->faker->optional()->randomElements([
                'metano' => $this->faker->randomFloat(2, 0, 100),
                'etano' => $this->faker->randomFloat(2, 0, 50),
                'propano' => $this->faker->randomFloat(2, 0, 30),
            ]),
            'propiedades_fisicas' => $this->faker->optional()->randomElements([
                'densidad' => $this->faker->randomFloat(2, 0.7, 0.9),
                'viscosidad' => $this->faker->randomFloat(2, 1.0, 5.0),
                'punto_inflamacion' => $this->faker->randomFloat(2, 20, 60),
            ]),
            'propiedades_quimicas' => $this->faker->optional()->randomElements([
                'azufre' => $this->faker->randomFloat(2, 0, 100),
                'nitrogeno' => $this->faker->randomFloat(2, 0, 50),
                'oxigeno' => $this->faker->randomFloat(2, 0, 30),
            ]),
            'poder_calorifico' => $this->faker->optional()->randomFloat(4, 30000, 50000),
            'poder_calorifico_superior' => $this->faker->optional()->randomFloat(4, 31000, 51000),
            'poder_calorifico_inferior' => $this->faker->optional()->randomFloat(4, 29000, 49000),
            'octanaje_ron' => $this->faker->optional()->randomFloat(2, 87, 98),
            'octanaje_mon' => $this->faker->optional()->randomFloat(2, 82, 90),
            'indice_octano' => $this->faker->optional()->randomFloat(2, 85, 94),
            'contiene_bioetanol' => false,
            'porcentaje_bioetanol' => $this->faker->optional()->randomFloat(2, 0, 100),
            'contiene_biodiesel' => false,
            'porcentaje_biodiesel' => $this->faker->optional()->randomFloat(2, 0, 100),
            'contiene_bioturbosina' => false,
            'porcentaje_bioturbosina' => $this->faker->optional()->randomFloat(2, 0, 100),
            'fame' => $this->faker->optional()->randomFloat(2, 0, 100),
            'porcentaje_propano' => $this->faker->optional()->randomFloat(2, 0, 100),
            'porcentaje_butano' => $this->faker->optional()->randomFloat(2, 0, 100),
            'propano_normalizado' => $this->faker->optional()->randomFloat(2, 0, 100),
            'butano_normalizado' => $this->faker->optional()->randomFloat(2, 0, 100),
            'composicion_normalizada' => $this->faker->optional()->randomElements([
                'propano' => $this->faker->randomFloat(2, 0, 100),
                'butano' => $this->faker->randomFloat(2, 0, 100),
                'pentano' => $this->faker->randomFloat(2, 0, 50),
            ]),
            'archivo_pdf' => $this->faker->optional()->filePath(),
            'archivo_xml' => $this->faker->optional()->filePath(),
            'archivo_json' => $this->faker->optional()->filePath(),
            'archivos_adicionales' => $this->faker->optional()->randomElements([
                [
                    'nombre' => 'certificado.pdf',
                    'tipo' => 'application/pdf',
                    'tamaño' => $this->faker->numberBetween(1000, 10000),
                ],
            ]),
            'estado' => $this->faker->randomElement(['VIGENTE', 'CADUCADO', 'CANCELADO']),
            'observaciones' => $this->faker->optional()->sentence(),
        ];
    }

    public function vigente(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'VIGENTE',
        ]);
    }

    public function caducado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'CADUCADO',
        ]);
    }
}