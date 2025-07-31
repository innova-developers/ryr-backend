<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ClientAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('sanctum')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Token de autenticación requerido',
            ], 401);
        }

        $user = Auth::guard('sanctum')->user();

        // Verificar que el usuario tenga rol de cliente
        if ($user->role !== 'cliente') {
            return response()->json([
                'success' => false,
                'message' => 'Acceso denegado. Solo clientes pueden acceder a este recurso.',
            ], 403);
        }

        return $next($request);
    }
}
