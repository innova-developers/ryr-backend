<?php

namespace App\Http\Middleware;

use App\Shared\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CadeteMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado'
            ], 401);
        }

        if (!in_array($user->role, [UserRole::CADETE, UserRole::CADETE_EXTERNO])) {
            return response()->json([
                'message' => 'Acceso denegado. Solo cadetes y cadetes externos pueden acceder a esta funcionalidad.',
                'user_role' => $user->role->value
            ], 403);
        }

        return $next($request);
    }
}