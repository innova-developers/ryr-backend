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
        Schema::create('satisfaction_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_id')->constrained('commissions')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('token', 64)->unique()->comment('Token único para validar la encuesta');
            $table->tinyInteger('rating')->nullable()->comment('Calificación de 1 a 5');
            $table->text('comment')->nullable()->comment('Comentario del cliente');
            $table->timestamp('sent_at')->nullable()->comment('Fecha en que se envió la encuesta');
            $table->timestamp('responded_at')->nullable()->comment('Fecha en que el cliente respondió');
            $table->timestamps();
            
            // Índices
            $table->index('commission_id');
            $table->index('customer_id');
            $table->index('token');
            $table->index('responded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('satisfaction_surveys');
    }
};
