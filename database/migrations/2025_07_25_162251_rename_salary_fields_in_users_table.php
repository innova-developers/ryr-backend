<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Renombrar commission_percentage a income_percentage
            $table->renameColumn('commission_percentage', 'income_percentage');
            // Agregar el nuevo campo commission_percentage
            $table->decimal('commission_percentage', 5, 2)->nullable()->after('income_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Revertir los cambios
            $table->renameColumn('income_percentage', 'commission_percentage');
            $table->dropColumn('commission_percentage');
        });
    }
};
