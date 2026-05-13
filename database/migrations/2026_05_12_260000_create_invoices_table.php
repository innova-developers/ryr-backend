<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('commission_id')->nullable()->constrained('commissions');
            $table->foreignId('current_account_id')->nullable()->constrained('current_accounts');
            $table->foreignId('franchise_id')->nullable()->constrained('franchises');
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('user_id')->constrained('users');

            $table->unsignedTinyInteger('tipo_comprobante');
            $table->unsignedInteger('punto_venta');
            $table->unsignedInteger('numero_comprobante');
            $table->date('fecha_emision');

            $table->string('cae', 20)->nullable();
            $table->string('cae_vencimiento', 10)->nullable();

            $table->decimal('importe_total', 12, 2);
            $table->decimal('importe_neto', 12, 2);
            $table->decimal('importe_iva', 12, 2)->default(0);
            $table->decimal('iva_rate', 5, 2)->default(21);

            $table->unsignedTinyInteger('doc_tipo')->default(99);
            $table->string('doc_numero', 20)->nullable();

            $table->string('razon_social')->nullable();
            $table->string('domicilio_cliente')->nullable();
            $table->string('condicion_iva', 50)->nullable();

            $table->unsignedTinyInteger('concepto')->default(2);

            $table->string('status', 20)->default('emitida');
            $table->text('observaciones')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'fecha_emision']);
            $table->index(['commission_id']);
            $table->index(['current_account_id']);
            $table->index(['franchise_id']);
            $table->index(['tipo_comprobante', 'punto_venta', 'numero_comprobante']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
