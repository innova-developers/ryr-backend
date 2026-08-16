<?php

namespace App\Http\Controllers\Admin;

use App\Shared\Enums\CurrentAccountStatus;
use App\Shared\Models\Commission;
use App\Shared\Models\CurrentAccount;
use App\Shared\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * RC-1 y RC-498 — Extracto de cuenta corriente.
 *
 * Antes había DOS libros mayores distintos para el mismo cliente:
 *
 *  - La pantalla de cuenta corriente leía current_accounts y mostraba el saldo que
 *    calcula el backend.
 *  - El PDF de Clientes.jsx armaba su propio libro juntando la tabla commissions con
 *    los créditos de current_accounts, arrancando el saldo acumulado en 0 y ordenando
 *    sólo por fecha.
 *
 * Con eso: los signos salían invertidos, el saldo del PDF era relativo al rango en
 * vez de absoluto, el orden dentro del mismo día era arbitrario, y los conjuntos no
 * coincidían (295 comisiones sin movimiento en cuenta corriente y 298 con fecha
 * distinta a la de su movimiento).
 *
 * Este endpoint es la única fuente: devuelve el saldo inicial del período, los
 * movimientos con su saldo corriente y el detalle de la comisión asociada.
 */
class CurrentAccountStatementController extends Controller
{
    public function show(Request $request, int $customerId): JsonResponse
    {
        $customer = Customer::find($customerId);

        if (! $customer) {
            return response()->json(['success' => false, 'message' => 'Cliente no encontrado'], 404);
        }

        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'type' => 'nullable|in:credit,debit',
            'payment_method' => 'nullable|string',
            'search' => 'nullable|string|max:255',
        ]);

        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;

        // Saldo al cierre del día anterior al período: sin esto el saldo corriente del
        // extracto arranca en 0 y no coincide con el de la pantalla, que es absoluto.
        $openingBalance = $this->balanceBefore($customerId, $dateFrom);

        $query = CurrentAccount::query()
            ->where('customer_id', $customerId)
            ->where('status', CurrentAccountStatus::OK->value);

        if ($dateFrom) {
            $query->whereDate('transaction_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('transaction_date', '<=', $dateTo);
        }
        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }
        if (! empty($validated['payment_method'])) {
            $query->where('payment_method', $validated['payment_method']);
        }
        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhere('observations', 'like', "%{$search}%");
            });
        }

        // Mismo orden que la pantalla, con desempate por id para que dos movimientos
        // del mismo día no salgan en orden distinto en cada consulta.
        $movements = $query->orderBy('transaction_date')->orderBy('id')->get();

        $commissions = $this->commissionsFor($movements);

        $running = $openingBalance;
        $totalDebits = 0.0;
        $totalCredits = 0.0;

        $rows = $movements->map(function (CurrentAccount $movement) use (&$running, &$totalDebits, &$totalCredits, $commissions) {
            $amount = (float) $movement->amount;
            $signed = $movement->type === 'credit' ? $amount : -$amount;
            $running += $signed;

            if ($movement->type === 'credit') {
                $totalCredits += $amount;
            } else {
                $totalDebits += $amount;
            }

            $commission = $commissions->get($this->commissionIdFrom($movement->reference));

            return [
                'id' => $movement->id,
                'date' => $movement->transaction_date?->format('Y-m-d'),
                'type' => $movement->type,
                'description' => $movement->description,
                'reference' => $movement->reference,
                'payment_method' => $movement->payment_method,
                'amount' => $amount,
                'signed_amount' => $signed,
                'balance' => round($running, 2),
                'commission' => $commission ? $this->commissionDetail($commission) : null,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'last_name' => $customer->last_name,
                    'razon_social' => $customer->razon_social,
                    'dni' => $customer->dni,
                    'cuit' => $customer->cuit,
                    'email' => $customer->email,
                    'phone' => $customer->phone ?? $customer->mobile,
                    'address' => $customer->address,
                ],
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'opening_balance' => round($openingBalance, 2),
                'closing_balance' => round($running, 2),
                'total_credits' => round($totalCredits, 2),
                'total_debits' => round($totalDebits, 2),
                'movements_count' => $rows->count(),
                'movements' => $rows,
            ],
        ]);
    }

    /**
     * Saldo acumulado antes del inicio del período. Sin fecha desde, es 0 porque el
     * extracto arranca en el primer movimiento del cliente.
     */
    private function balanceBefore(int $customerId, ?string $dateFrom): float
    {
        if (! $dateFrom) {
            return 0.0;
        }

        $query = CurrentAccount::query()
            ->where('customer_id', $customerId)
            ->where('status', CurrentAccountStatus::OK->value)
            ->whereDate('transaction_date', '<', $dateFrom);

        $credits = (float) (clone $query)->where('type', 'credit')->sum('amount');
        $debits = (float) (clone $query)->where('type', 'debit')->sum('amount');

        return $credits - $debits;
    }

    /**
     * Comisiones referenciadas por los movimientos, indexadas por id.
     */
    private function commissionsFor($movements)
    {
        $ids = $movements
            ->map(fn (CurrentAccount $m) => $this->commissionIdFrom($m->reference))
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Commission::with([
            'items:id,commission_id,type,size,quantity,detail,unit_price,subtotal',
            'originLocation:id,name,address,origin',
            'destinationLocation:id,name,address,origin',
            'destination:id,origin,destination',
        ])->whereIn('id', $ids)->get()->keyBy('id');
    }

    private function commissionIdFrom(?string $reference): ?int
    {
        if (! $reference || ! str_starts_with($reference, 'COM-')) {
            return null;
        }

        $id = (int) substr($reference, 4);

        return $id > 0 ? $id : null;
    }

    /**
     * Detalle que el cliente necesita para identificar a qué corresponde el importe:
     * origen, destino y cantidad de bultos por tamaño.
     */
    private function commissionDetail(Commission $commission): array
    {
        $grandes = 0;
        $chicos = 0;

        foreach ($commission->items ?? [] as $item) {
            // size viene casteado a enum CommissionItemSize; puede ser null en las
            // comisiones extraordinarias, que no tienen tamaño de bulto.
            $size = strtoupper((string) ($item->size?->value ?? ''));
            if ($size === 'GRANDE') {
                $grandes += (int) $item->quantity;
            } elseif ($size === 'CHICO') {
                $chicos += (int) $item->quantity;
            }
        }

        return [
            'id' => $commission->id,
            'date' => $commission->date?->format('Y-m-d'),
            'origin' => $this->locationLabel($commission->originLocation) ?? $commission->destination?->origin,
            'destination' => $this->locationLabel($commission->destinationLocation) ?? $commission->destination?->destination,
            'bultos_grandes' => $grandes,
            'bultos_chicos' => $chicos,
            'notes' => $commission->notes,
            'items' => ($commission->items ?? collect())->map(fn ($i) => [
                'type' => $i->type?->value ?? $i->type,
                'size' => $i->size?->value ?? null,
                'quantity' => (int) $i->quantity,
                'detail' => $i->detail,
                'subtotal' => (float) $i->subtotal,
            ])->values(),
        ];
    }

    private function locationLabel($location): ?string
    {
        if (! $location) {
            return null;
        }

        return trim(implode(' - ', array_filter([
            $location->name,
            $location->address,
            $location->origin,
        ])));
    }
}
