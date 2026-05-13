<?php

namespace App\Shared\Enums;

enum UserRole: string
{
    case ADMINISTRADOR = 'administrador';
    case CADETE = 'cadete';
    case MOSTRADOR = 'mostrador';
    case CLIENTE = 'cliente';
    case CADETE_EXTERNO = 'cadete_externo';
    case COBRADOR = 'cobrador';
    case ADMIN_FRANQUICIA = 'admin_franquicia';

    public function isMatrixAdmin(): bool
    {
        return in_array($this, [self::ADMINISTRADOR, self::MOSTRADOR]);
    }

    public function isFranchiseAdmin(): bool
    {
        return $this === self::ADMIN_FRANQUICIA;
    }

    public function canManageFranchise(): bool
    {
        return $this->isMatrixAdmin() || $this->isFranchiseAdmin();
    }
}
