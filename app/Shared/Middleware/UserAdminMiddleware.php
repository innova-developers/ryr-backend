<?php

namespace App\Shared\Middleware;

class UserAdminMiddleware
{
    public function handle($request, \Closure $next)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        return $next($request);
    }
}
