<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // Para APIs, no redirigimos, solo devolvemos null
        // Esto hará que el middleware lance una excepción AuthenticationException
        // que será capturada por el ExceptionHandler y devuelta como JSON
        return null;
    }
}
