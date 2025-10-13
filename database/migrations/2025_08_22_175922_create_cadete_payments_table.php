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
        Schema::create('cadete_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cadete_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade');
            $table->string('payment_type'); // monthly, biweekly, weekly, bonus, advance, other
            $table->string('payment_method'); // cash, bank_transfer, check, other
            // $table->decimal('amount', 10, 2); // Campo removido, se usa net_amount en su lugar
            $table->decimal('commission_amount', 10, 2)->default(0); // Ganancias por comisiones
            $table->decimal('base_salary', 10, 2)->default(0); // Salario base
            $table->decimal('bonus_amount', 10, 2)->default(0); // Bonos adicionales
            $table->decimal('deduction_amount', 10, 2)->default(0); // Deducciones
            $table->decimal('net_amount', 10, 2); // Monto neto a pagar
            $table->string('status')->default('pending'); // pending, paid, cancelled
            $table->date('payment_date'); // Fecha del pago
            $table->date('period_start'); // Inicio del período
            $table->date('period_end'); // Fin del período
            $table->text('description')->nullable(); // Descripción del pago
            $table->text('notes')->nullable(); // Notas adicionales
            $table->string('reference_number')->nullable(); // Número de referencia
            $table->string('transaction_id')->nullable(); // ID de transacción bancaria
            $table->timestamp('paid_at')->nullable(); // Fecha de pago efectivo
            $table->timestamps();
            $table->softDeletes(); // Para SoftDeletes
            
            // Índices para optimizar consultas
            $table->index(['cadete_id', 'payment_date']);
            $table->index(['cadete_id', 'status']);
            $table->index(['payment_type', 'status']);
            $table->index(['period_start', 'period_end']);
            
            // Índice único para evitar pagos duplicados en el mismo período
            $table->unique(['cadete_id', 'payment_type', 'period_start', 'period_end'], 'unique_cadete_period_payment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cadete_payments');
    }
};
