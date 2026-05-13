<?php

namespace App\Contexts\Franchises\Application;

use App\Contexts\Franchises\Application\DTO\CreateFranchiseDTO;
use App\Contexts\Franchises\Domain\Repositories\FranchiseRepository;
use App\Shared\Models\Franchise;

class CreateFranchiseUseCase
{
    public function __construct(private FranchiseRepository $repository)
    {
    }

    public function __invoke(CreateFranchiseDTO $dto): Franchise
    {
        return $this->repository->create($dto);
    }
}
