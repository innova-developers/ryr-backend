<?php

namespace App\Contexts\CurrentAccount\Application\UseCases;

use App\Contexts\CurrentAccount\Domain\Repositories\CurrentAccountRepository;

class GetCustomerBalanceUseCase
{
    public function __construct(
        private readonly CurrentAccountRepository $repository
    ) {
    }

    public function __invoke(int $customerId): float
    {
        return $this->repository->getCustomerBalance($customerId);
    }
}
