<?php

namespace App\Shared\Enums;

enum IvaStatus: string
{
    case AUTO = 'auto';
    case ALWAYS = 'always';
    case EXEMPT = 'exempt';

    public function label(): string
    {
        return match($this) {
            self::AUTO => 'Automático (según método de pago)',
            self::ALWAYS => 'Siempre aplica IVA',
            self::EXEMPT => 'Exento de IVA',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
