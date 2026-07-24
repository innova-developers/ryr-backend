<?php

namespace App\Shared\Enums;

enum CustomerType: string
{
    case INDIVIDUAL = 'individual';
    case COMPANY = 'company';

    public function label(): string
    {
        return match ($this) {
            self::INDIVIDUAL => 'Cliente común',
            self::COMPANY => 'Empresa',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
