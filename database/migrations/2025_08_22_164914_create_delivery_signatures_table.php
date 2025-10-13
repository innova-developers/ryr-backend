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
        Schema::create('delivery_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_id')->constrained('commissions')->onDelete('cascade');
            $table->foreignId('cadete_id')->constrained('users')->onDelete('cascade');
            $table->string('receiver_name');
            $table->string('receiver_phone');
            $table->text('notes')->nullable();
            $table->longText('signature_image'); // Base64 PNG image
            $table->timestamp('delivery_timestamp');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            
            // Índices para optimizar consultas
            $table->index(['commission_id']);
            $table->index(['cadete_id']);
            $table->index(['delivery_timestamp']);
            
            // Una comisión solo puede tener una firma
            $table->unique('commission_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_signatures');
    }
};
