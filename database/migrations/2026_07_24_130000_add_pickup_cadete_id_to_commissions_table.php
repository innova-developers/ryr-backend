<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * pickup_cadete_id = el cadete que LEVANTÓ la comisión (el primero que la tomó).
     * Se fija una sola vez y NO se sobreescribe en reasignaciones/entregas.
     * Es el cadete al que se le ACREDITA la comisión (salario/ganancias).
     * cadete_id sigue siendo el cadete que la maneja operativamente ahora.
     */
    public function up(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->foreignId('pickup_cadete_id')->nullable()->after('cadete_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropForeign(['pickup_cadete_id']);
            $table->dropColumn('pickup_cadete_id');
        });
    }
};
