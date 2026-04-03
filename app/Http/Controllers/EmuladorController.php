<?php

namespace App\Http\Controllers;

use App\Models\Instalacion;
use App\Models\Tanque;
use Illuminate\Http\Request;

class EmuladorController extends BaseController
{
    /**
     * Obtener tanques operativos de una instalación
     */
    public function tanquesPorInstalacion($instalacionId)
    {
        try {
            $tanques = Tanque::with('producto')
                ->where('instalacion_id', $instalacionId)
                ->where('estado', 'OPERATIVO')
                ->where('activo', true)
                ->orderBy('identificador')
                ->get();

            $data = $tanques->map(function ($tanque) {
                return [
                    'id' => $tanque->id,
                    'identificador' => $tanque->identificador,
                    'numero_serie' => $tanque->numero_serie,
                    'producto' => $tanque->producto ? $tanque->producto->nombre : null,
                    'producto_id' => $tanque->producto_id,
                    'capacidad_total' => $tanque->capacidad_total,
                    'estado' => $tanque->estado,
                ];
            });

            return $this->success($data, 'Tanques obtenidos exitosamente');

        } catch (\Exception $e) {
            return $this->error('Error al obtener tanques: '.$e->getMessage(), 500);
        }
    }

    /**
     * Obtener lectura emulada de un tanque
     */
    public function lectura($tanqueId)
    {
        try {
            $tanque = Tanque::with(['producto', 'instalacion'])->find($tanqueId);

            if (! $tanque) {
                return $this->error('Tanque no encontrado', 404);
            }

            $capacidadTotal = floatval($tanque->capacidad_total);
            $capacidadUtil = floatval($tanque->capacidad_util ?? $capacidadTotal * 0.9);
            $capacidadOperativa = floatval($tanque->capacidad_operativa ?? $capacidadTotal * 0.85);
            $capacidadMinima = floatval($tanque->capacidad_minima ?? $capacidadTotal * 0.05);

            // Generar datos simulados
            $nivelPorcentaje = rand(15, 85);
            $volumenActual = ($nivelPorcentaje / 100) * $capacidadTotal;
            $volumenAnterior = $volumenActual * (1 + (rand(-50, 50) / 1000));
            $volumenCambio = $volumenActual - $volumenAnterior;

            $temperatura = 15 + (rand(0, 150) / 10);
            $presion = 1.0 + (rand(0, 50) / 100);
            $densidad = $tanque->producto ? floatval($tanque->producto->densidad ?? 0.75) : 0.75;
            $factorCorreccion = 1.0 + (($temperatura - 20) * -0.001);

            $tipoOperacion = $volumenCambio > 0 ? 'RECEPCION' : 'ENTREGA';

            $data = [
                'volumen' => round(abs($volumenActual), 3),
                'volumen_anterior' => round(abs($volumenAnterior), 3),
                'volumen_cambio' => round($volumenCambio, 3),
                'temperatura' => round($temperatura, 1),
                'presion' => round($presion, 3),
                'densidad' => round($densidad, 4),
                'factor_correccion' => round($factorCorreccion, 6),
                'nivel_porcentaje' => $nivelPorcentaje,
                'tipo_operacion' => $tipoOperacion,
                'tanque' => [
                    'id' => $tanque->id,
                    'identificador' => $tanque->identificador,
                    'producto' => $tanque->producto ? $tanque->producto->nombre : null,
                    'producto_id' => $tanque->producto_id,
                ],
                'datos_tanque' => [
                    'capacidad_total' => $capacidadTotal,
                    'capacidad_util' => $capacidadUtil,
                    'capacidad_operativa' => $capacidadOperativa,
                    'capacidad_minima' => $capacidadMinima,
                    'temperatura_referencia' => floatval($tanque->temperatura_referencia ?? 20),
                    'presion_referencia' => floatval($tanque->presion_referencia ?? 1.01325),
                ],
                'timestamp' => now()->toIso8601String(),
            ];

            return $this->success($data, 'Lectura del emulador obtenida exitosamente');

        } catch (\Exception $e) {
            return $this->error('Error al obtener lectura: '.$e->getMessage(), 500);
        }
    }

    /**
     * Obtener datos automáticos para nuevo tanque
     */
    public function datosAutomaticos()
    {
        $data = [
            'identificador' => 'T-'.strtoupper(substr(md5(mt_rand()), 0, 4)),
            'material' => 'Acero al Carbón',
            'fabricante' => 'Gilbarco Veeder-Root',
            'capacidad_total' => 50000,
            'capacidad_util' => 45000,
            'capacidad_operativa' => 42500,
            'capacidad_minima' => 2500,
            'temperatura_referencia' => 20,
            'presion_referencia' => 1.01325,
            'volumen_actual' => 0,
        ];

        return $this->success($data, 'Datos automáticos generados');
    }

    /**
     * Generar número de serie para un tanque
     */
    public function serial($instalacionId)
    {
        try {
            $instalacion = Instalacion::find($instalacionId);
            $clave = $instalacion ? $instalacion->clave_instalacion : 'XXX';
            $serial = $clave.'-'.date('Y').'-'.strtoupper(substr(md5(mt_rand()), 0, 6));

            return $this->success(['numero_serie' => $serial], 'Número de serie generado');

        } catch (\Exception $e) {
            return $this->error('Error al generar serial: '.$e->getMessage(), 500);
        }
    }

    /**
     * Simular llenado de tanque
     */
    public function simularLlenado(Request $request)
    {
        try {
            $volumenInicial = floatval($request->input('volumen_inicial', 0));
            $capacidad = 50000;
            $volumenEntrado = rand(5000, 30000);
            $volumenFinal = $volumenInicial + $volumenEntrado;
            $velocidadFlujo = rand(200, 800);

            $data = [
                'volumen_inicial' => $volumenInicial,
                'volumen_entrado' => $volumenEntrado,
                'volumen_final' => $volumenFinal,
                'velocidad_flujo' => $velocidadFlujo,
                'tiempo_estimado' => round($volumenEntrado / $velocidadFlujo, 1),
                'estado' => 'COMPLETADO',
            ];

            return $this->success($data, 'Simulación de llenado completada');

        } catch (\Exception $e) {
            return $this->error('Error en simulación: '.$e->getMessage(), 500);
        }
    }
}
