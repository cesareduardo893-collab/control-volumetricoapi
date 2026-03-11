<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BitacoraController extends BaseController
{
    /**
     * Listar eventos de bitácora
     */
    public function index(Request $request)
    {
        $query = Bitacora::with(['usuario']);

        // Filtros
        if ($request->has('usuario_id')) {
            $query->where('usuario_id', $request->usuario_id);
        }

        if ($request->has('tipo_evento')) {
            $query->where('tipo_evento', $request->tipo_evento);
        }

        if ($request->has('subtipo_evento')) {
            $query->where('subtipo_evento', $request->subtipo_evento);
        }

        if ($request->has('modulo')) {
            $query->where('modulo', $request->modulo);
        }

        if ($request->has('tabla')) {
            $query->where('tabla', $request->tabla);
        }

        if ($request->has('registro_id')) {
            $query->where('registro_id', $request->registro_id);
        }

        if ($request->has('fecha_inicio')) {
            $query->where('created_at', '>=', Carbon::parse($request->fecha_inicio));
        }

        if ($request->has('fecha_fin')) {
            $query->where('created_at', '<=', Carbon::parse($request->fecha_fin));
        }

        if ($request->has('ip_address')) {
            $query->where('ip_address', 'LIKE', "%{$request->ip_address}%");
        }

        if ($request->has('numero_registro')) {
            $query->where('numero_registro', 'LIKE', "%{$request->numero_registro}%");
        }

        if ($request->has('descripcion')) {
            $query->where('descripcion', 'LIKE', "%{$request->descripcion}%");
        }

        $eventos = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return $this->success($eventos, 'Eventos de bitácora obtenidos exitosamente');
    }

    /**
     * Mostrar evento específico
     */
    public function show($id)
    {
        $evento = Bitacora::with(['usuario'])->find($id);

        if (!$evento) {
            return $this->error('Evento de bitácora no encontrado', 404);
        }

        return $this->success($evento, 'Evento de bitácora obtenido exitosamente');
    }

    /**
     * Obtener resumen de actividad
     */
    public function resumenActividad(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        $eventos = Bitacora::whereBetween('created_at', [
                Carbon::parse($request->fecha_inicio),
                Carbon::parse($request->fecha_fin)
            ])
            ->get();

        $resumen = [
            'periodo' => [
                'inicio' => $request->fecha_inicio,
                'fin' => $request->fecha_fin,
            ],
            'total_eventos' => $eventos->count(),
            'por_tipo_evento' => $eventos->groupBy('tipo_evento')
                ->map(function ($items) {
                    return [
                        'cantidad' => $items->count(),
                        'porcentaje' => round(($items->count() / max($eventos->count(), 1)) * 100, 2),
                    ];
                }),
            'por_modulo' => $eventos->groupBy('modulo')
                ->map(function ($items) {
                    return [
                        'cantidad' => $items->count(),
                        'porcentaje' => round(($items->count() / max($eventos->count(), 1)) * 100, 2),
                    ];
                }),
            'actividad_usuarios' => $eventos->groupBy('usuario_id')
                ->map(function ($items) {
                    $usuario = $items->first()->usuario;
                    return [
                        'usuario_id' => $usuario ? $usuario->id : null,
                        'usuario_nombre' => $usuario ? $usuario->nombres . ' ' . $usuario->apellidos : 'Sistema',
                        'eventos' => $items->count(),
                    ];
                })->values(),
            'tendencia_diaria' => $eventos->groupBy(function ($item) {
                    return $item->created_at->format('Y-m-d');
                })
                ->map(function ($items, $fecha) {
                    return [
                        'fecha' => $fecha,
                        'total' => $items->count(),
                    ];
                })->values(),
        ];

        return $this->success($resumen, 'Resumen de actividad obtenido exitosamente');
    }

    /**
     * Obtener actividad por usuario
     */
    public function actividadUsuario(Request $request, $usuarioId)
    {
        $usuario = User::find($usuarioId);

        if (!$usuario) {
            return $this->error('Usuario no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        $eventos = Bitacora::where('usuario_id', $usuarioId)
            ->whereBetween('created_at', [
                Carbon::parse($request->fecha_inicio),
                Carbon::parse($request->fecha_fin)
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $resumen = [
            'usuario_id' => $usuarioId,
            'usuario_nombre' => $usuario->nombres . ' ' . $usuario->apellidos,
            'periodo' => [
                'inicio' => $request->fecha_inicio,
                'fin' => $request->fecha_fin,
            ],
            'total_eventos' => $eventos->count(),
            'por_modulo' => $eventos->groupBy('modulo')
                ->map(function ($items) {
                    return $items->count();
                }),
            'por_tipo_evento' => $eventos->groupBy('tipo_evento')
                ->map(function ($items) {
                    return $items->count();
                }),
            'eventos' => $eventos,
        ];

        return $this->success($resumen, 'Actividad del usuario obtenida exitosamente');
    }

    /**
     * Obtener actividad por módulo
     */
    public function actividadModulo(Request $request, $modulo)
    {
        $validator = Validator::make($request->all(), [
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        $eventos = Bitacora::where('modulo', $modulo)
            ->whereBetween('created_at', [
                Carbon::parse($request->fecha_inicio),
                Carbon::parse($request->fecha_fin)
            ])
            ->orderBy('created_at', 'desc')
            ->with('usuario')
            ->get();

        $resumen = [
            'modulo' => $modulo,
            'periodo' => [
                'inicio' => $request->fecha_inicio,
                'fin' => $request->fecha_fin,
            ],
            'total_eventos' => $eventos->count(),
            'por_tipo_evento' => $eventos->groupBy('tipo_evento')
                ->map(function ($items) {
                    return $items->count();
                }),
            'por_usuario' => $eventos->groupBy('usuario_id')
                ->map(function ($items) {
                    $usuario = $items->first()->usuario;
                    return [
                        'usuario' => $usuario ? $usuario->nombres . ' ' . $usuario->apellidos : 'Sistema',
                        'eventos' => $items->count(),
                    ];
                })->values(),
            'eventos' => $eventos,
        ];

        return $this->success($resumen, 'Actividad del módulo obtenida exitosamente');
    }

    /**
     * Obtener actividad por tabla
     */
    public function actividadTabla(Request $request, $tabla, $registroId = null)
    {
        $validator = Validator::make($request->all(), [
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        $query = Bitacora::where('tabla', $tabla)
            ->whereBetween('created_at', [
                Carbon::parse($request->fecha_inicio),
                Carbon::parse($request->fecha_fin)
            ]);

        if ($registroId) {
            $query->where('registro_id', $registroId);
        }

        $eventos = $query->orderBy('created_at', 'desc')
            ->with('usuario')
            ->get();

        $resumen = [
            'tabla' => $tabla,
            'registro_id' => $registroId,
            'periodo' => [
                'inicio' => $request->fecha_inicio,
                'fin' => $request->fecha_fin,
            ],
            'total_eventos' => $eventos->count(),
            'por_tipo_evento' => $eventos->groupBy('subtipo_evento')
                ->map(function ($items) {
                    return $items->count();
                }),
            'eventos' => $eventos->map(function ($e) {
                return [
                    'id' => $e->id,
                    'numero_registro' => $e->numero_registro,
                    'fecha' => $e->created_at->toDateTimeString(),
                    'usuario' => $e->usuario ? $e->usuario->nombres . ' ' . $e->usuario->apellidos : 'Sistema',
                    'subtipo_evento' => $e->subtipo_evento,
                    'descripcion' => $e->descripcion,
                    'datos_anteriores' => $e->datos_anteriores,
                    'datos_nuevos' => $e->datos_nuevos,
                ];
            }),
        ];

        return $this->success($resumen, 'Actividad de la tabla obtenida exitosamente');
    }

    /**
     * Exportar bitácora (simulado)
     */
    public function exportar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'formato' => 'required|in:CSV,PDF,JSON',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        if ($validator->fails()) {
            return $this->error('Error de validación', 422, $validator->errors());
        }

        $eventos = Bitacora::whereBetween('created_at', [
                Carbon::parse($request->fecha_inicio),
                Carbon::parse($request->fecha_fin)
            ])
            ->with('usuario')
            ->orderBy('created_at')
            ->get();

        $data = [
            'generado' => now()->toDateTimeString(),
            'usuario' => auth()->user()->nombres . ' ' . auth()->user()->apellidos,
            'periodo' => [
                'inicio' => $request->fecha_inicio,
                'fin' => $request->fecha_fin,
            ],
            'total_registros' => $eventos->count(),
            'eventos' => $eventos->map(function ($e) {
                return [
                    'numero_registro' => $e->numero_registro,
                    'fecha_hora' => $e->created_at->toDateTimeString(),
                    'usuario' => $e->usuario ? $e->usuario->nombres . ' ' . $e->usuario->apellidos : 'Sistema',
                    'tipo_evento' => $e->tipo_evento,
                    'subtipo_evento' => $e->subtipo_evento,
                    'modulo' => $e->modulo,
                    'tabla' => $e->tabla,
                    'registro_id' => $e->registro_id,
                    'descripcion' => $e->descripcion,
                    'ip_address' => $e->ip_address,
                    'hash' => $e->hash_actual,
                ];
            }),
        ];

        // Simular exportación según formato
        switch ($request->formato) {
            case 'JSON':
                return response()->json($data)
                    ->header('Content-Disposition', 'attachment; filename="bitacora.json"');
            case 'CSV':
                // Simulación de CSV
                return response("CSV export simulation", 200)
                    ->header('Content-Type', 'text/csv')
                    ->header('Content-Disposition', 'attachment; filename="bitacora.csv"');
            case 'PDF':
                // Simulación de PDF
                return response("PDF export simulation", 200)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'attachment; filename="bitacora.pdf"');
            default:
                return $this->success($data, 'Exportación generada exitosamente');
        }
    }
}