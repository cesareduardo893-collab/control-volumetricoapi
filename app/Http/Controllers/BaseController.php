<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class BaseController extends Controller
{
    /**
     * Respuesta exitosa
     */
    public function success($data = null, string $message = '', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Respuesta de error
     */
    public function error(string $message, int $code = 400, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!is_null($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Respuesta no autorizada
     */
    public function unauthorized(string $message = 'No autorizado'): JsonResponse
    {
        return $this->error($message, 401);
    }

    /**
     * Respuesta prohibida
     */
    public function forbidden(string $message = 'Acceso denegado'): JsonResponse
    {
        return $this->error($message, 403);
    }

    /**
     * Respuesta no encontrada
     */
    public function notFound(string $message = 'Recurso no encontrado'): JsonResponse
    {
        return $this->error($message, 404);
    }

    /**
     * Error de validación
     */
    public function validationError($errors, string $message = 'Error de validación'): JsonResponse
    {
        return $this->error($message, 422, $errors);
    }

    /**
     * Registrar actividad en bitácora
     */
    protected function logActivity(
        ?int $usuarioId,
        string $tipoEvento,
        string $subtipoEvento,
        string $modulo,
        string $descripcion,
        ?string $tabla = null,
        ?int $registroId = null,
        $datosAnteriores = null,
        $datosNuevos = null
    ): void {
        try {
            // Generar número de registro único
            $numeroRegistro = 'B' . now()->format('YmdHis') . rand(100, 999);

            // Obtener último hash
            $ultimoHash = Bitacora::max('hash_actual');
            
            // Generar contenido para hash
            $contenido = implode('|', [
                $numeroRegistro,
                $usuarioId ?? 'SISTEMA',
                $tipoEvento,
                $subtipoEvento,
                $modulo,
                $descripcion,
                now()->toIso8601String(),
                json_encode($datosAnteriores),
                json_encode($datosNuevos),
                $ultimoHash ?? ''
            ]);
            $hashActual = hash('sha256', $contenido);

            Bitacora::create([
                'numero_registro' => $numeroRegistro,
                'usuario_id' => $usuarioId,
                'tipo_evento' => $tipoEvento,
                'subtipo_evento' => $subtipoEvento,
                'modulo' => $modulo,
                'tabla' => $tabla,
                'registro_id' => $registroId,
                'datos_anteriores' => $datosAnteriores,
                'datos_nuevos' => $datosNuevos,
                'descripcion' => $descripcion,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'dispositivo' => $this->parseUserAgent(request()->userAgent()),
                'hash_anterior' => $ultimoHash,
                'hash_actual' => $hashActual,
            ]);
        } catch (\Exception $e) {
            // Log error pero no interrumpir flujo principal
            \Log::error('Error al registrar en bitácora: ' . $e->getMessage());
        }
    }

    /**
     * Parsear user agent
     */
    protected function parseUserAgent(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        if (preg_match('/\((.*?)\)/', $userAgent, $matches)) {
            return substr($matches[1], 0, 100);
        }

        return substr($userAgent, 0, 100);
    }

    /**
     * Verificar integridad de bitácora
     */
    protected function verificarIntegridadBitacora(): array
    {
        $eventos = Bitacora::orderBy('id')->get(['id', 'numero_registro', 'hash_anterior', 'hash_actual']);
        $errores = [];
        $hashAnterior = null;

        foreach ($eventos as $evento) {
            if ($evento->hash_anterior !== $hashAnterior) {
                $errores[] = [
                    'id' => $evento->id,
                    'numero_registro' => $evento->numero_registro,
                    'error' => 'Hash anterior no coincide con el hash del registro previo',
                ];
            }
            $hashAnterior = $evento->hash_actual;
        }

        return [
            'integro' => empty($errores),
            'total_verificados' => $eventos->count(),
            'errores' => $errores,
        ];
    }
}