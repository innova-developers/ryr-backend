<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Historial de "manos": una fila por cada tramo de custodia de un cadete
     * sobre una comisión. Registra CADA mano por la que pasó la comisión.
     *   - action: LEVANTO (primer cadete que la tomó), REASIGNADO (traspaso a otro
     *     cadete), DEVUELTO (soltada de nuevo al pool).
     *   - assigned_at: cuándo tomó la comisión. released_at: cuándo la soltó/traspasó
     *     (null mientras la tiene).
     */
    public function up(): void
    {
        Schema::create('commission_cadete_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_id')->constrained('commissions')->cascadeOnDelete();
            $table->foreignId('cadete_id')->constrained('users')->cascadeOnDelete();
            $table->string('action')->default('LEVANTO'); // LEVANTO | REASIGNADO | DEVUELTO
            $table->unsignedBigInteger('assigned_by')->nullable(); // usuario que hizo la asignación (admin o el propio cadete)
            $table->string('status_at_assignment')->nullable(); // estado de la comisión al tomarla
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['commission_id', 'assigned_at']);
            $table->index('cadete_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_cadete_history');
    }
};
