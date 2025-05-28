<?php

namespace App\Domain\Enums;

enum UserRole: string
{
    case ADMINISTRADOR = 'administrador';
    case CADETE = 'cadete';
    case MOSTRADOR = 'mostrador';
    case CLIENTE = 'cliente';
}

