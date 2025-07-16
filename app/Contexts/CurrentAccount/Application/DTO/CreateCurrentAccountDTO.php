<?php

namespace App\Contexts\CurrentAccount\Application\DTO;

class CreateCurrentAccountDTO
{
    public function __construct(
        public readonly int $customerId,
        public readonly string $type,
        public readonly float $amount,
        public readonly string $description,
        public readonly ?string $reference,
        public readonly string $transactionDate,
        public readonly ?string $paymentMethod,
        public readonly ?string $observations,
        public readonly ?int $userId,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            customerId: $data['customer_id'],
            type: $data['type'],
            amount: $data['amount'],
            description: $data['description'],
            reference: $data['reference'] ?? null,
            transactionDate: $data['transaction_date'],
            paymentMethod: $data['payment_method'] ?? null,
            observations: $data['observations'] ?? null,
            userId: $data['user_id'] ?? null,
        );
    }
}
