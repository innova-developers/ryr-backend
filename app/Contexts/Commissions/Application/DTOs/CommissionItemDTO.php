<?php

namespace App\Contexts\Commissions\Application\DTOs;

use App\Shared\Enums\CommissionItemSize;
use App\Shared\Enums\CommissionItemType;
use DateTime;

class CommissionItemDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly ?int $commissionId,
        public readonly CommissionItemType $type,
        public readonly ?CommissionItemSize $size,
        public readonly int $quantity,
        public readonly float $unitPrice,
        public readonly float $subtotal,
        public readonly ?string $detail,
        public readonly ?DateTime $createdAt = null,
        public readonly ?DateTime $updatedAt = null,
        public readonly ?DateTime $deletedAt = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            commissionId: $data['commission_id'] ?? null,
            type: CommissionItemType::from($data['type']),
            size: isset($data['size']) ? CommissionItemSize::from($data['size']) : null,
            quantity: $data['quantity'],
            unitPrice: $data['unit_price'],
            subtotal: $data['subtotal'],
            detail: $data['detail'] ?? null,
            createdAt: isset($data['created_at']) ? new DateTime($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new DateTime($data['updated_at']) : null,
            deletedAt: isset($data['deleted_at']) ? new DateTime($data['deleted_at']) : null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'commission_id' => $this->commissionId,
            'type' => $this->type->value,
            'size' => $this->size?->value,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'subtotal' => $this->subtotal,
            'detail' => $this->detail,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deletedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
