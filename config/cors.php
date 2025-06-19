<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost:5173',
        'https://localhost:5173',
        'http://localhost:3000',
        'https://localhost:3000',
        // Agrega aquí tu dominio de producción
        'https://ryr.desarrollo.innovadevelopers.com',
        'https://www.ryr.desarrollo.innovadevelopers.com',
        // Para desarrollo, puedes permitir todos los orígenes
        // Comenta la línea de abajo en producción
        // '*',
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['*'],
    'max_age' => 0,
    'supports_credentials' => true,
]; 