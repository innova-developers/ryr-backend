<?php

namespace App\Contexts\Commissions\Infrastructure\Mappers;

class CommissionMapper
{
    public static function fromEntityToArray($commission): array
    {
        return [
            'id' => $commission->id,
            'client_id' => $commission->client_id,
            'destination_id' => $commission->destination_id,
            'date' => $commission->date,
            'status' => $commission->status,
            'user_id' => $commission->user_id,
            'branch_id' => $commission->branch_id,
            'total' => $commission->total,
            'origin_location_id' => $commission->origin_location_id,
            'destination_location_id' => $commission->destination_location_id,
            'client' => $commission->client ? [
                'id' => $commission->client->id,
                'name' => $commission->client->name,
            ] : null,
            'destination' => $commission->destination ? [
                'id' => $commission->destination->id,
                'name' => $commission->destination->name,
            ] : null,
            'branch' => $commission->branch ? [
                'id' => $commission->branch->id,
                'name' => $commission->branch->name,
            ] : null,
            'user' => $commission->user ? [
                'id' => $commission->user->id,
                'name' => $commission->user->name,
            ] : null,
            'origin_location' => $commission->originLocation ? [
                'id' => $commission->originLocation->id,
                'name' => $commission->originLocation->name,
                'address' => $commission->originLocation->address,
            ] : null,
            'destination_location' => $commission->destinationLocation ? [
                'id' => $commission->destinationLocation->id,
                'name' => $commission->destinationLocation->name,
                'address' => $commission->destinationLocation->address,
            ] : null,
            'items' => $commission->items ? $commission->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'commission_id' => $item->commission_id,
                    'type' => $item->type,
                    'size' => $item->size,
                    'quantity' => $item->quantity,
                    'unit_price' => number_format($item->unit_price, 2, '.', ''),
                    'subtotal' => number_format($item->subtotal, 2, '.', ''),
                    'detail' => $item->detail,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ];
            })->toArray() : [],
            'logs' => $commission->logs ? $commission->logs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'previous_status' => $log->previous_status ?: "",
                    'new_status' => $log->new_status,
                    'details' => $log->details,
                    'created_at' => $log->created_at,
                    'updated_at' => $log->updated_at,
                    'user' => $log->user ? $log->user->name : null,
        ];
            })->toArray() : [],
            'created_at' => $commission->created_at,
            'updated_at' => $commission->updated_at,
        ];
    }
}
