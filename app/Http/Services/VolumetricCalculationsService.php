<?php

namespace App\Services;

use App\Models\Tanque;

class VolumetricCalculationsService
{
    /**
     * Calcula el volumen corregido a condiciones de referencia
     * según Anexo 21.5.1.I.d
     *
     * @param float $volumenMedido
     * @param float $temperatura
     * @param float|null $densidad
     * @param Tanque $tanque
     * @return array
     */
    public function calcularVolumenCorregido(float $volumenMedido, float $temperatura, ?float $densidad, Tanque $tanque): array
    {
        // Coeficientes de expansión térmica según tipo de producto (simplificado)
        // En un sistema real, esto vendría de tablas API o configuración
        $coeficientes = [
            'gasolina' => 0.00095,
            'diesel' => 0.00085,
            'turbosina' => 0.00090,
            'petroleo' => 0.00080,
            'default' => 0.00090
        ];

        $tipoProducto = $tanque->producto?->tipo_hidrocarburo ?? 'default';
        $coef = $coeficientes[$tipoProducto] ?? $coeficientes['default'];

        // Temperatura de referencia según el producto (Anexo 21.5.1.I.d)
        // Hidrocarburos: 15.56°C (60°F), Petrolíferos: 20°C
        $tempRef = $tanque->temperatura_referencia ?? 20.0;
        if ($tanque->producto?->es_hidrocarburo) {
            $tempRef = 15.56;
        }

        // Factor de corrección por temperatura (simplificado)
        // Fórmula: VCF = 1 / (1 + β * (T - Tref))
        $factorTemperatura = 1 / (1 + $coef * ($temperatura - $tempRef));

        // Factor de presión (simplificado, normalmente se usa 1 para líquidos)
        $factorPresion = 1;

        $volumenCorregido = $volumenMedido * $factorTemperatura * $factorPresion;

        return [
            'factor_temperatura' => round($factorTemperatura, 6),
            'factor_presion' => $factorPresion,
            'volumen_corregido' => round($volumenCorregido, 4),
        ];
    }

    /**
     * Calcula la masa a partir de volumen y densidad
     *
     * @param float $volumen
     * @param float $densidad
     * @param string $unidadVolumen
     * @param string $unidadDensidad
     * @return float
     */
    public function calcularMasa(float $volumen, float $densidad, string $unidadVolumen = 'L', string $unidadDensidad = 'kg/L'): float
    {
        // Conversiones según unidades (simplificado)
        return $volumen * $densidad;
    }

    /**
     * Calcula el factor de corrección por temperatura según API MPMS Chapter 11.1
     * (implementación real requeriría tablas API o ecuaciones complejas)
     *
     * @param float $api
     * @param float $temperatura
     * @return float
     */
    public function factorCorreccionAPITablas(float $api, float $temperatura): float
    {
        // Placeholder - en producción usar librería especializada
        return 1.0;
    }
}