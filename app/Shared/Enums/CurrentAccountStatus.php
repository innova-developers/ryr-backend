<?php

namespace App\Shared\Enums;

enum CurrentAccountStatus: string
{
    case PENDIENTE = 'PENDIENTE';
    case OK = 'OK';

    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::OK => 'Confirmado',
        };
    }
}
