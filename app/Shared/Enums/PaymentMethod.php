<?php

namespace App\Shared\Enums;

enum PaymentMethod: string
{
    case EFECTIVO = 'EFECTIVO';
    case CHEQUE = 'CHEQUE';
    case TRANSFERENCIA = 'TRANSFERENCIA';
    case CUENTA_CORRIENTE = 'CUENTA_CORRIENTE';

    public function label(): string
    {
        return match($this) {
            self::EFECTIVO => 'Efectivo',
            self::CHEQUE => 'Cheque',
            self::TRANSFERENCIA => 'Transferencia',
            self::CUENTA_CORRIENTE => 'Cuenta Corriente',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
