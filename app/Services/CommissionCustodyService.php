<?php

namespace App\Services;

use App\Shared\Models\Commission;
use App\Shared\Models\CommissionCadeteHistory;

/**
 * Centraliza la custodia de comisiones por cadete:
 *  - pickup_cadete_id: el cadete que LEVANTÓ la comisión (el primero que la tomó).
 *    Se fija una sola vez y NO se sobreescribe al reasignar/entregar. Es el cadete
 *    al que se le ACREDITA la comisión (salario/ganancias).
 *  - commission_cadete_history: una fila por cada mano por la que pasó la comisión.
 *
 * Importante: estos métodos ajustan las propiedades pickup_cadete_id en el modelo,
 * pero NO llaman a save(). El caller debe persistir la comisión (normalmente ya lo
 * hace con su propio update()).
 */
class CommissionCustodyService
{
    /**
     * Un cadete toma la comisión (self-assign, asignación admin, cambio, o creación
     * por un cadete). Abre un tramo de custodia y, si es la primera mano, fija al
     * cadete acreditado (pickup_cadete_id).
     */
    public function takeCustody(Commission $commission, int $cadeteId, ?int $assignedBy = null): void
    {
        // Cerrar cualquier tramo abierto de OTRO cadete (traspaso).
        $this->closeOpenSegments($commission->id, $cadeteId);

        $alreadyOpen = CommissionCadeteHistory::where('commission_id', $commission->id)
            ->where('cadete_id', $cadeteId)
            ->whereNull('released_at')
            ->exists();

        $isFirstPickup = $commission->pickup_cadete_id === null;

        if (! $alreadyOpen) {
            CommissionCadeteHistory::create([
                'commission_id' => $commission->id,
                'cadete_id' => $cadeteId,
                'action' => $isFirstPickup ? 'LEVANTO' : 'REASIGNADO',
                'assigned_by' => $assignedBy,
                'status_at_assignment' => $commission->status?->value,
                'assigned_at' => now(),
            ]);
        }

        // El crédito queda con el PRIMER cadete que la levantó.
        if ($isFirstPickup) {
            $commission->pickup_cadete_id = $cadeteId;
        }
    }

    /**
     * El cadete suelta la comisión de vuelta al pool (unassign). Cierra su tramo y,
     * si el que la suelta era el cadete acreditado, libera el crédito para que lo
     * tome el próximo cadete que la levante (evita acreditar un "tomó y soltó").
     */
    public function releaseCustody(Commission $commission): void
    {
        $previousCadeteId = $commission->cadete_id;

        $this->closeOpenSegments($commission->id, null);

        if ($previousCadeteId !== null && $commission->pickup_cadete_id === $previousCadeteId) {
            $commission->pickup_cadete_id = null;
        }
    }

    /**
     * Cierra los tramos de custodia abiertos de una comisión (marca released_at).
     * $exceptCadeteId permite no cerrar el tramo del cadete que la sigue teniendo.
     */
    private function closeOpenSegments(int $commissionId, ?int $exceptCadeteId): void
    {
        $query = CommissionCadeteHistory::where('commission_id', $commissionId)
            ->whereNull('released_at');

        if ($exceptCadeteId !== null) {
            $query->where('cadete_id', '!=', $exceptCadeteId);
        }

        $query->update(['released_at' => now()]);
    }
}
