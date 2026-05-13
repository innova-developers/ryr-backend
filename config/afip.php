<?php

return [
    'cuit' => env('ARCA_CUIT', '20000000001'),
    'production' => env('ARCA_PRODUCTION', false),
    'passphrase' => env('ARCA_PASSPHRASE', 'xxxxx'),
    'mock_arca' => env('ARCA_MOCK', true),
    'punto_venta' => env('ARCA_PUNTO_VENTA', 5),
    'razon_social' => env('ARCA_RAZON_SOCIAL', 'RYR COMISIONES SRL'),
    'domicilio' => env('ARCA_DOMICILIO', 'Av. Ejemplo 1234, CABA'),
    'condicion_iva' => env('ARCA_CONDICION_IVA', 'IVA Responsable Inscripto'),
];
