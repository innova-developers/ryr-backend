<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-507 — Registro de reenvíos de la encuesta.
 *
 * Sin esto, saber si a un cliente se le reenvió la encuesta y por qué canal obliga a
 * escarbar el log. Se guarda en la propia encuesta: cuántas veces, cuándo y por dónde
 * salió la última.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback_surveys', function (Blueprint $table) {
            $table->unsignedTinyInteger('resend_count')->default(0)->after('sent_at');
            $table->timestamp('last_resent_at')->nullable()->after('resend_count');
            // "whatsapp", "email" o "whatsapp,email"
            $table->string('last_resent_channels', 40)->nullable()->after('last_resent_at');
        });
    }

    public function down(): void
    {
        Schema::table('feedback_surveys', function (Blueprint $table) {
            $table->dropColumn(['resend_count', 'last_resent_at', 'last_resent_channels']);
        });
    }
};
