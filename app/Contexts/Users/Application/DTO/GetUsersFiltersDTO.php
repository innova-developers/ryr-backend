<?php

namespace App\Contexts\Users\Application\DTO;

class GetUsersFiltersDTO
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly int $page = 1,
        public readonly int $perPage = 10,
        public readonly ?string $sortBy = null,
        public readonly string $sortDirection = 'asc'
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            search: $data['search'] ?? null,
            page: (int) ($data['page'] ?? 1),
            // Sin per_page explícito se listan todos; si se pide, se topea a 100.
            perPage: isset($data['per_page']) ? min(100, max(1, (int) $data['per_page'])) : 999999,
            sortBy: $data['sort_by'] ?? null,
            sortDirection: $data['sort_direction'] ?? 'asc'
        );
    }
}
