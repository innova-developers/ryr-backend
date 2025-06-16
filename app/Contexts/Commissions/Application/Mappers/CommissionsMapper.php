<?php

namespace App\Contexts\Commissions\Application\Mappers;

use Illuminate\Support\Collection;

class CommissionsMapper
{
    public static function fromDomainToArray($commissions): array
    {
        if (! ($commissions instanceof Collection)) {
            $commissions = collect($commissions);
        }

        return $commissions->map(function ($commission) {
            $items = $commission->items ?? [];
            if (! ($items instanceof Collection)) {
                $items = collect($items);
            }

            return [
                'id' => $commission->id,
                'client_id' => $commission->client_id,
                'destination_id' => $commission->destination_id,
                'branch_id' => $commission->branch_id,
                'date' => $commission->date,
                'status' => $commission->status,
                'user_id' => $commission->user_id,
                'total' => $commission->total,
                'created_at' => $commission->created_at,
                'updated_at' => $commission->updated_at,
                'client' => [
                    'id' => $commission->client->id,
                    'name' => $commission->client->name . ' ' . $commission->client->last_name,
                    'email' => $commission->client->email,
                ],
                'origin' => $commission->origin,
                'destination' => $commission->destination,
                'current_branch' => $commission->logs->last()->user->branch->name,
                'items' => $commission->items->map(function ($item) {
                    return [
                        'id' => $item->id,
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
    }
    public static function fromDomainToIndiividualArray($commission): array
    {
        return [
            'id' => $commission->id,
            'client_id' => $commission->client_id,
            'client' => [
                'id' => $commission->client->id,
                'name' => $commission->client->name,
                'last_name' => $commission->client->last_name,
                'email' => $commission->client->email,
                'phone' => $commission->client->phone,
                'dni' => $commission->client->dni,
            ],
            'origin' => $commission->origin,
            'destination' => $commission->destination,
            'branch_id' => $commission->branch_id,
            'branch' => [
                'id' => $commission->branch->id,
                'name' => $commission->branch->name,
            ],
            'current_branch' => $commission->logs->last()->user->branch->name,
            'date' => $commission->date->format('Y-m-d H:i:s'),
            'status' => $commission->status,
            'user_id' => $commission->user_id,
            'user' => [
                'id' => $commission->user->id,
                'name' => $commission->user->name,
            ],
            'total' => $commission->total,
            'items' => $commission->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                    'type' => $item->type,
                    'size' => $item->size,
                    'detail' => $item->detail,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ];
            })->toArray(),
            'logs' => $commission->logs->map(function ($item) {
                return [
                    'id' => $item->id,
                    'previous_status' => $item->previous_status,
                    'new_status' => $item->new_status,
                    'details' => $item->details,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                    'user' => $item->user->name,
                ];
            })->toArray(),
            'created_at' => $commission->created_at,
            'updated_at' => $commission->updated_at,
        ];
    }
}
