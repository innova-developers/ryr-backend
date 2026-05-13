<?php

namespace App\Shared\Enums;

enum FranchiseStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case TERMINATED = 'terminated';

    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'Activa',
            self::SUSPENDED => 'Suspendida',
            self::TERMINATED => 'Terminada',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
