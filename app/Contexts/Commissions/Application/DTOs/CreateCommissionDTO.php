<?php

namespace App\Contexts\Commissions\Application\DTOs;

use App\Shared\Enums\CommissionStatus;
use DateTime;

class CreateCommissionDTO
{
    public function __construct(
        public readonly int $clientId,
        public readonly DateTime $date,
        public readonly string $origin,
        public readonly string $destination,
        public readonly CommissionStatus $status,
        public readonly array $items,
        public readonly float $total
    ) {
    }

    /**
     * @throws \Exception
     */
    public static function fromArray(array $data): self
    {
        return new self(
            clientId: $data['client_id'],
            date: new DateTime($data['date']),
            origin: $data['origin'],
            destination: $data['destination'],
            status: CommissionStatus::from($data['status']),
            items: array_map(fn ($item) => CommissionItemDTO::fromArray($item), $data['items']),
            total: $data['total']
        );
    }

    public function toArray(): array
    {
        return [
            'client_id' => $this->clientId,
            'date' => $this->date->format('Y-m-d'),
            'origin' => $this->origin,
            'destination' => $this->destination,
            'status' => $this->status->value,
            'items' => array_map(fn ($item) => $item->toArray(), $this->items),
            'total' => $this->total
        ];
    }
}
