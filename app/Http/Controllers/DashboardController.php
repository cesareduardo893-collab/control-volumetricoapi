<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Obtener resumen del dashboard (tarjetas y últimos movimientos) - SIN DATOS
     */
    public function resumen()
    {
        $data = [
            'contribuyentes_activos' => 0,
            'instalaciones_activas'   => 0,
            'alarmas_activas'         => 0,
            'volumen_total'           => 0,
            'ultimos_movimientos'     => [],
        ];

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * Obtener datos en tiempo real - SIN DATOS
     */
    public function tiempoReal()
    {
        $data = [
            'volumen_actual' => 0,
            'flujo'           => 0,
            'temperatura'     => 0,
            'presion'         => 0,
        ];

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }
}