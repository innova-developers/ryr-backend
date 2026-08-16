<?php

namespace App\Http\Controllers\Admin;

use App\Services\CustomerRateResolver;
use App\Shared\Models\Customer;
use App\Shared\Models\CustomerRate;
use App\Shared\Models\CustomerRateTier;
use App\Shared\Models\Destination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * RC-484 — Tarifas especiales por cliente.
 *
 * Cada precio es opcional: lo que no se define cae a la tabla general del destino.
 * Con destination_id nulo la tarifa vale para todos los destinos.
 */
class CustomerRateController extends Controller
{
    public function __construct(private readonly CustomerRateResolver $resolver)
    {
    }

    public function index(int $customerId): JsonResponse
    {
        $customer = Customer::find($customerId);

        if (! $customer) {
            return response()->json(['success' => false, 'message' => 'Cliente no encontrado'], 404);
        }

        $rates = CustomerRate::with(['tiers', 'destination:id,origin,destination'])
            ->where('customer_id', $customerId)
            ->orderByRaw('CASE WHEN destination_id IS NULL THEN 0 ELSE 1 END')
            ->get()
            ->map(fn (CustomerRate $r) => $this->present($r));

        return response()->json(['success' => true, 'data' => $rates]);
    }

    public function store(Request $request, int $customerId): JsonResponse
    {
        $customer = Customer::find($customerId);

        if (! $customer) {
            return response()->json(['success' => false, 'message' => 'Cliente no encontrado'], 404);
        }

        $validated = $this->validatePayload($request);

        $existe = CustomerRate::where('customer_id', $customerId)
            ->where('destination_id', $validated['destination_id'] ?? null)
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe una tarifa para ese destino. Editá la existente.',
            ], 422);
        }

        $rate = DB::transaction(function () use ($customerId, $validated) {
            $rate = CustomerRate::create([
                'customer_id' => $customerId,
                'destination_id' => $validated['destination_id'] ?? null,
                'fixed_price' => $validated['fixed_price'] ?? null,
                'small_bulk_price' => $validated['small_bulk_price'] ?? null,
                'large_bulk_price' => $validated['large_bulk_price'] ?? null,
                'agreement_price' => $validated['agreement_price'] ?? null,
                'declared_value_percentage' => $validated['declared_value_percentage'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->syncTiers($rate, $validated['tiers'] ?? []);

            return $rate;
        });

        return response()->json(['success' => true, 'data' => $this->present($rate->fresh(['tiers', 'destination']))], 201);
    }

    public function update(Request $request, int $customerId, int $rateId): JsonResponse
    {
        $rate = CustomerRate::where('customer_id', $customerId)->find($rateId);

        if (! $rate) {
            return response()->json(['success' => false, 'message' => 'Tarifa no encontrada'], 404);
        }

        $validated = $this->validatePayload($request);

        DB::transaction(function () use ($rate, $validated) {
            $rate->update([
                'destination_id' => $validated['destination_id'] ?? null,
                'fixed_price' => $validated['fixed_price'] ?? null,
                'small_bulk_price' => $validated['small_bulk_price'] ?? null,
                'large_bulk_price' => $validated['large_bulk_price'] ?? null,
                'agreement_price' => $validated['agreement_price'] ?? null,
                'declared_value_percentage' => $validated['declared_value_percentage'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->syncTiers($rate, $validated['tiers'] ?? []);
        });

        return response()->json(['success' => true, 'data' => $this->present($rate->fresh(['tiers', 'destination']))]);
    }

    public function destroy(int $customerId, int $rateId): JsonResponse
    {
        $rate = CustomerRate::where('customer_id', $customerId)->find($rateId);

        if (! $rate) {
            return response()->json(['success' => false, 'message' => 'Tarifa no encontrada'], 404);
        }

        $rate->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Previsualiza qué precios rigen para un cliente y un destino, indicando de dónde
     * sale cada uno. Es lo que consume la pantalla de carga de comisión.
     */
    public function preview(Request $request, int $customerId): JsonResponse
    {
        $validated = $request->validate([
            'destination_id' => 'required|exists:destinations,id',
        ]);

        $destination = Destination::findOrFail($validated['destination_id']);
        $resolved = $this->resolver->resolve($customerId, $destination);

        return response()->json([
            'success' => true,
            'data' => [
                'fixed_price' => $resolved['fixed_price'],
                'small_bulk_price' => $resolved['small_bulk_price'],
                'large_bulk_price' => $resolved['large_bulk_price'],
                'agreement_price' => $resolved['agreement_price'],
                'declared_value_percentage' => $resolved['declared_value_percentage'],
                // 'general' | 'cliente_general' | 'cliente_destino'
                'source' => $resolved['source'],
                'has_special_rate' => $resolved['source'] !== 'general',
                'tiers' => collect($resolved['tiers'])->map(fn ($t) => [
                    'size' => $t->size,
                    'min_quantity' => (int) $t->min_quantity,
                    'unit_price' => (float) $t->unit_price,
                ])->values(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'destination_id' => 'nullable|exists:destinations,id',
            'fixed_price' => 'nullable|numeric|min:0',
            'small_bulk_price' => 'nullable|numeric|min:0',
            'large_bulk_price' => 'nullable|numeric|min:0',
            'agreement_price' => 'nullable|numeric|min:0',
            'declared_value_percentage' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string|max:500',
            'tiers' => 'nullable|array',
            'tiers.*.size' => 'required|in:CHICO,GRANDE,chico,grande',
            'tiers.*.min_quantity' => 'required|integer|min:1',
            'tiers.*.unit_price' => 'required|numeric|min:0',
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $tiers
     */
    private function syncTiers(CustomerRate $rate, array $tiers): void
    {
        $rate->tiers()->delete();

        foreach ($tiers as $tier) {
            CustomerRateTier::create([
                'customer_rate_id' => $rate->id,
                'size' => $tier['size'],
                'min_quantity' => (int) $tier['min_quantity'],
                'unit_price' => (float) $tier['unit_price'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function present(CustomerRate $rate): array
    {
        return [
            'id' => $rate->id,
            'destination_id' => $rate->destination_id,
            'destination_label' => $rate->destination
                ? "{$rate->destination->origin} → {$rate->destination->destination}"
                : 'Todos los destinos',
            'fixed_price' => $rate->fixed_price,
            'small_bulk_price' => $rate->small_bulk_price,
            'large_bulk_price' => $rate->large_bulk_price,
            'agreement_price' => $rate->agreement_price,
            'declared_value_percentage' => $rate->declared_value_percentage,
            'is_active' => $rate->is_active,
            'notes' => $rate->notes,
            'tiers' => $rate->tiers->map(fn ($t) => [
                'id' => $t->id,
                'size' => $t->size,
                'min_quantity' => (int) $t->min_quantity,
                'unit_price' => (float) $t->unit_price,
            ])->values(),
        ];
    }
}
