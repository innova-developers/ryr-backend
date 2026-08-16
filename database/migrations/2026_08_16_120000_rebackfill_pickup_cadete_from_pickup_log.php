<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RC-482 (LIQUIDACION DE EMPLEADOS / CADETES).
 *
 * El backfill de sprint 8 acreditó cada comisión al PRIMER cadete que apareció en
 * commission_logs, que es el que se la asignó. Pero la liquidación va para el que
 * efectivamente hizo el retiro, o sea el que puso la comisión en ENCOMIENDA_RETIRADA.
 *
 * Sobre la base de producción al 16-08-2026: de 7665 comisiones con retiro
 * registrado, 415 estaban acreditadas a un cadete distinto del que retiró
 * (~$7,1M mal atribuidos) y 28 no tenían acreditación.
 *
 * Esta migración recalcula pickup_cadete_id usando el AUTOR del primer log de
 * ENCOMIENDA_RETIRADA. Las comisiones que nunca llegaron a retirarse quedan como
 * están: todavía no hay retiro que acreditar.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL: se resuelve en una sola sentencia con subconsulta correlacionada.
        // El log más viejo de ENCOMIENDA_RETIRADA es el retiro real; si por alguna
        // corrección hubiera más de uno, vale el primero.
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->backfillPortable();

            return;
        }

        DB::statement("
            UPDATE commissions c
            SET c.pickup_cadete_id = (
                SELECT l.user_id
                FROM commission_logs l
                WHERE l.commission_id = c.id
                  AND l.new_status = 'ENCOMIENDA_RETIRADA'
                ORDER BY l.created_at ASC, l.id ASC
                LIMIT 1
            )
            WHERE EXISTS (
                SELECT 1
                FROM commission_logs l2
                WHERE l2.commission_id = c.id
                  AND l2.new_status = 'ENCOMIENDA_RETIRADA'
            )
        ");
    }

    public function down(): void
    {
        // No se revierte: el valor anterior era el criterio equivocado que se corrige.
    }

    /**
     * Misma lógica fila por fila, para los entornos de test en sqlite.
     */
    private function backfillPortable(): void
    {
        $retiros = DB::table('commission_logs')
            ->where('new_status', 'ENCOMIENDA_RETIRADA')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['commission_id', 'user_id']);

        $primerRetiro = [];

        foreach ($retiros as $log) {
            if (! array_key_exists($log->commission_id, $primerRetiro)) {
                $primerRetiro[$log->commission_id] = $log->user_id;
            }
        }

        foreach ($primerRetiro as $commissionId => $userId) {
            DB::table('commissions')
                ->where('id', $commissionId)
                ->update(['pickup_cadete_id' => $userId]);
        }
    }
};
