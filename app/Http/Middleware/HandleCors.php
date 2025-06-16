<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\HandleCors as Middleware;

class HandleCors extends Middleware
{
    protected $headers = [
        'Access-Control-Allow-Origin' => 'http://localhost:5173',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept',
        'Access-Control-Allow-Credentials' => 'true',
    ];

    public function handle($request, \Closure $next)
    {
        $response = $next($request);

        if ($request->isMethod('OPTIONS')) {
            $response = response('', 200);
        }

        foreach ($this->headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }
}
