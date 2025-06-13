<?php

namespace App\Contexts\Commissions\Application\DTOs;

use App\Shared\Enums\CommissionStatus;

class ListCommissionsFiltersDTO
{
    public function __construct(
        public readonly ?string $client = null,
        public readonly ?int $destinationId = null,
        public readonly ?int $branchId = null,
        public readonly ?int $userId = null,
        public readonly ?string $dateFrom = null,
        public readonly ?string $dateTo = null,
        public readonly ?CommissionStatus $status = null,
        public readonly int $page = 1,
        public readonly int $perPage = 15,
        public readonly ?string $sort = 'date',
        public readonly ?string $sortDirection = 'asc',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            client: $data['clientName'] ?? null,
            destinationId: $data['destination_id'] ?? null,
            branchId: $data['branch_id'] ?? null,
            userId: $data['user_id'] ?? null,
            dateFrom: $data['dateFrom'] ?? null,
            dateTo: $data['dateTo'] ?? null,
            status: isset($data['status']) ? CommissionStatus::from($data['status']) : null,
            page: $data['page'] ?? 1,
            perPage: $data['perPage'] ?? 15,
            sort: $data['sort_by'] ?? null,
            sortDirection: $data['sort_direction'] ?? 'asc'
        );
    }
}
