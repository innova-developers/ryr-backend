<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost:5173',
        'https://localhost:5173',
        'http://localhost:3000',
        'https://localhost:3000',
        'http://localhost:8080',
        'https://localhost:8080',
        'http://localhost:4200',
        'https://localhost:4200',
        'http://localhost:4000',
        'https://localhost:4000',
        'http://localhost:5000',
        'https://localhost:5000',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:8080',
        'http://127.0.0.1:4200',
        'http://127.0.0.1:4000',
        'http://127.0.0.1:5000',
        // Agrega aquí tu dominio de producción
        'https://ryr.desarrollo.innovadevelopers.com',
        'https://www.ryr.desarrollo.innovadevelopers.com',
        // Para desarrollo, puedes permitir todos los orígenes
        // Comenta la línea de abajo en producción
        '*',
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['*'],
    'max_age' => 0,
    'supports_credentials' => true,
]; 