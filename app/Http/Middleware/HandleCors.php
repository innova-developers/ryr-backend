<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\HandleCors as Middleware;

class HandleCors extends Middleware
{
    protected $allowedOrigins = [
        'http://localhost:5173',
        'https://localhost:5173',
        'http://localhost:3000',
        'https://localhost:3000',
        // Agrega aquí tu dominio de producción
        'https://tu-dominio-produccion.com',
        'https://www.tu-dominio-produccion.com',
    ];

    public function handle($request, \Closure $next)
    {
        $response = $next($request);

        if ($request->isMethod('OPTIONS')) {
            $response = response('', 200);
        }

        $origin = $request->header('Origin');
        
        // Si el origen está en la lista de permitidos, lo usamos
        if (in_array($origin, $this->allowedOrigins)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        }

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');

        return $response;
    }
}
