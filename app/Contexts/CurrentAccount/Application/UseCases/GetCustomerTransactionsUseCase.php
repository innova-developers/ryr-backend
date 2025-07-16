<?php

namespace App\Contexts\CurrentAccount\Application\UseCases;

use App\Contexts\CurrentAccount\Application\DTO\CurrentAccountFilterDTO;
use App\Contexts\CurrentAccount\Domain\Repositories\CurrentAccountRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class GetCustomerTransactionsUseCase
{
    public function __construct(
        private readonly CurrentAccountRepository $repository
    ) {
    }

    public function __invoke(int $customerId, CurrentAccountFilterDTO $filter): LengthAwarePaginator
    {
        return $this->repository->getCustomerTransactions($customerId, $filter);
    }
}
