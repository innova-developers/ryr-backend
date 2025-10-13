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
        Schema::create('current_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['credit', 'debit']); // credit = ingreso, debit = egreso
            $table->decimal('amount', 15, 2); // Monto de la transacción
            $table->string('description'); // Descripción de la transacción
            $table->string('reference')->nullable(); // Referencia externa (factura, recibo, etc.)
            $table->date('transaction_date'); // Fecha de la transacción
            $table->decimal('balance', 15, 2); // Saldo después de la transacción
            $table->enum('payment_method', ['cash', 'transfer', 'check', 'card', 'other'])->nullable();
            $table->text('observations')->nullable(); // Observaciones adicionales
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // Usuario que registró la transacción
            $table->timestamps();
            $table->softDeletes();

            // Índices para optimizar consultas
            $table->index(['customer_id', 'transaction_date']);
            $table->index(['customer_id', 'type']);
            $table->index('transaction_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('current_accounts');
    }
};
