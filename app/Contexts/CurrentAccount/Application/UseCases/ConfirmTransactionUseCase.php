<?php

namespace App\Contexts\CurrentAccount\Application\UseCases;

use App\Contexts\CurrentAccount\Domain\Repositories\CurrentAccountRepository;
use App\Shared\Models\CurrentAccount;

class ConfirmTransactionUseCase
{
    public function __construct(
        private readonly CurrentAccountRepository $repository
    ) {
    }

    public function __invoke(int $id): CurrentAccount
    {
        return $this->repository->confirmTransaction($id);
    }
}

