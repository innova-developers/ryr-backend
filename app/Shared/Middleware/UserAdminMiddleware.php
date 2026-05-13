<?php

namespace App\Shared\Middleware;

use App\Shared\Enums\UserRole;

class UserAdminMiddleware
{
    public function handle($request, \Closure $next)
    {
        $user = $request->user();
        
        if (! $user) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        // Permitir acceso a administradores y mostradores
        $allowedRoles = [UserRole::ADMINISTRADOR, UserRole::MOSTRADOR, UserRole::ADMIN_FRANQUICIA];
        
        if (! in_array($user->role, $allowedRoles)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        return $next($request);
    }
}
