<?php

namespace App\Shared\Middleware;

use App\Shared\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FranchiseScopeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        if ($user->role === UserRole::ADMIN_FRANQUICIA) {
            if (!$user->franchise_id) {
                return response()->json(['message' => 'Usuario sin franquicia asignada'], 403);
            }
            $request->attributes->set('franchise_scope', $user->franchise_id);
        } else {
            $request->attributes->set('franchise_scope', null);
        }

        return $next($request);
    }
}
