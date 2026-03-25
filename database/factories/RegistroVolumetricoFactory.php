<?php

namespace Database\Factories;

use App\Models\RegistroVolumetrico;
use App\Models\Instalacion;
use App\Models\Tanque;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RegistroVolumetricoFactory extends Factory
{
    protected $model = RegistroVolumetrico::class;

    public function definition(): array
    {
        $volumenInicial = $this->faker->randomFloat(4, 10000, 50000);
        $volumenFinal = $volumenInicial - $this->faker->randomFloat(4, 100, 5000);
        $volumenOperacion = $volumenInicial - $volumenFinal;

        return [
            'numero_registro' => 'RV-' . Str::uuid(),
            'instalacion_id' => Instalacion::factory(),
            'tanque_id' => Tanque::factory(),
            'medidor_id' => $this->faker->optional()->numberBetween(1, 100),
            'producto_id' => Producto::factory(),
            'usuario_registro_id' => User::factory(),
            'usuario_valida_id' => $this->faker->optional()->passthrough(User::factory()),
            'fecha' => $this->faker->date(),
            'hora_inicio' => $this->faker->time(),
            'hora_fin' => $this->faker->time(),
            'volumen_inicial' => $volumenInicial,
            'volumen_final' => $volumenFinal,
            'volumen_operacion' => $volumenOperacion,
            'temperatura_inicial' => $this->faker->randomFloat(2, 15, 35),
            'temperatura_final' => $this->faker->randomFloat(2, 15, 35),
            'presion_inicial' => $this->faker->optional()->randomFloat(3, 100, 110),
            'presion_final' => $this->faker->optional()->randomFloat(3, 100, 110),
            'densidad' => $this->faker->randomFloat(4, 0.7, 0.95),
            'volumen_corregido' => $volumenOperacion * 0.99,
            'factor_correccion' => 0.99,
            'detalle_correccion' => $this->faker->optional()->randomElements([
                'temperatura_ambiente' => $this->faker->randomFloat(2, 15, 35),
                'factor_correccion' => $this->faker->randomFloat(4, 0.95, 1.05),
                'volumen_corregido' => $this->faker->randomFloat(4, 1000, 50000),
            ]),
            'masa' => $this->faker->optional()->randomFloat(4, 1000, 100000),
            'poder_calorifico' => $this->faker->optional()->randomFloat(4, 30000, 50000),
            'energia_total' => $this->faker->optional()->randomFloat(4, 100000, 10000000),
            'tipo_registro' => $this->faker->randomElement([
                RegistroVolumetrico::TIPO_REGISTRO_OPERACION,
                RegistroVolumetrico::TIPO_REGISTRO_ACUMULADO,
                RegistroVolumetrico::TIPO_REGISTRO_EXISTENCIAS
            ]),
            'operacion' => $this->faker->randomElement([
                RegistroVolumetrico::OPERACION_RECEPCION,
                RegistroVolumetrico::OPERACION_ENTREGA,
                RegistroVolumetrico::OPERACION_VENTA,
                RegistroVolumetrico::OPERACION_INVENTARIO_INICIAL,
                RegistroVolumetrico::OPERACION_INVENTARIO_FINAL
            ]),
            'rfc_contraparte' => $this->faker->optional()->regexify('[A-Z]{3}[0-9]{9}'),
            'documento_fiscal_uuid' => $this->faker->optional()->uuid(),
            'folio_fiscal' => $this->faker->optional()->bothify('FOL-####'),
            'tipo_cfdi' => $this->faker->optional()->randomElement(['I', 'E', 'T']),
            'dictamen_id' => $this->faker->optional()->numberBetween(1, 100),
            'estado' => $this->faker->randomElement([
                RegistroVolumetrico::ESTADO_PENDIENTE,
                RegistroVolumetrico::ESTADO_PROCESADO,
                RegistroVolumetrico::ESTADO_VALIDADO,
                RegistroVolumetrico::ESTADO_ERROR,
                RegistroVolumetrico::ESTADO_CANCELADO,
                RegistroVolumetrico::ESTADO_CON_ALARMA
            ]),
            'fecha_validacion' => $this->faker->optional()->dateTime(),
            'validaciones_realizadas' => $this->faker->optional()->randomElements([
                'volumen' => $this->faker->boolean(80),
                'temperatura' => $this->faker->boolean(90),
                'presion' => $this->faker->boolean(85),
            ]),
            'inconsistencias_detectadas' => $this->faker->optional()->randomElements([
                [
                    'tipo' => $this->faker->randomElement(['VOLUMEN', 'TEMPERATURA', 'PRESION']),
                    'descripcion' => $this->faker->sentence(),
                    'severidad' => $this->faker->randomElement(['BAJA', 'MEDIA', 'ALTA']),
                ],
            ]),
            'porcentaje_diferencia' => $this->faker->optional()->randomFloat(4, 0, 100),
            'observaciones' => $this->faker->optional()->sentence(),
            'errores' => $this->faker->optional()->randomElements([
                [
                    'codigo' => $this->faker->bothify('ERR-####'),
                    'mensaje' => $this->faker->sentence(),
                    'campo' => $this->faker->randomElement(['volumen', 'temperatura', 'presion']),
                ],
            ]),
        ];
    }

    public function recepcion(): static
    {
        return $this->state(fn (array $attributes) => [
            'operacion' => RegistroVolumetrico::OPERACION_RECEPCION,
            'volumen_operacion' => $this->faker->randomFloat(4, 5000, 50000),
        ]);
    }

    public function venta(): static
    {
        return $this->state(fn (array $attributes) => [
            'operacion' => RegistroVolumetrico::OPERACION_VENTA,
            'volumen_operacion' => $this->faker->randomFloat(4, 100, 10000),
        ]);
    }

    public function validado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => RegistroVolumetrico::ESTADO_VALIDADO,
            'fecha_validacion' => now(),
            'usuario_valida_id' => User::factory(),
        ]);
    }
}