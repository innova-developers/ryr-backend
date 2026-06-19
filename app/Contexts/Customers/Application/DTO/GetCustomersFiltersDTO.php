<?php

namespace App\Contexts\Customers\Application\DTO;

class GetCustomersFiltersDTO
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly int $page = 1,
        public readonly int $perPage = 10,
        public readonly ?string $sortBy = null,
        public readonly string $sortDirection = 'asc',
        public readonly bool $resume = false,
        public readonly ?string $dateFrom = null,
        public readonly ?string $dateTo = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            search: $data['search'] ?? null,
            page: (int) ($data['page'] ?? 1),
            perPage: min(100, max(1, (int) ($data['per_page'] ?? 10))),
            sortBy: $data['sort_by'] ?? null,
            sortDirection: $data['sort_direction'] ?? 'asc',
            resume: isset($data['resume']) && ($data['resume'] === true || $data['resume'] === 'true'),
            dateFrom: $data['date_from'] ?? null,
            dateTo: $data['date_to'] ?? null
        );
    }
}
