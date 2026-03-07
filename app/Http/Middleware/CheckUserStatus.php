<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return $next($request);
        }

        if ($user->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Tu cuenta ha sido eliminada.',
                'errors' => null
            ], 403);
        }

        return $next($request);
    }
}