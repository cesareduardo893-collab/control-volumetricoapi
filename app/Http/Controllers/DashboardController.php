<?php

namespace App\Http\Controllers;

use App\Models\Alarma;
use App\Models\Contribuyente;
use App\Models\Dispensario;
use App\Models\Existencia;
use App\Models\Instalacion;
use App\Models\Manguera;
use App\Models\Medidor;
use App\Models\MovimientoDia;
use App\Models\Producto;
use App\Models\RegistroVolumetrico;
use App\Models\Tanque;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Obtener resumen del dashboard (tarjetas y últimos movimientos)
     */
    public function resumen()
    {
        // Conteos principales
        $contribuyentesTotal = Contribuyente::withTrashed()->count();
        $contribuyentesActivos = Contribuyente::where('activo', true)->count();

        $instalacionesTotal = Instalacion::withTrashed()->count();
        $instalacionesActivas = Instalacion::where('activo', true)
            ->where('estatus', 'OPERACION')
            ->count();

        $alarmasActivas = Alarma::where('atendida', false)->count();

        $volumenTotal = DB::table('existencias')->sum('volumen_disponible') ?? 0;

        // Conteos de infraestructura
        $tanquesTotal = Tanque::withTrashed()->count();
        $tanquesOperando = Tanque::where('estado', 'OPERATIVO')->count();

        $medidoresTotal = Medidor::withTrashed()->count();
        $medidoresOperando = Medidor::where('estado', 'OPERATIVO')->count();

        $dispensariosTotal = Dispensario::withTrashed()->count();
        $dispensariosOperando = Dispensario::where('estado', 'OPERATIVO')->count();

        $manguerasTotal = Manguera::withTrashed()->count();
        $manguerasOperando = Manguera::where('estado', 'OPERATIVO')->count();

        // Registros del día
        $hoy = Carbon::today()->format('Y-m-d');
        $registrosHoy = RegistroVolumetrico::where('fecha', $hoy)->count();

        // Usuarios
        $usuariosTotal = User::withTrashed()->count();
        $usuariosActivos = User::where('activo', true)->count();

        // Últimos movimientos
        $ultimosMovimientos = RegistroVolumetrico::with(['instalacion', 'producto'])
            ->orderBy('fecha', 'desc')
            ->orderBy('hora_inicio', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($mov) {
                return [
                    'id' => $mov->id,
                    'fecha_movimiento' => $mov->fecha ? $mov->fecha->format('d/m/Y').' '.($mov->hora_inicio ?? '') : 'N/A',
                    'instalacion' => $mov->instalacion ? $mov->instalacion->nombre : 'N/A',
                    'producto' => $mov->producto ? $mov->producto->nombre : 'N/A',
                    'tipo_movimiento' => $mov->tipo_registro ?? $mov->operacion,
                    'volumen_neto' => $mov->volumen_operacion ?? $mov->volumen_corregido ?? 0,
                    'estado' => $mov->estado,
                ];
            });

        $data = [
            // Tarjetas principales
            'contribuyentes_activos' => $contribuyentesActivos,
            'contribuyentes_total' => $contribuyentesTotal,
            'instalaciones_activas' => $instalacionesActivas,
            'instalaciones_total' => $instalacionesTotal,
            'alarmas_activas' => $alarmasActivas,
            'volumen_total' => floatval($volumenTotal),

            // Infraestructura
            'tanques_total' => $tanquesTotal,
            'tanques_operando' => $tanquesOperando,
            'medidores_total' => $medidoresTotal,
            'medidores_operando' => $medidoresOperando,
            'dispensarios_total' => $dispensariosTotal,
            'dispensarios_operando' => $dispensariosOperando,
            'mangueras_total' => $manguerasTotal,
            'mangueras_operando' => $manguerasOperando,

            // Registros
            'registros_hoy' => $registrosHoy,

            // Usuarios
            'usuarios_total' => $usuariosTotal,
            'usuarios_activos' => $usuariosActivos,

            // Movimientos
            'ultimos_movimientos' => $ultimosMovimientos,
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
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
            'data' => $data,
        ]);
    }

    /**
     * Obtener gráfica de movimientos por día
     */
    public function graficaMovimientos(Request $request)
    {
        $dias = $request->get('dias', 7);
        $fechaInicio = Carbon::now()->subDays($dias)->format('Y-m-d');

        $movimientos = RegistroVolumetrico::where('fecha', '>=', $fechaInicio)
            ->orderBy('fecha')
            ->get()
            ->groupBy(function ($item) {
                return $item->fecha ? $item->fecha->format('Y-m-d') : '';
            });

        $labels = [];
        $entradas = [];
        $salidas = [];

        for ($i = $dias - 1; $i >= 0; $i--) {
            $fecha = Carbon::now()->subDays($i)->format('Y-m-d');
            $labels[] = Carbon::parse($fecha)->format('d/m');

            $data = $movimientos->get($fecha);
            $entradasTemp = $data ? $data->filter(function ($item) {
                $op = strtolower($item->operacion ?? '');

                return in_array($op, ['recepcion', 'entrada']);
            })->sum('volumen_operacion') : 0;
            $salidasTemp = $data ? $data->filter(function ($item) {
                $op = strtolower($item->operacion ?? '');

                return in_array($op, ['venta', 'entrega', 'salida']);
            })->sum('volumen_operacion') : 0;

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

        foreach ($existencias as $nombre => $items) {
            $labels[] = $nombre;
            $valores[] = floatval($items->sum('volumen_disponible'));
        }

        return response()->json([
            'success' => true,
            'data' => [
                'labels' => $labels,
                'valores' => $valores,
            ],
        ]);
    }
}
