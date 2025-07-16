<?php

namespace App\Contexts\CurrentAccount\Application\UseCases;

use App\Contexts\CurrentAccount\Application\DTO\CreateCurrentAccountDTO;
use App\Contexts\CurrentAccount\Domain\Repositories\CurrentAccountRepository;
use App\Shared\Models\CurrentAccount;

class CreateCurrentAccountUseCase
{
    public function __construct(
        private readonly CurrentAccountRepository $repository
    ) {
    }

    public function __invoke(CreateCurrentAccountDTO $dto): CurrentAccount
    {
        return $this->repository->create($dto);
    }
}
