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
        Schema::create('franchise_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('franchise_id')->constrained('franchises')->onDelete('cascade');
            $table->unsignedBigInteger('commission_id')->comment('ID de la comisión en la base de datos de la franquicia');
            $table->decimal('commission_amount', 10, 2)->comment('Monto total de la comisión de la franquicia');
            $table->decimal('matrix_commission_amount', 10, 2)->comment('Monto de la comisión adeudada a la matriz');
            $table->decimal('commission_percentage', 5, 2)->comment('Porcentaje aplicado para calcular la comisión de la matriz');
            $table->date('commission_date')->comment('Fecha de la comisión');
            $table->string('status')->default('pending')->comment('Estado: pending, paid, cancelled');
            $table->date('paid_at')->nullable()->comment('Fecha de pago');
            $table->text('notes')->nullable()->comment('Notas adicionales');
            $table->timestamps();
            
            // Índices
            $table->index('franchise_id');
            $table->index('commission_id');
            $table->index('status');
            $table->index('commission_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('franchise_commissions');
    }
};
