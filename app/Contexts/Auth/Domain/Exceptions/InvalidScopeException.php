<?php

namespace App\Contexts\Auth\Domain\Exceptions;

class InvalidScopeException extends \Exception
{
    public function __construct(string $userRole, string $scope)
    {
        $message = match ($scope) {
            'web' => "El usuario con rol '{$userRole}' no tiene permisos para acceder a la aplicación web. Solo administradores, mostradores y cadetes pueden acceder.",
            'app' => "El usuario con rol '{$userRole}' no tiene permisos para acceder a la aplicación móvil. Solo cadetes y cadetes externos pueden acceder.",
            default => "Scope '{$scope}' no válido para el rol '{$userRole}'.",
        };

        parent::__construct($message);
    }
}