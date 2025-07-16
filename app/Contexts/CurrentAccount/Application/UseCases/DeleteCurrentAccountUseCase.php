<?php

namespace App\Contexts\CurrentAccount\Application\UseCases;

use App\Contexts\CurrentAccount\Domain\Repositories\CurrentAccountRepository;

class DeleteCurrentAccountUseCase
{
    public function __construct(
        private readonly CurrentAccountRepository $repository
    ) {
    }

    public function __invoke(int $id): bool
    {
        return $this->repository->delete($id);
    }
}
