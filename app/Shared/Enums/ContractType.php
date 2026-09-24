<?php

namespace App\Shared\Enums;

/**
 * Tipos de contratación de un empleado (users.contract_type).
 *
 * Tiene que coincidir con el ENUM de la columna en MySQL. La migración
 * 2026_05_11_124500 cambió la columna a estos tres valores (y pasó
 * commission_based a fixed_plus_commission), pero la validación del alta y la
 * edición de usuarios siguió aceptando sólo fixed_salary/commission_based: en
 * producción cualquier intento de guardar "Por Retiro" o "Fijo + Comisiones"
 * volvía 422 (RC-532). Por eso la validación ahora sale de acá.
 */
enum ContractType: string
{
    case FIXED_SALARY = 'fixed_salary';
    case PER_PICKUP = 'per_pickup';
    case FIXED_PLUS_COMMISSION = 'fixed_plus_commission';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
