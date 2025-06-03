<?php

namespace App\Contexts\Branchs\Domain\Repositories;

use App\Contexts\Branchs\Application\DTO\CreateBranchDTO;
use App\Contexts\Branchs\Application\DTO\UpdateBranchDTO;
use App\Shared\Models\Branch;

interface BranchRepository
{
    public function get(): array;
    public function findById(int $id): ?Branch;
    public function create(CreateBranchDTO $dto): Branch;
    public function update(UpdateBranchDTO $dto): Branch;
    public function delete(int $id): bool;
}
