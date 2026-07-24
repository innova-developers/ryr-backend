<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill histórico (todo en SQL para no cargar ~100k logs en memoria):
     *  - pickup_cadete_id = primer cadete que actuó sobre la comisión (según
     *    commission_logs), con fallback al cadete_id actual.
     *  - commission_cadete_history = una fila por cada cadete distinto que actuó,
     *    en orden de aparición (LEVANTO el primero, REASIGNADO el resto).
     *
     * Nota: las autoasignaciones históricas no dejaron log; se usa el primer cadete
     * que aparece en commission_logs. Las comisiones nuevas se trackean en tiempo
     * real desde el controlador de custodia.
     */
    public function up(): void
    {
        // El backfill usa sintaxis específica de MySQL (UPDATE ... JOIN, window functions)
        // y solo tiene sentido sobre datos históricos reales. En SQLite (tests con base
        // vacía) no hay nada que rellenar: se omite.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // 1) pickup_cadete_id = primer cadete que actuó (por commission_logs)
        DB::statement(<<<'SQL'
            UPDATE commissions c
            JOIN (
                SELECT commission_id, user_id FROM (
                    SELECT cl.commission_id, cl.user_id,
                        ROW_NUMBER() OVER (PARTITION BY cl.commission_id ORDER BY cl.id) AS rn
                    FROM commission_logs cl
                    JOIN users u ON u.id = cl.user_id
                    WHERE u.role IN ('cadete', 'cadete_externo')
                ) t
                WHERE t.rn = 1
            ) first ON first.commission_id = c.id
            SET c.pickup_cadete_id = first.user_id
            WHERE c.pickup_cadete_id IS NULL
        SQL);

        // Fallback: si aún no tiene picker, usar el cadete_id operativo actual
        DB::statement('UPDATE commissions SET pickup_cadete_id = cadete_id WHERE pickup_cadete_id IS NULL AND cadete_id IS NOT NULL');

        // 2) Reconstruir el historial de manos (solo si está vacío, para re-runs)
        $already = DB::table('commission_cadete_history')->count();
        if ($already === 0) {
            DB::statement(<<<'SQL'
                INSERT INTO commission_cadete_history
                    (commission_id, cadete_id, action, assigned_by, status_at_assignment, assigned_at, released_at, created_at, updated_at)
                SELECT
                    y.commission_id,
                    y.user_id,
                    CASE WHEN y.cadete_rn = 1 THEN 'LEVANTO' ELSE 'REASIGNADO' END,
                    NULL,
                    y.new_status,
                    y.created_at,
                    NULL,
                    NOW(),
                    NOW()
                FROM (
                    SELECT
                        x.commission_id,
                        x.user_id,
                        x.new_status,
                        x.created_at,
                        ROW_NUMBER() OVER (PARTITION BY x.commission_id ORDER BY x.first_id) AS cadete_rn
                    FROM (
                        SELECT
                            cl.commission_id,
                            cl.user_id,
                            cl.new_status,
                            cl.created_at,
                            cl.id AS first_id,
                            ROW_NUMBER() OVER (PARTITION BY cl.commission_id, cl.user_id ORDER BY cl.id) AS log_rn
                        FROM commission_logs cl
                        JOIN users u ON u.id = cl.user_id
                        WHERE u.role IN ('cadete', 'cadete_externo')
                    ) x
                    WHERE x.log_rn = 1
                ) y
            SQL);
        }
    }

    public function down(): void
    {
        DB::table('commission_cadete_history')->truncate();
        DB::table('commissions')->update(['pickup_cadete_id' => null]);
    }
};
