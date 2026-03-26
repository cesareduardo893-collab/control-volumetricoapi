<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRolePermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permission  El permiso requerido (slug)
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado',
            ], 401);
        }

        // Verificar si el usuario está activo
        if (!$user->activo) {
            return response()->json([
                'success' => false,
                'message' => 'Cuenta inactiva',
            ], 403);
        }

        // Obtener roles activos del usuario
        $roles = $user->roles()
            ->wherePivot('fecha_revocacion', null)
            ->wherePivot('activo', true)
            ->get();

        if ($roles->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene roles asignados',
            ], 403);
        }

        // Verificar si algún rol tiene el permiso 'all' (Administrador)
        foreach ($roles as $role) {
            if (in_array('all', $role->permisos_detallados ?? [])) {
                return $next($request);
            }
        }

        // Verificar si algún rol tiene el permiso específico
        foreach ($roles as $role) {
            $rolePermissions = $role->permissions()
                ->where('slug', $permission)
                ->where('activo', true)
                ->exists();

            if ($rolePermissions) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'No tiene permisos para realizar esta acción',
        ], 403);
    }
}
