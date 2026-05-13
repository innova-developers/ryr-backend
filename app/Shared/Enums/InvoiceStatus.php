<?php

namespace App\Shared\Enums;

enum InvoiceStatus: string
{
    case EMITIDA = 'emitida';
    case ANULADA = 'anulada';

    public function label(): string
    {
        return match ($this) {
            self::EMITIDA => 'Emitida',
            self::ANULADA => 'Anulada',
        };
    }
}
