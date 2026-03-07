<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BitacoraController extends BaseController
{
    /**
     * Listar eventos de bitácora (solo lectura)
     */
    public function index(Request $request)
    {
        // Verificar permisos (solo usuarios autorizados pueden ver bitácora)
        if (!auth()->user()->hasPermission('ver_bitacora')) {
            return $this->sendError('No tiene permisos para ver la bitácora', [], 403);
        }

        $query = Bitacora::with('usuario');

        // Filtros
        if ($request->has('fecha_inicio')) {
            $query->where('fecha', '>=', Carbon::parse($request->fecha_inicio)->startOfDay());
        }

        if ($request->has('fecha_fin')) {
            $query->where('fecha', '<=', Carbon::parse($request->fecha_fin)->endOfDay());
        }

        if ($request->has('usuario_id')) {
            $query->where('usuario_id', $request->usuario_id);
        }

        if ($request->has('categoria')) {
            $query->where('categoria', $request->categoria);
        }

        if ($request->has('tipo_evento')) {
            $query->where('tipo_evento', $request->tipo_evento);
        }

        if ($request->has('severidad')) {
            $query->where('severidad', $request->severidad);
        }

        if ($request->has('ip_origen')) {
            $query->where('ip_origen', 'LIKE', "%{$request->ip_origen}%");
        }

        if ($request->has('entidad_afectada')) {
            $query->where('entidad_afectada', $request->entidad_afectada);
        }

        if ($request->has('entidad_id')) {
            $query->where('entidad_id', $request->entidad_id);
        }

        if ($request->has('buscar')) {
            $query->where(function($q) use ($request) {
                $q->where('descripcion', 'LIKE', "%{$request->buscar}%")
                  ->orWhere('detalle', 'LIKE', "%{$request->buscar}%")
                  ->orWhere('ip_origen', 'LIKE', "%{$request->buscar}%");
            });
        }

        // Verificar integridad de la bitácora (hash chain)
        if ($request->boolean('verificar_integridad')) {
            $this->verificarIntegridadBitacora();
        }

        $bitacora = $query->orderBy('fecha', 'desc')
            ->orderBy('numero_registro', 'desc')
            ->paginate($request->get('per_page', 15));

        return $this->sendResponse($bitacora, 'Eventos de bitácora obtenidos exitosamente');
    }

    /**
     * Mostrar evento específico
     */
    public function show($id)
    {
        if (!auth()->user()->hasPermission('ver_bitacora')) {
            return $this->sendError('No tiene permisos para ver la bitácora', [], 403);
        }

        $evento = Bitacora::with('usuario')->find($id);

        if (!$evento) {
            return $this->sendError('Evento de bitácora no encontrado');
        }

        // Verificar hash del evento específico
        $hashCalculado = $this->calcularHashEvento($evento);
        if ($hashCalculado !== $evento->hash) {
            $evento->integridad_verificada = false;
            $evento->hash_calculado = $hashCalculado;
        } else {
            $evento->integridad_verificada = true;
        }

        // Verificar encadenamiento
        if ($evento->numero_registro > 1) {
            $eventoAnterior = Bitacora::where('numero_registro', $evento->numero_registro - 1)->first();
            if ($eventoAnterior) {
                $hashEsperado = hash('sha256', $eventoAnterior->hash . $evento->contenido_para_hash);
                $evento->encadenamiento_valido = ($hashEsperado === $evento->hash_anterior);
            }
        }

        return $this->sendResponse($evento, 'Evento de bitácora obtenido exitosamente');
    }

    /**
     * Exportar bitácora
     */
    public function exportar(Request $request)
    {
        if (!auth()->user()->hasPermission('exportar_bitacora')) {
            return $this->sendError('No tiene permisos para exportar la bitácora', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'formato' => 'required|in:CSV,PDF,JSON',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        $query = Bitacora::with('usuario')
            ->whereBetween('fecha', [
                Carbon::parse($request->fecha_inicio)->startOfDay(),
                Carbon::parse($request->fecha_fin)->endOfDay()
            ]);

        if ($request->has('categoria')) {
            $query->where('categoria', $request->categoria);
        }

        if ($request->has('tipo_evento')) {
            $query->where('tipo_evento', $request->tipo_evento);
        }

        $eventos = $query->orderBy('fecha')->get();

        switch ($request->formato) {
            case 'CSV':
                return $this->exportarCSV($eventos);
            case 'PDF':
                return $this->exportarPDF($eventos);
            case 'JSON':
                return $this->exportarJSON($eventos);
        }
    }

    /**
     * Obtener resumen de actividad
     */
    public function resumenActividad(Request $request)
    {
        if (!auth()->user()->hasPermission('ver_bitacora')) {
            return $this->sendError('No tiene permisos para ver la bitácora', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors()->toArray(), 422);
        }

        $eventos = Bitacora::whereBetween('fecha', [
            Carbon::parse($request->fecha_inicio)->startOfDay(),
            Carbon::parse($request->fecha_fin)->endOfDay()
        ])->get();

        $resumen = [
            'periodo' => [
                'inicio' => $request->fecha_inicio,
                'fin' => $request->fecha_fin
            ],
            'total_eventos' => $eventos->count(),
            'por_categoria' => $eventos->groupBy('categoria')
                ->map(function ($items) {
                    return [
                        'cantidad' => $items->count(),
                        'porcentaje' => round(($items->count() / max($eventos->count(), 1)) * 100, 2)
                    ];
                }),
            'por_severidad' => $eventos->groupBy('severidad')
                ->map(function ($items) {
                    return [
                        'cantidad' => $items->count(),
                        'porcentaje' => round(($items->count() / max($eventos->count(), 1)) * 100, 2)
                    ];
                }),
            'actividad_usuarios' => $eventos->groupBy('usuario_id')
                ->map(function ($items) {
                    $usuario = $items->first()->usuario;
                    return [
                        'usuario_id' => $usuario ? $usuario->id : null,
                        'usuario_nombre' => $usuario ? $usuario->name : 'Sistema',
                        'usuario_rfc' => $usuario ? $usuario->rfc : null,
                        'eventos' => $items->count(),
                        'ultimo_evento' => $items->max('fecha')
                    ];
                })->values(),
            'eventos_por_dia' => $eventos->groupBy(function ($item) {
                    return $item->fecha->format('Y-m-d');
                })
                ->map(function ($items, $fecha) {
                    return [
                        'fecha' => $fecha,
                        'total' => $items->count(),
                        'criticos' => $items->where('severidad', 'CRITICA')->count()
                    ];
                })->values()
        ];

        return $this->sendResponse($resumen, 'Resumen de actividad obtenido exitosamente');
    }

    /**
     * Verificar integridad de la bitácora
     */
    public function verificarIntegridad()
    {
        if (!auth()->user()->hasPermission('verificar_bitacora')) {
            return $this->sendError('No tiene permisos para verificar la bitácora', [], 403);
        }

        $resultado = $this->verificarIntegridadBitacora(true);

        $this->logActivity(
            auth()->id(),
            'seguridad',
            'verificacion_bitacora',
            'bitacoras',
            "Verificación de integridad de bitácora realizada. Resultado: " . ($resultado['valida'] ? 'VÁLIDA' : 'INVÁLIDA'),
            'bitacoras',
            null,
            null,
            ['resultado' => $resultado]
        );

        return $this->sendResponse($resultado, 'Verificación de integridad completada');
    }

    /**
     * Métodos privados
     */
    private function verificarIntegridadBitacora($detallada = false)
    {
        $eventos = Bitacora::orderBy('numero_registro')->get();
        $hashAnterior = null;
        $errores = [];
        $verificados = 0;

        foreach ($eventos as $evento) {
            // Verificar hash del evento
            $hashCalculado = $this->calcularHashEvento($evento);
            if ($hashCalculado !== $evento->hash) {
                $errores[] = [
                    'numero_registro' => $evento->numero_registro,
                    'tipo' => 'hash_modificado',
                    'esperado' => $evento->hash,
                    'calculado' => $hashCalculado
                ];
            }

            // Verificar encadenamiento
            if ($hashAnterior !== null) {
                $hashAnteriorEsperado = hash('sha256', $hashAnterior . $evento->contenido_para_hash);
                if ($hashAnteriorEsperado !== $evento->hash_anterior) {
                    $errores[] = [
                        'numero_registro' => $evento->numero_registro,
                        'tipo' => 'encadenamiento_roto',
                        'esperado' => $hashAnteriorEsperado,
                        'encontrado' => $evento->hash_anterior
                    ];
                }
            }

            $hashAnterior = $evento->hash;
            $verificados++;
        }

        if ($detallada) {
            return [
                'valida' => empty($errores),
                'total_verificados' => $verificados,
                'errores' => $errores,
                'fecha_verificacion' => now()->toDateTimeString()
            ];
        }

        return empty($errores);
    }

    private function calcularHashEvento($evento)
    {
        $contenido = implode('|', [
            $evento->numero_registro,
            $evento->fecha->toIso8601String(),
            $evento->usuario_id ?? 'SISTEMA',
            $evento->categoria,
            $evento->tipo_evento,
            $evento->descripcion,
            $evento->ip_origen ?? '',
            $evento->mac_address ?? '',
            $evento->entidad_afectada ?? '',
            $evento->entidad_id ?? '',
            json_encode($evento->detalle ?? []),
            $evento->created_at->toIso8601String()
        ]);

        return hash('sha256', $contenido);
    }

    private function exportarCSV($eventos)
    {
        $filename = 'bitacora_' . now()->format('Y-m-d_His') . '.csv';
        $handle = fopen('php://temp', 'w+');

        // Encabezados
        fputcsv($handle, [
            'No. Registro',
            'Fecha',
            'Usuario',
            'Categoría',
            'Tipo Evento',
            'Severidad',
            'Descripción',
            'IP Origen',
            'MAC Address',
            'Entidad Afectada',
            'Entidad ID',
            'Hash'
        ]);

        foreach ($eventos as $evento) {
            fputcsv($handle, [
                $evento->numero_registro,
                $evento->fecha->format('Y-m-d H:i:s'),
                $evento->usuario ? $evento->usuario->name : 'Sistema',
                $evento->categoria,
                $evento->tipo_evento,
                $evento->severidad,
                $evento->descripcion,
                $evento->ip_origen,
                $evento->mac_address,
                $evento->entidad_afectada,
                $evento->entidad_id,
                $evento->hash
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return response($content, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename={$filename}");
    }

    private function exportarPDF($eventos)
    {
        // Implementar generación de PDF con dompdf o similar
        $pdf = \PDF::loadView('exports.bitacora-pdf', [
            'eventos' => $eventos,
            'fecha_generacion' => now()
        ]);

        return $pdf->download('bitacora_' . now()->format('Y-m-d_His') . '.pdf');
    }

    private function exportarJSON($eventos)
    {
        return response()->json([
            'fecha_generacion' => now()->toIso8601String(),
            'total_eventos' => $eventos->count(),
            'eventos' => $eventos->map(function ($evento) {
                return [
                    'numero_registro' => $evento->numero_registro,
                    'fecha' => $evento->fecha->toIso8601String(),
                    'usuario' => $evento->usuario ? [
                        'id' => $evento->usuario->id,
                        'name' => $evento->usuario->name,
                        'rfc' => $evento->usuario->rfc
                    ] : null,
                    'categoria' => $evento->categoria,
                    'tipo_evento' => $evento->tipo_evento,
                    'severidad' => $evento->severidad,
                    'descripcion' => $evento->descripcion,
                    'ip_origen' => $evento->ip_origen,
                    'mac_address' => $evento->mac_address,
                    'entidad_afectada' => $evento->entidad_afectada,
                    'entidad_id' => $evento->entidad_id,
                    'detalle' => $evento->detalle,
                    'hash' => $evento->hash,
                    'hash_anterior' => $evento->hash_anterior
                ];
            })
        ])->header('Content-Disposition', 'attachment; filename="bitacora_' . now()->format('Y-m-d_His') . '.json"');
    }
}