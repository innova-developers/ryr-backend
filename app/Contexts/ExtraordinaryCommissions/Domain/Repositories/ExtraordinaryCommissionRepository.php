<?php

namespace App\Contexts\ExtraordinaryCommissions\Domain\Repositories;

use App\Contexts\ExtraordinaryCommissions\Application\DTO\CreateExtraordinaryCommissionDTO;
use App\Contexts\ExtraordinaryCommissions\Application\DTO\GetExtraordinaryCommissionByOriginAndDestinationDTO;
use App\Contexts\ExtraordinaryCommissions\Application\DTO\UpdateExtraordinaryCommissionDTO;
use App\Shared\Models\ExtraordinaryCommission;

interface ExtraordinaryCommissionRepository
{
    public function get(): array;
    public function create(CreateExtraordinaryCommissionDTO $dto): ExtraordinaryCommission;
    public function findById(int $id): ExtraordinaryCommission;
    public function update(UpdateExtraordinaryCommissionDTO $dto): ExtraordinaryCommission;
    public function delete(int $id): void;
    public function findByOriginAndDestination(GetExtraordinaryCommissionByOriginAndDestinationDTO $dto): array;

}
