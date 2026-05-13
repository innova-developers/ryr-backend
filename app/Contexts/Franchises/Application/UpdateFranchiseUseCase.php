<?php

namespace App\Contexts\Franchises\Application;

use App\Contexts\Franchises\Application\DTO\UpdateFranchiseDTO;
use App\Contexts\Franchises\Domain\Repositories\FranchiseRepository;
use App\Shared\Models\Franchise;

class UpdateFranchiseUseCase
{
    public function __construct(private FranchiseRepository $repository)
    {
    }

    public function __invoke(UpdateFranchiseDTO $dto): Franchise
    {
        return $this->repository->update($dto);
    }
}
