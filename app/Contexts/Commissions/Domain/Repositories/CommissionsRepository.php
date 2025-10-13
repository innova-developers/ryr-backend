<?php

namespace App\Contexts\Commissions\Domain\Repositories;

use App\Contexts\Commissions\Application\DTOs\CreateCommissionDTO;
use App\Contexts\Commissions\Application\DTOs\CreateCommissionLogDTO;
use App\Contexts\Commissions\Application\DTOs\ListCommissionsFiltersDTO;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Commission;
use Illuminate\Pagination\LengthAwarePaginator;

interface CommissionsRepository
{
    /**
     * @throws \Exception
     */
    public function create(CreateCommissionDTO $dto, int $destinationId): Commission;

    /**
     * @throws \Exception
     */
    public function update(\App\Contexts\Commissions\Application\DTOs\UpdateCommissionDTO $dto, int $destinationId): void;

    /**
     * @throws \Exception
     */
    public function addItems(int $commissionId, ?array $items): void;

    /**
     * @throws \Exception
     */
    public function deleteItems(int $commissionId): void;

    /**
     * @throws \Exception
     */
    public function findById(int $id): Commission;

    /**
     * @throws \Exception
     */
    public function findAllWithItems(ListCommissionsFiltersDTO $filters): LengthAwarePaginator;

    /**
     * @throws \Exception
     */
    public function delete(int $id): void;

    public function updateStatus(int $id, CommissionStatus $status): void;
    public function createLog(CreateCommissionLogDTO $dto): void;
    public function getTotals(ListCommissionsFiltersDTO $filters): array;
}
