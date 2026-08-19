<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RC-499 (EDICION DE COMISION).
 *
 * Al cambiar el cliente de una comisión, el movimiento de cuenta corriente asociado
 * (reference = "COM-{id}") no se movía: quedaba colgado del cliente anterior, así que
 * la comisión figuraba a la vez en la cuenta del viejo y en la del nuevo.
 *
 * El código ya quedó corregido; esta migración repara los movimientos que quedaron
 * desviados (4 en la base de producción al 16-08-2026) y recalcula los saldos de
 * todos los clientes afectados, tanto los que pierden el movimiento como los que lo
 * reciben.
 */
return new class extends Migration
{
    public function up(): void
    {
        $desviados = DB::table('current_accounts as ca')
            ->join('commissions as c', DB::raw('c.id'), '=', DB::raw('CAST(SUBSTRING(ca.reference, 5) AS UNSIGNED)'))
            ->where('ca.reference', 'LIKE', 'COM-%')
            ->whereColumn('ca.customer_id', '!=', 'c.client_id')
            ->select('ca.id', 'ca.customer_id as viejo', 'c.client_id as nuevo')
            ->get();

        if ($desviados->isEmpty()) {
            return;
        }

        $afectados = [];

        foreach ($desviados as $fila) {
            DB::table('current_accounts')
                ->where('id', $fila->id)
                ->update(['customer_id' => $fila->nuevo]);

            $afectados[$fila->viejo] = true;
            $afectados[$fila->nuevo] = true;
        }

        foreach (array_keys($afectados) as $customerId) {
            $this->recalcularSaldos((int) $customerId);
        }
    }

    public function down(): void
    {
        // No hay vuelta atrás: la asignación anterior era el error que se corrige.
    }

    /**
     * Recalcula el saldo acumulado de un cliente sobre sus movimientos confirmados,
     * en el mismo orden que usa la aplicación (transaction_date, luego id).
     */
    private function recalcularSaldos(int $customerId): void
    {
        $movimientos = DB::table('current_accounts')
            ->where('customer_id', $customerId)
            ->where('status', 'OK')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get(['id', 'type', 'amount']);

        $saldo = 0.0;

        foreach ($movimientos as $movimiento) {
            $saldo += match ($movimiento->type) {
                'credit' => (float) $movimiento->amount,
                'debit' => -(float) $movimiento->amount,
                default => 0.0,
            };

            DB::table('current_accounts')->where('id', $movimiento->id)->update(['balance' => $saldo]);
        }
    }
};
