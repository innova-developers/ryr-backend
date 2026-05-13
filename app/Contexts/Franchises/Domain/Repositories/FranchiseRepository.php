<?php

namespace App\Contexts\Franchises\Domain\Repositories;

use App\Contexts\Franchises\Application\DTO\CreateFranchiseDTO;
use App\Contexts\Franchises\Application\DTO\GetFranchisesFiltersDTO;
use App\Contexts\Franchises\Application\DTO\UpdateFranchiseDTO;
use App\Shared\Models\Franchise;

interface FranchiseRepository
{
    public function get(?GetFranchisesFiltersDTO $filters = null): array;
    public function findById(int $id): ?Franchise;
    public function findBySlug(string $slug): ?Franchise;
    public function create(CreateFranchiseDTO $dto): Franchise;
    public function update(UpdateFranchiseDTO $dto): Franchise;
    public function delete(int $id): bool;
    public function getDashboardStats(int $franchiseId, ?string $dateFrom = null, ?string $dateTo = null): array;
    public function getSettlementReport(int $franchiseId, string $dateFrom, string $dateTo): array;
}
