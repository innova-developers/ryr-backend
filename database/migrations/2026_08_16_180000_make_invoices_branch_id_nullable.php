<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * invoices.branch_id era NOT NULL y se llenaba con la sucursal del usuario que emite.
 * Los administradores de matriz no tienen sucursal (superadmin tiene branch_id NULL),
 * así que cualquier factura emitida por ellos fallaba con "Column 'branch_id' cannot
 * be null".
 *
 * Una factura de matriz legítimamente no pertenece a ninguna sucursal, así que la
 * columna pasa a ser nullable. Igual el servicio intenta primero la sucursal de la
 * comisión y después la del cliente, para no perder el dato cuando existe.
 *
 * En MySQL se usa SQL crudo: el change() de Laravel se ejecuta sin error pero deja la
 * columna igual, así que la migración quedaría en verde sin haber hecho nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoices', 'branch_id')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE invoices MODIFY branch_id BIGINT UNSIGNED NULL');

            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('invoices', 'branch_id')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE invoices MODIFY branch_id BIGINT UNSIGNED NOT NULL');

            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable(false)->change();
        });
    }
};
