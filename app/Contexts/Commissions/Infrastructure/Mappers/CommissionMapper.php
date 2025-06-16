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
            'items' => $commission->items->map(function ($item) {
                return [
                    'type' => $item->type,
                    'size' => $item->size,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                    'detail' => $item->detail,
                ];
            })->toArray(),
        ];
    }

}
