<?php

return [
    App\AppServiceProvider::class,
    // TelescopeServiceProvider se registra condicionalmente en AppServiceProvider::register()
    // solo si el paquete (require-dev) está instalado. Evita romper en producción con --no-dev.
    App\Contexts\CurrentAccount\Infrastructure\Providers\CurrentAccountServiceProvider::class,
];
