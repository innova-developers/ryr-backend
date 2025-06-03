<?php

namespace App\Shared\Enums;

enum UserRole: string
{
    case ADMINISTRADOR = 'administrador';
    case CADETE = 'cadete';
    case MOSTRADOR = 'mostrador';
    case CLIENTE = 'cliente';
    case CADETE_EXTERNO = 'cadete_externo';

}
