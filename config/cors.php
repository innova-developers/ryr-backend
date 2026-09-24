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
    // RC-552: con 0 el navegador no guarda el preflight y cada llamada del admin
    // (www.ryrcomisiones.com -> ryrcomisiones.com, cross-origin con Authorization)
    // iba precedida de un OPTIONS: 78.136 en 24 días, el 48% del tráfico del admin,
    // cada uno con su boot de Laravel y un RTT extra (~0,1-0,15 s medido en prod).
    // Con 86400 lo reutiliza por URL+método; Chrome lo topea en 2 h, Firefox en 24 h
    // y Safari en 10 min. Simulado sobre el access log, evita ~62% de esos OPTIONS
    // (el resto son URLs que no se repiten: paginación y búsquedas).
    'max_age' => 86400,
    'supports_credentials' => true,
]; 