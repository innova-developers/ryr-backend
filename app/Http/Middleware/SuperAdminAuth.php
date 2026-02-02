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
        // Intentar autenticar con el guard superadmin primero
        $user = $request->user('superadmin');
        
        // Si no funciona con superadmin, intentar con sanctum y verificar que sea SuperAdmin
        if (!$user) {
            $user = $request->user('sanctum');
            
            if ($user && !($user instanceof SuperAdmin)) {
                // Si es un usuario normal, no es Super Admin
                $user = null;
            }
        }
        
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