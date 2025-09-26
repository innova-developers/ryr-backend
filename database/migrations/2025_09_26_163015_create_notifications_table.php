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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('commission_id')->constrained('commissions')->onDelete('cascade');
            $table->string('type'); // 'commission_status_change', 'commission_assigned', etc.
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Datos adicionales como estado anterior, nuevo estado, etc.
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'is_read']);
            $table->index(['commission_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
