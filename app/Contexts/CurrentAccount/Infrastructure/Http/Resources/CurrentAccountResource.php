<?php

namespace App\Contexts\CurrentAccount\Infrastructure\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CurrentAccountResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'type' => $this->type,
            'status' => $this->status?->value,
            'amount' => (float) $this->amount,
            'description' => $this->description,
            'reference' => $this->reference,
            'transaction_date' => $this->transaction_date?->format('Y-m-d'),
            'balance' => (float) $this->balance,
            'payment_method' => $this->payment_method,
            'observations' => $this->observations,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'verified_by_user_id' => $this->verified_by_user_id,
            'verified_at' => $this->verified_at?->toISOString(),
            'verified_by' => $this->whenLoaded('verifiedBy', function () {
                return $this->verifiedBy ? [
                    'id' => $this->verifiedBy->id,
                    'name' => $this->verifiedBy->name,
                    'email' => $this->verifiedBy->email,
                ] : null;
            }),
            'customer' => $this->whenLoaded('customer', function () {
                return $this->customer ? [
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                    'last_name' => $this->customer->last_name,
                ] : null;
            }),
            'user' => $this->whenLoaded('user', function () {
                return $this->user ? [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ] : null;
            }),
        ];
    }
}
