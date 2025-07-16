<?php

namespace App\Contexts\CurrentAccount\Application\DTO;

class UpdateCurrentAccountDTO
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $type,
        public readonly ?float $amount,
        public readonly ?string $description,
        public readonly ?string $reference,
        public readonly ?string $transactionDate,
        public readonly ?string $paymentMethod,
        public readonly ?string $observations,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            type: $data['type'] ?? null,
            amount: $data['amount'] ?? null,
            description: $data['description'] ?? null,
            reference: $data['reference'] ?? null,
            transactionDate: $data['transaction_date'] ?? null,
            paymentMethod: $data['payment_method'] ?? null,
            observations: $data['observations'] ?? null,
        );
    }
}
