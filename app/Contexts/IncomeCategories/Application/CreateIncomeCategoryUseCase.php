<?php

namespace App\Contexts\IncomeCategories\Application;

use App\Contexts\IncomeCategories\Application\DTOs\CreateIncomeCategoryDTO;
use App\Contexts\IncomeCategories\Domain\Repositories\IncomeCategoriesRepository;

class CreateIncomeCategoryUseCase
{
    public function __construct(
        private readonly IncomeCategoriesRepository $repository
    ) {
    }

    public function __invoke(CreateIncomeCategoryDTO $dto): array
    {
        $category = $this->repository->create($dto);

        return [
            'id' => $category->id,
            'name' => $category->name,
            'description' => $category->description,
        ];
    }
}
