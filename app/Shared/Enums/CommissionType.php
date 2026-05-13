<?php

namespace App\Shared\Enums;

enum CommissionType: string
{
    case ORDINARIA = 'ORDINARIA';
    case EXTRAORDINARIA = 'EXTRAORDINARIA';

    public function label(): string
    {
        return match ($this) {
            self::ORDINARIA => 'Ordinaria',
            self::EXTRAORDINARIA => 'Extraordinaria',
        };
    }
}
