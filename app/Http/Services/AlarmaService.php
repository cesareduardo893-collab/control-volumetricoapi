<?php

namespace App\Services;

use App\Models\Alarma;
use App\Models\Tanque;
use App\Models\Existencia;
use Illuminate\Support\Facades\DB;

class AlarmaService
{
    /**
     * Crea una alarma genérica.
     *
     * @param mixed $componente El modelo que origina la alarma (Tanque, Medidor, etc.)
     * @param mixed $registroAsociado El registro específico asociado (Existencia, etc.)
     * @param string $tipoAlarma Tipo de alarma (ej. 'diferencia_volumen', 'existencia_negativa')
     * @param string $gravedad (Baja, Media, Alta, Crítica)
     * @param string $descripcion
     * @return Alarma
     */
    public function crearAlarma($componente, $registroAsociado, string $tipoAlarma, string $gravedad, string $descripcion): Alarma
    {
        $componenteTipo = class_basename($componente);
        $componenteId = $componente->id;
        $componenteIdentificador = $componente->identificador ?? $componente->clave ?? $componente->numero_serie ?? 'N/A';

        $alarma = Alarma::create([
            'numero_registro' => $this->generateAlarmaNumber(),
            'fecha_hora' => now(),
            'componente_tipo' => $componenteTipo,
            'componente_id' => $componenteId,
            'componente_identificador' => $componenteIdentificador,
            'tipo_alarma' => $tipoAlarma,
            'gravedad' => $gravedad,
            'descripcion' => $descripcion,
            'atendida' => false,
        ]);

        // Opcional: Disparar un evento o notificación en tiempo real
        // event(new AlarmaCreada($alarma));

        return $alarma;
    }

    /**
     * Crea una alarma específica para diferencias volumétricas.
     *
     * @param Tanque $tanque
     * @param Existencia $existencia
     * @param float $porcentaje
     * @param float $diferencia
     * @param float $limite
     * @param string $descripcion
     * @return Alarma
     */
    public function crearAlarmaVolumetrica(Tanque $tanque, Existencia $existencia, float $porcentaje, float $diferencia, float $limite, string $descripcion): Alarma
    {
        $gravedad = $this->determinarGravedad($porcentaje, $limite);

        $alarma = $this->crearAlarma(
            $tanque,
            $existencia,
            'diferencia_volumen',
            $gravedad,
            $descripcion
        );

        // Actualizar con datos específicos
        $alarma->update([
            'diferencia_detectada' => $diferencia,
            'porcentaje_diferencia' => $porcentaje,
            'limite_permitido' => $limite,
        ]);

        return $alarma;
    }

    /**
     * Determina la gravedad basada en el porcentaje de diferencia.
     *
     * @param float $porcentaje
     * @param float $limite
     * @return string
     */
    private function determinarGravedad(float $porcentaje, float $limite): string
    {
        if ($porcentaje > $limite * 3) {
            return 'Crítica';
        } elseif ($porcentaje > $limite * 2) {
            return 'Alta';
        } elseif ($porcentaje > $limite) {
            return 'Media';
        }
        return 'Baja';
    }

    /**
     * Genera un número de alarma único y consecutivo.
     *
     * @return string
     */
    private function generateAlarmaNumber(): string
    {
        $fecha = now()->format('Ymd');
        $ultimo = Alarma::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        if ($ultimo && preg_match('/AL-(\d+)-(\d+)$/', $ultimo->numero_registro, $matches)) {
            $consecutivo = intval($matches[2]) + 1;
        } else {
            $consecutivo = 1;
        }

        return 'AL-' . $fecha . '-' . str_pad($consecutivo, 5, '0', STR_PAD_LEFT);
    }
}