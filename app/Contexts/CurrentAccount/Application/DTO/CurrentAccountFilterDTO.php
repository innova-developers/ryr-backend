<?php

namespace App\Contexts\CurrentAccount\Application\DTO;

class CurrentAccountFilterDTO
{
    public function __construct(
        public readonly ?int $customerId = null,
        public readonly ?string $type = null,
        public readonly ?string $startDate = null,
        public readonly ?string $endDate = null,
        public readonly ?string $paymentMethod = null,
        public readonly ?string $search = null,
        public readonly int $perPage = 15,
        public readonly int $page = 1,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            customerId: $data['customer_id'] ?? null,
            type: $data['type'] ?? null,
            startDate: $data['start_date'] ?? null,
            endDate: $data['end_date'] ?? null,
            paymentMethod: $data['payment_method'] ?? null,
            search: $data['search'] ?? null,
            perPage: $data['per_page'] ?? 15,
            page: $data['page'] ?? 1,
        );
    }
}
