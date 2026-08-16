<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RC-487 (BALANCE AUTOMÁTICO).
 *
 * El Balance separaba egresos ordinarios de extraordinarios buscando "extra" en el
 * NOMBRE de la categoría. En producción ninguna de las 107 categorías contiene esa
 * palabra, así que el total de extraordinarios era siempre 0 y todo caía en
 * ordinarios. Además, renombrar una categoría cambiaba el balance en silencio.
 *
 * Se reemplaza por un flag explícito que el admin controla desde el ABM.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('expense_categories', 'is_extraordinary')) {
            return;
        }

        Schema::table('expense_categories', function (Blueprint $table) {
            $table->boolean('is_extraordinary')->default(false)->after('name');
        });

        // Se preserva la clasificación que regía hasta ahora para no alterar los
        // números históricos de golpe (hoy no marca ninguna, pero si en algún entorno
        // existe una categoría con "extra" en el nombre, queda igual que antes).
        DB::table('expense_categories')
            ->where('name', 'LIKE', '%extra%')
            ->update(['is_extraordinary' => true]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('expense_categories', 'is_extraordinary')) {
            return;
        }

        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropColumn('is_extraordinary');
        });
    }
};
