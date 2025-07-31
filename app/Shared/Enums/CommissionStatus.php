<?php

namespace App\Shared\Enums;

enum CommissionStatus: string
{
    case DEPOSITO = 'DEPOSITO';
    case LAS_ROSAS = 'LAS_ROSAS';
    case RETIRAR = 'RETIRAR';
    case RETIRADO = 'RETIRADO';
    case ENTREGAR = 'ENTREGAR';
    case ENTREGADO = 'ENTREGADO';
    case ENTREGAR_Y_RETIRAR = 'ENTREGAR_Y_RETIRAR';

    case PRESUPUESTO = 'PRESUPUESTO';

    case PENDIENTE = 'PENDIENTE';
    case PAGADO = 'PAGADO';
    case CANCELADO = 'CANCELADO';
    case RECHAZADO = 'RECHAZADO';
    case ACEPTADO = 'ACEPTADO';

}
