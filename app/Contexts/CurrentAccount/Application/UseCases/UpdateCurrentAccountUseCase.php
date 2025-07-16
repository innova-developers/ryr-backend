<?php

namespace App\Contexts\CurrentAccount\Application\UseCases;

use App\Contexts\CurrentAccount\Application\DTO\UpdateCurrentAccountDTO;
use App\Contexts\CurrentAccount\Domain\Repositories\CurrentAccountRepository;
use App\Shared\Models\CurrentAccount;

class UpdateCurrentAccountUseCase
{
    public function __construct(
        private readonly CurrentAccountRepository $repository
    ) {
    }

    public function __invoke(UpdateCurrentAccountDTO $dto): CurrentAccount
    {
        return $this->repository->update($dto);
    }
}
