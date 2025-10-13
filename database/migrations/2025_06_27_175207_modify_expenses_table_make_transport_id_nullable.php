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
        Schema::table('expenses', function (Blueprint $table) {
            // Primero eliminar la restricción foreign key existente
            $table->dropForeign(['transport_id']);
            
            // Hacer el campo nullable
            $table->foreignId('transport_id')->nullable()->change();
            
            // Agregar la nueva restricción foreign key con nullOnDelete
            $table->foreign('transport_id')->references('id')->on('transports')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // Eliminar la nueva restricción foreign key
            $table->dropForeign(['transport_id']);
            
            // Revertir a not nullable
            $table->foreignId('transport_id')->nullable(false)->change();
            
            // Restaurar la restricción original
            $table->foreign('transport_id')->references('id')->on('transports')->cascadeOnDelete();
        });
    }
};
