<?php

namespace App\Contexts\Branchs\Application\DTO;

class GetBranchesFiltersDTO
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
            perPage: (int) ($data['per_page'] ?? 10),
            sortBy: $data['sort_by'] ?? null,
            sortDirection: $data['sort_direction'] ?? 'asc'
        );
    }
}
