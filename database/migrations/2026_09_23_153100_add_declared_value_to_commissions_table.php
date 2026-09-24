<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-531 (TARIFA ESPECIAL: VALOR DECLARADO).
 *
 * customer_rates.declared_value_percentage existe desde RC-484 y el resolver ya sabía
 * sumar "valor declarado × %", pero la comisión no tenía dónde guardar el valor
 * declarado: nadie se lo pasaba al cálculo. VETARO (3%) y BRUNORI (38%) tienen
 * tarifa con porcentaje, y el mostrador terminaba escribiendo el valor en las notas
 * y cargando a mano en el precio unitario la cuenta ya hecha.
 *
 * Nulo = la comisión no declaró valor (el caso de todas las que ya existen).
 * decimal(14,2) porque es el valor de la mercadería, no el de la comisión: en
 * producción ya aparecieron declarados de más de $4.000.000.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->decimal('declared_value', 14, 2)->nullable()->after('iva_applied');
        });
    }

    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropColumn('declared_value');
        });
    }
};
