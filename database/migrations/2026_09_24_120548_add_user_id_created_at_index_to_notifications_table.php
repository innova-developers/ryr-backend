<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índice (user_id, created_at) en notifications (RC-548).
 *
 * El listado de la app (GET /cadete/notifications) filtra por user_id y ordena por
 * created_at desc. Sólo existía (user_id, is_read), así que MySQL leía todas las
 * notificaciones del cadete y las ordenaba en memoria para devolver 20: EXPLAIN con
 * "Using filesort" sobre 44.152 filas estimadas (32.741 reales del cadete 17), y el
 * listado tardaba ~100 ms en prod. Con este índice lee las 20 más nuevas en orden y corta.
 *
 * Es aditivo: no cambia datos ni otros índices. El de (user_id, is_read) se queda
 * porque es el que resuelve el conteo de no leídas del badge.
 */
return new class extends Migration
{
    private const INDEX = 'notifications_user_id_created_at_index';

    public function up(): void
    {
        // Por si en algún entorno ya se creó a mano, con este u otro nombre.
        if (Schema::hasIndex('notifications', ['user_id', 'created_at'])) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('notifications', self::INDEX)) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(self::INDEX);
        });
    }
};
