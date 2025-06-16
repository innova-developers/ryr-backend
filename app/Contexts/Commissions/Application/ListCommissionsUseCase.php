<?php

namespace App\Contexts\Commissions\Application;

use App\Contexts\Commissions\Application\DTOs\ListCommissionsFiltersDTO;
use App\Contexts\Commissions\Application\Mappers\CommissionsMapper;
use App\Contexts\Commissions\Domain\Repositories\CommissionsRepository;

class ListCommissionsUseCase
{
    public function __construct(
        private readonly CommissionsRepository $repository
    ) {
    }

    public function __invoke(ListCommissionsFiltersDTO $filters): array
    {
        $result = $this->repository->findAllWithItems($filters);

        return [
            'data' => CommissionsMapper::fromDomainToArray($result->items()),
            'meta' => [
                'current_page' => $result->currentPage(),
                'last_page' => $result->lastPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ];
    }
}
