<?php

namespace App\Contexts\Commissions\Application\DTOs;

use App\Shared\Enums\CommissionStatus;

class ListCommissionsFiltersDTO
{
    public function __construct(
        public readonly ?int $clientId = null,
        public readonly ?int $destinationId = null,
        public readonly ?int $branchId = null,
        public readonly ?int $userId = null,
        public readonly ?string $dateFrom = null,
        public readonly ?string $dateTo = null,
        public readonly ?CommissionStatus $status = null,
        public readonly int $page = 1,
        public readonly int $perPage = 15
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            clientId: $data['client_id'] ?? null,
            destinationId: $data['destination_id'] ?? null,
            branchId: $data['branch_id'] ?? null,
            userId: $data['user_id'] ?? null,
            dateFrom: $data['date_from'] ?? null,
            dateTo: $data['date_to'] ?? null,
            status: isset($data['status']) ? CommissionStatus::from($data['status']) : null,
            page: $data['page'] ?? 1,
            perPage: $data['per_page'] ?? 15
        );
    }
} 