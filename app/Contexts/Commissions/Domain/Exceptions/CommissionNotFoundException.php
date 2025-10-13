<?php

namespace App\Contexts\Commissions\Domain\Exceptions;

use Exception;

class CommissionNotFoundException extends Exception
{
    public function __construct(string $message = "Comisión no encontrada")
    {
        parent::__construct($message);
    }
}
