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
        Schema::create('fcm_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('fcm_token', 500);
            $table->enum('platform', ['android', 'ios', 'web'])->default('android');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            
            // Índice único para evitar tokens duplicados por usuario y plataforma
            $table->unique(['user_id', 'platform']);
            // Índice para búsquedas rápidas por token
            $table->index('fcm_token');
            // Índice para tokens activos
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fcm_tokens');
    }
};
