<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('current_accounts', function (Blueprint $table) {
            $table->string('status')->default('PENDIENTE')->after('type');
        });

        // Actualizar registros existentes: los debit quedan en OK, los credit en PENDIENTE
        DB::statement("UPDATE current_accounts SET status = CASE WHEN type = 'debit' THEN 'OK' ELSE 'PENDIENTE' END");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('current_accounts', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
