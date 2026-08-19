<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-490 (CHATBOT BASICO).
 *
 * Preguntas frecuentes que responde el chatbot. Se guardan en base y no en código
 * para que el admin pueda editarlas sin un deploy.
 *
 * keywords es la lista de términos que disparan la respuesta, separados por coma.
 * El matcheo es por palabras, no por IA: el alcance de la card es un chatbot básico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->text('keywords');
            $table->string('category', 60)->default('general');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_faqs');
    }
};
