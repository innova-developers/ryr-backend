<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\SuperAdmin;

class SuperAdminAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Usar auth:sanctum pero verificar que sea SuperAdmin
        $user = $request->user('sanctum');
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Verificar que el usuario sea un SuperAdmin
        if (!($user instanceof SuperAdmin)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Super Admin required.',
            ], 403);
        }

        // Verificar que el SuperAdmin esté activo
        if (!$user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Super Admin account is inactive',
            ], 403);
        }

        return $next($request);
    }
}