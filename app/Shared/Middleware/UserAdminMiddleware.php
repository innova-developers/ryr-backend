<?php

namespace App\Shared\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Shared\Enums\UserRole;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UserAdminMiddleware
{
    public function handle(Request $request, Closure $next): JsonResponse
    {
        $user = $request->user();
        if (!$user || $user->role !== UserRole::ADMINISTRADOR->value) {
            throw new HttpException(403, 'No autorizado');
        }
        return $next($request);
    }
}
