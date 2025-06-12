<?php

namespace App\Contexts\Commissions\Domain\Repositories;

use App\Contexts\Commissions\Application\DTOs\CreateCommissionDTO;
use App\Shared\Models\Commission;
use App\Contexts\Commissions\Application\DTOs\ListCommissionsFiltersDTO;
use Illuminate\Pagination\LengthAwarePaginator;

interface CommissionsRepository
{
    public function create(CreateCommissionDTO $dto,int $destinationId): Commission;
    public function addItems(int $commissionId, array $items): void;
    public function findById(int $id): Commission;
    public function findAllWithItems(ListCommissionsFiltersDTO $filters): LengthAwarePaginator;
}
