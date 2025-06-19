<?php

namespace App\Contexts\Commissions\Application\Mappers;

use Illuminate\Support\Collection;

class CommissionsMapper
{
    public static function fromDomainToArray($commissions): array
    {
        try {
            if (! ($commissions instanceof Collection)) {
                $commissions = collect($commissions);
            }

            return $commissions->map(function ($commission) {
                $items = $commission->items ?? [];
                if (! ($items instanceof Collection)) {
                    $items = collect($items);
                }

                return [
                    'id' => $commission->id ?? null,
                    'client_id' => $commission->client_id ?? null,
                    'branch_id' => $commission->branch_id ?? null,
                    'date' => $commission->date,
                    'status' => $commission->status,
                    'user_id' => $commission->user_id,
                    'total' => $commission->total,
                    'created_at' => $commission->created_at,
                    'updated_at' => $commission->updated_at,
                    'client' => [
                        'id' => $commission->client->id ?? null,
                        'name' => $commission->client->name . ' ' . $commission->client->last_name,
                        'email' => $commission->client->email,
                    ],
                    'origin' => $commission->destination->origin,
                    'destination' => $commission->destination->destination,
                    'origin_location' => [
                        'id' => $commission->originLocation->id ?? null,
                        'name' => $commission->originLocation->name ?? null,
                        'address' => $commission->originLocation->address ?? null,
                        'origin' => $commission->originLocation->origin ?? null,
                        'phone' => $commission->originLocation->phone ?? null,
                    ],
                    'destination_location' => [
                        'id' => $commission->destinationLocation->id ?? null,
                        'name' => $commission->destinationLocation->name ?? null,
                        'address' => $commission->destinationLocation->address ?? null,
                        'origin' => $commission->destinationLocation->origin ?? null,
                        'phone' => $commission->destinationLocation->phone ?? null,
                    ],
                    'current_branch' => $commission->logs->last()->user->branch->name ?? null,
                    'items' => $items->map(function ($item) {
                        return [
                            'id' => $item->id ?? null,
                            'commission_id' => $item->commission_id,
                            'type' => $item->type,
                            'size' => $item->size,
                            'quantity' => $item->quantity,
                            'unit_price' => $item->unit_price,
                            'subtotal' => $item->subtotal,
                            'detail' => $item->detail,
                            'created_at' => $item->created_at,
                            'updated_at' => $item->updated_at,
                        ];
                    })->toArray(),
                ];
            })->toArray();
        } catch (\Exception $e) {
            logger()->error('Mapper failed: ' . $e->getMessage());
            logger()->error('Trace: ' . $e->getTraceAsString());

            throw new \RuntimeException('Failed to map commission. '.$e->getTraceAsString());
        }

    }
    public static function fromDomainToIndiividualArray($commission): array
    {
        try {
            return [
                'id' => $commission->id ?? null,
                'client_id' => $commission->client_id ?? null,
                'client' => [
                    'id' => optional($commission->client)->id,
                    'name' => optional($commission->client)->name,
                    'last_name' => optional($commission->client)->last_name,
                    'email' => optional($commission->client)->email,
                    'phone' => optional($commission->client)->phone,
                    'dni' => optional($commission->client)->dni,
                ],
                'origin' => optional(optional($commission->destination)->origin) ?? null,
                'destination' => optional(optional($commission->destination)->destination) ?? null,
                'origin_location' => [
                    'id' => optional($commission->originLocation)->id,
                    'name' => optional($commission->originLocation)->name,
                    'address' => optional($commission->originLocation)->address,
                    'origin' => optional($commission->originLocation)->origin,
                    'phone' => optional($commission->originLocation)->phone,

                ],
                'destination_location' => [
                    'id' => optional($commission->destinationLocation)->id,
                    'name' => optional($commission->destinationLocation)->name,
                    'address' => optional($commission->destinationLocation)->address,
                    'origin' => optional($commission->destinationLocation)->origin,
                    'phone' => optional($commission->destinationLocation)->phone,
                ],
                'branch_id' => $commission->branch_id ?? null,
                'branch' => [
                    'id' => optional($commission->branch)->id,
                    'name' => optional($commission->branch)->name,
                ],
                'current_branch' => optional(optional(optional($commission->logs)->last())->user)->branch->name ?? null,
                'date' => optional($commission->date)->format('Y-m-d H:i:s') ?? null,
                'status' => $commission->status ?? null,
                'user_id' => $commission->user_id ?? null,
                'user' => [
                    'id' => optional($commission->user)->id,
                    'name' => optional($commission->user)->name,
                ],
                'total' => $commission->total ?? null,
                'items' => optional($commission->items)->map(function ($item) {
                    return [
                        'id' => $item->id ?? null,
                        'description' => $item->description ?? null,
                        'quantity' => $item->quantity ?? null,
                        'unit_price' => $item->unit_price ?? null,
                        'subtotal' => $item->subtotal ?? null,
                        'type' => $item->type ?? null,
                        'size' => $item->size ?? null,
                        'detail' => $item->detail ?? null,
                        'created_at' => $item->created_at ?? null,
                        'updated_at' => $item->updated_at ?? null,
                    ];
                })->toArray() ?? [],
                'logs' => optional($commission->logs)->map(function ($item) {
                    return [
                        'id' => $item->id ?? null,
                        'previous_status' => $item->previous_status ?? null,
                        'new_status' => $item->new_status ?? null,
                        'details' => $item->details ?? null,
                        'created_at' => $item->created_at ?? null,
                        'updated_at' => $item->updated_at ?? null,
                        'user' => optional($item->user)->name,
                    ];
                })->toArray() ?? [],
                'created_at' => $commission->created_at ?? null,
                'updated_at' => $commission->updated_at ?? null,
            ];
        } catch (\Exception $e) {
            logger()->error('Mapper failed: ' . $e->getMessage());
            logger()->error('Trace: ' . $e->getTraceAsString());

            throw new \RuntimeException('Failed to map commission. Check logs for details.');
        }
    }
}
