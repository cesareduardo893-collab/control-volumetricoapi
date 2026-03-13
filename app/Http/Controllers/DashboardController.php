<?php

namespace App\Http\Controllers;

use App\Models\Contribuyente;
use App\Models\Instalacion;
use App\Models\Alarma;
use App\Models\Existencia;
use App\Models\MovimientoDia;
use App\Models\Producto;
use App\Models\RegistroVolumetrico;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Obtener resumen del dashboard (tarjetas y últimos movimientos)
     */
    public function resumen()
    {
        $contribuyentesActivos = Contribuyente::where('activo', true)->count();
        
        $instalacionesActivas = Instalacion::where('activo', true)
            ->where('estatus', 'OPERACION')
            ->count();
        
        $alarmasActivas = Alarma::where('estado', 'ACTIVA')
            ->whereNull('fecha_atencion')
            ->count();
        
        $volumenTotal = Existencia::sum('volumen_disponible') ?? 0;
        
        $ultimosMovimientos = RegistroVolumetrico::with(['instalacion', 'producto'])
            ->orderBy('fecha_movimiento', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($mov) {
                return [
                    'id' => $mov->id,
                    'fecha_movimiento' => $mov->fecha_movimiento,
                    'instalacion' => $mov->instalacion ? $mov->instalacion->nombre : 'N/A',
                    'producto' => $mov->producto ? $mov->producto->nombre : 'N/A',
                    'tipo_movimiento' => $mov->tipo_movimiento,
                    'volumen_neto' => $mov->volumen_neto ?? 0,
                    'estado' => $mov->estado,
                ];
            });

        $data = [
            'contribuyentes_activos' => $contribuyentesActivos,
            'instalaciones_activas' => $instalacionesActivas,
            'alarmas_activas' => $alarmasActivas,
            'volumen_total' => floatval($volumenTotal),
            'ultimos_movimientos' => $ultimosMovimientos,
        ];

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * Obtener datos en tiempo real
     */
    public function tiempoReal()
    {
        $hoy = Carbon::today();
        
        $movimientosHoy = MovimientoDia::whereDate('created_at', $hoy)->get();
        
        $volumenTotal = $movimientosHoy->sum('volumen') ?? 0;
        $flujoPromedio = $movimientosHoy->avg('presion') ?? 0;
        $temperaturaPromedio = $movimientosHoy->avg('temperatura') ?? 0;
        $presionPromedio = $movimientosHoy->avg('presion') ?? 0;

        $data = [
            'volumen_actual' => floatval($volumenTotal),
            'flujo' => floatval($flujoPromedio),
            'temperatura' => floatval($temperaturaPromedio),
            'presion' => floatval($presionPromedio),
            'actualizado_at' => now()->toIso8601String(),
        ];

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * Obtener gráfica de movimientos por día
     */
    public function graficaMovimientos(Request $request)
    {
        $dias = $request->get('dias', 7);
        $fechaInicio = Carbon::now()->subDays($dias);
        
        $movimientos = MovimientoDia::whereDate('created_at', '>=', $fechaInicio)
            ->orderBy('created_at')
            ->get()
            ->groupBy(function ($item) {
                return Carbon::parse($item->created_at)->format('Y-m-d');
            });

        $labels = [];
        $entradas = [];
        $salidas = [];

        for ($i = $dias - 1; $i >= 0; $i--) {
            $fecha = Carbon::now()->subDays($i)->format('Y-m-d');
            $labels[] = Carbon::parse($fecha)->format('d/m');
            
            $data = $movimientos->get($fecha);
            // Sumar volumen por tipo de movimiento
            $entradasTemp = $data ? $data->whereIn('tipo_movimiento', ['RECEPCION', 'INICIAL'])->sum('volumen') : 0;
            $salidasTemp = $data ? $data->whereIn('tipo_movimiento', ['VENTA', 'ENTREGA'])->sum('volumen') : 0;
            
            $entradas[] = floatval($entradasTemp);
            $salidas[] = floatval($salidasTemp);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'labels' => $labels,
                'entradas' => $entradas,
                'salidas' => $salidas,
            ],
        ]);
    }

    /**
     * Obtener gráfica de distribución por producto
     */
    public function graficaProductos()
    {
        $existencias = Existencia::with('producto')
            ->where('volumen_disponible', '>', 0)
            ->get()
            ->groupBy(function ($item) {
                return $item->producto ? $item->producto->nombre : 'Sin producto';
            });

        $labels = [];
        $valores = [];
        $colores = [
            'rgba(255, 99, 132, 0.8)',
            'rgba(54, 162, 235, 0.8)',
            'rgba(255, 206, 86, 0.8)',
            'rgba(75, 192, 192, 0.8)',
            'rgba(153, 102, 255, 0.8)',
            'rgba(255, 159, 64, 0.8)',
        ];

        $i = 0;
        foreach ($existencias as $nombre => $items) {
            $labels[] = $nombre;
            $valores[] = floatval($items->sum('volumen_disponible'));
            $i++;
            if ($i >= count($colores)) $i = 0;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'labels' => $labels,
                'valores' => $valores,
                'colores' => array_slice($colores, 0, count($labels)),
            ],
        ]);
    }
}