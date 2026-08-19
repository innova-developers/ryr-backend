<?php

namespace App\Services;

use App\Shared\Models\CustomerRate;
use App\Shared\Models\Destination;

/**
 * RC-484 — Resuelve qué precios rigen para un cliente y un destino.
 *
 * Orden de prioridad:
 *   1. Tarifa del cliente PARA ESE DESTINO
 *   2. Tarifa general del cliente (destination_id nulo)
 *   3. Tabla general del destino
 *
 * Los precios son independientes entre sí: una tarifa puede fijar sólo el bulto
 * grande y dejar que el resto salga de la tabla general.
 */
class CustomerRateResolver
{
    /**
     * @return array{
     *     fixed_price: float,
     *     small_bulk_price: float,
     *     large_bulk_price: float,
     *     agreement_price: float|null,
     *     declared_value_percentage: float|null,
     *     source: string,
     *     customer_rate_id: int|null
     * }
     */
    public function resolve(?int $customerId, Destination $destination): array
    {
        $general = [
            'fixed_price' => (float) $destination->fixed_price,
            'small_bulk_price' => (float) $destination->small_bulk_price,
            'large_bulk_price' => (float) $destination->large_bulk_price,
            'agreement_price' => null,
            'declared_value_percentage' => null,
            'source' => 'general',
            'customer_rate_id' => null,
            'tiers' => collect(),
        ];

        if (! $customerId) {
            return $general;
        }

        $rate = $this->rateFor($customerId, $destination->id);

        if (! $rate) {
            return $general;
        }

        return [
            'fixed_price' => $rate->fixed_price ?? $general['fixed_price'],
            'small_bulk_price' => $rate->small_bulk_price ?? $general['small_bulk_price'],
            'large_bulk_price' => $rate->large_bulk_price ?? $general['large_bulk_price'],
            'agreement_price' => $rate->agreement_price,
            'declared_value_percentage' => $rate->declared_value_percentage,
            'source' => $rate->destination_id ? 'cliente_destino' : 'cliente_general',
            'customer_rate_id' => $rate->id,
            'tiers' => $rate->tiers,
        ];
    }

    /**
     * La tarifa específica del destino gana sobre la general del cliente.
     */
    private function rateFor(int $customerId, int $destinationId): ?CustomerRate
    {
        return CustomerRate::with('tiers')
            ->where('customer_id', $customerId)
            ->where('is_active', true)
            ->where(function ($q) use ($destinationId) {
                $q->where('destination_id', $destinationId)
                  ->orWhereNull('destination_id');
            })
            // destination_id no nulo primero: en MySQL y SQLite, ORDER BY sobre la
            // expresión booleana pone el 0 (no nulo) antes que el 1 (nulo).
            ->orderByRaw('CASE WHEN destination_id IS NULL THEN 1 ELSE 0 END')
            ->first();
    }

    /**
     * Precio unitario de un bulto, aplicando el escalón por cantidad si corresponde.
     *
     * @param array<string, mixed> $resolved Resultado de resolve()
     */
    public function unitPriceFor(array $resolved, ?string $size, int $quantity): float
    {
        $size = mb_strtoupper(trim((string) $size));

        $base = match ($size) {
            'CHICO' => (float) $resolved['small_bulk_price'],
            'GRANDE' => (float) $resolved['large_bulk_price'],
            default => 0.0,
        };

        $tiers = $resolved['tiers'] ?? collect();

        if ($tiers->isEmpty() || $quantity <= 0) {
            return $base;
        }

        // Rige el escalón de mayor min_quantity que la cantidad alcance.
        $tier = $tiers
            ->filter(fn ($t) => mb_strtoupper((string) $t->size) === $size && $quantity >= (int) $t->min_quantity)
            ->sortByDesc(fn ($t) => (int) $t->min_quantity)
            ->first();

        return $tier ? (float) $tier->unit_price : $base;
    }

    /**
     * Total de la comisión según la tarifa resuelta.
     *
     * Con precio de acuerdo, ese valor cierra la comisión y no se suman ni la base ni
     * los bultos. El porcentaje sobre valor declarado siempre se agrega al final.
     *
     * @param array<string, mixed> $resolved
     * @param array<int, array{size: ?string, quantity: int, subtotal: float}> $items
     */
    public function totalFor(array $resolved, array $items, float $declaredValue = 0.0): float
    {
        if ($resolved['agreement_price'] !== null) {
            $total = (float) $resolved['agreement_price'];
        } else {
            $itemsTotal = 0.0;
            foreach ($items as $item) {
                $itemsTotal += (float) ($item['subtotal'] ?? 0);
            }
            $total = (float) $resolved['fixed_price'] + $itemsTotal;
        }

        if ($resolved['declared_value_percentage'] && $declaredValue > 0) {
            $total += round($declaredValue * ((float) $resolved['declared_value_percentage'] / 100), 2);
        }

        return round($total, 2);
    }
}
