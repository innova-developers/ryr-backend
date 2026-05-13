<?php

namespace App\Shared\Enums;

enum InvoiceType: int
{
    case FACTURA_A = 1;
    case NOTA_DEBITO_A = 2;
    case NOTA_CREDITO_A = 3;
    case FACTURA_B = 6;
    case NOTA_DEBITO_B = 7;
    case NOTA_CREDITO_B = 8;
    case FACTURA_C = 11;
    case NOTA_DEBITO_C = 12;
    case NOTA_CREDITO_C = 13;

    public function label(): string
    {
        return match ($this) {
            self::FACTURA_A => 'Factura A',
            self::NOTA_DEBITO_A => 'Nota de Débito A',
            self::NOTA_CREDITO_A => 'Nota de Crédito A',
            self::FACTURA_B => 'Factura B',
            self::NOTA_DEBITO_B => 'Nota de Débito B',
            self::NOTA_CREDITO_B => 'Nota de Crédito B',
            self::FACTURA_C => 'Factura C',
            self::NOTA_DEBITO_C => 'Nota de Débito C',
            self::NOTA_CREDITO_C => 'Nota de Crédito C',
        };
    }

    public function letter(): string
    {
        return match ($this) {
            self::FACTURA_A, self::NOTA_DEBITO_A, self::NOTA_CREDITO_A => 'A',
            self::FACTURA_B, self::NOTA_DEBITO_B, self::NOTA_CREDITO_B => 'B',
            self::FACTURA_C, self::NOTA_DEBITO_C, self::NOTA_CREDITO_C => 'C',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::FACTURA_A => 'FA-A',
            self::NOTA_DEBITO_A => 'ND-A',
            self::NOTA_CREDITO_A => 'NC-A',
            self::FACTURA_B => 'FA-B',
            self::NOTA_DEBITO_B => 'ND-B',
            self::NOTA_CREDITO_B => 'NC-B',
            self::FACTURA_C => 'FA-C',
            self::NOTA_DEBITO_C => 'ND-C',
            self::NOTA_CREDITO_C => 'NC-C',
        };
    }

    public static function facturaTypes(): array
    {
        return [self::FACTURA_A, self::FACTURA_B, self::FACTURA_C];
    }

    public static function notaCreditoTypes(): array
    {
        return [self::NOTA_CREDITO_A, self::NOTA_CREDITO_B, self::NOTA_CREDITO_C];
    }
}
