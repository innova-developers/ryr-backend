<?php

namespace App\Contexts\Commissions\Application\DTOs;

use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\CommissionType;
use DateTime;

class UpdateCommissionDTO
{
    public function __construct(
        public readonly int $id,
        public readonly int $clientId,
        public readonly DateTime $date,
        public readonly string $origin,
        public readonly string $destination,
        public readonly CommissionStatus $status,
        public readonly ?array $items,
        public readonly float $total,
        public readonly int $originLocationId,
        public readonly int $destinationLocationId,
        public readonly ?string $notes = null,
        public readonly bool $aCuenta = false,
        public readonly ?CommissionType $type = null
    ) {
    }

    /**
     * @throws \Exception
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            clientId: $data['client_id'],
            date: new DateTime($data['date']),
            origin: $data['origin'],
            destination: $data['destination'],
            status: CommissionStatus::from($data['status']),
            items: isset($data['items']) && !empty($data['items']) ? array_map(fn ($item) => CommissionItemDTO::fromArray($item), $data['items']) : null,
            total: $data['total'],
            originLocationId: $data['origin_location_id'],
            destinationLocationId: $data['destination_location_id'],
            notes: $data['notes'] ?? null,
            aCuenta: $data['a_cuenta'] ?? false,
            type: isset($data['type']) ? CommissionType::from($data['type']) : null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->clientId,
            'date' => $this->date->format('Y-m-d'),
            'origin' => $this->origin,
            'destination' => $this->destination,
            'status' => $this->status->value,
            'items' => $this->items ? array_map(fn ($item) => $item->toArray(), $this->items) : null,
            'total' => $this->total,
            'origin_location_id' => $this->originLocationId,
            'destination_location_id' => $this->destinationLocationId,
            'notes' => $this->notes,
            'a_cuenta' => $this->aCuenta,
        ];
    }
}
