<?php

namespace App\Http\Middleware;

use App\Shared\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOrCadeteMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (! $user) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        // Permitir acceso a administradores, mostradores, cadetes y cobradores
        $allowedRoles = [
            UserRole::ADMINISTRADOR, 
            UserRole::MOSTRADOR, 
            UserRole::CADETE,
            UserRole::CADETE_EXTERNO,
            UserRole::COBRADOR
        ];
        
        if (! in_array($user->role, $allowedRoles)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        return $next($request);
    }
}


