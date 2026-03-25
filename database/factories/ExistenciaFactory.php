<?php

namespace Database\Factories;

use App\Models\Existencia;
use App\Models\Tanque;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ExistenciaFactory extends Factory
{
    protected $model = Existencia::class;

    public function definition(): array
    {
        $volumenMedido = $this->faker->randomFloat(4, 1000, 50000);
        $temperatura = $this->faker->randomFloat(2, 15, 35);
        $factorCorreccion = 1 - (($temperatura - 20) * 0.0005);
        $volumenCorregido = $volumenMedido * $factorCorreccion;

        return [
            'numero_registro' => 'EX-' . Str::uuid(),
            'tanque_id' => Tanque::factory(),
            'producto_id' => Producto::factory(),
            'fecha' => $this->faker->date(),
            'hora' => $this->faker->time(),
            'volumen_medido' => $volumenMedido,
            'temperatura' => $temperatura,
            'presion' => $this->faker->optional()->randomFloat(3, 100, 110),
            'densidad' => $this->faker->optional()->randomFloat(4, 0.7, 0.95),
            'volumen_corregido' => $volumenCorregido,
            'factor_correccion_temperatura' => $factorCorreccion,
            'factor_correccion_presion' => 1.0,
            'volumen_disponible' => $volumenCorregido,
            'volumen_agua' => $this->faker->randomFloat(4, 0, 100),
            'volumen_sedimentos' => $this->faker->randomFloat(4, 0, 50),
            'volumen_inicial_dia' => $this->faker->optional()->randomFloat(4, 0, 50000),
            'volumen_calculado' => $this->faker->optional()->randomFloat(4, 0, 50000),
            'diferencia_volumen' => $this->faker->optional()->randomFloat(4, -5000, 5000),
            'porcentaje_diferencia' => $this->faker->optional()->randomFloat(4, 0, 100),
            'detalle_calculo' => $this->faker->optional()->randomElements([
                'volumen_bruto' => $this->faker->randomFloat(4, 1000, 50000),
                'volumen_neto' => $this->faker->randomFloat(4, 1000, 50000),
                'factor_correccion' => $this->faker->randomFloat(4, 0.95, 1.05),
                'temperatura_ambiente' => $this->faker->randomFloat(2, 15, 35),
            ]),
            'tipo_registro' => $this->faker->randomElement([
                Existencia::TIPO_REGISTRO_INICIAL,
                Existencia::TIPO_REGISTRO_OPERACION,
                Existencia::TIPO_REGISTRO_FINAL
            ]),
            'tipo_movimiento' => $this->faker->randomElement([
                Existencia::TIPO_MOVIMIENTO_INICIAL,
                Existencia::TIPO_MOVIMIENTO_RECEPCION,
                Existencia::TIPO_MOVIMIENTO_ENTREGA,
                Existencia::TIPO_MOVIMIENTO_VENTA,
                Existencia::TIPO_MOVIMIENTO_TRASPASO,
                Existencia::TIPO_MOVIMIENTO_AJUSTE,
                Existencia::TIPO_MOVIMIENTO_INVENTARIO
            ]),
            'documento_referencia' => $this->faker->optional()->bothify('DOC-####'),
            'rfc_contraparte' => $this->faker->optional()->regexify('[A-Z]{3}[0-9]{9}'),
            'observaciones' => $this->faker->optional()->sentence(),
            'usuario_registro_id' => User::factory(),
            'usuario_valida_id' => $this->faker->optional()->passthrough(User::factory()),
            'fecha_validacion' => $this->faker->optional()->dateTime(),
            'estado' => $this->faker->randomElement([
                Existencia::ESTADO_PENDIENTE,
                Existencia::ESTADO_VALIDADO,
                Existencia::ESTADO_EN_REVISION,
                Existencia::ESTADO_CON_ALARMA
            ]),
        ];
    }

    public function recepcion(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_movimiento' => Existencia::TIPO_MOVIMIENTO_RECEPCION,
            'volumen_corregido' => $this->faker->randomFloat(4, 5000, 50000),
        ]);
    }

    public function venta(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_movimiento' => Existencia::TIPO_MOVIMIENTO_VENTA,
            'volumen_corregido' => $this->faker->randomFloat(4, 100, 10000),
        ]);
    }

    public function validada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => Existencia::ESTADO_VALIDADO,
            'fecha_validacion' => now(),
            'usuario_valida_id' => User::factory(),
        ]);
    }
}