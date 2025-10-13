<?php

namespace App\Contexts\IncomeCategories\Application;

use App\Contexts\IncomeCategories\Application\DTOs\UpdateIncomeCategoryDTO;
use App\Contexts\IncomeCategories\Domain\Repositories\IncomeCategoriesRepository;

class UpdateIncomeCategoryUseCase
{
    public function __construct(
        private readonly IncomeCategoriesRepository $repository
    ) {
    }

    public function __invoke(UpdateIncomeCategoryDTO $dto): array
    {
        $category = $this->repository->update($dto);

        return [
            'id' => $category->id,
            'name' => $category->name,
            'description' => $category->description,
        ];
    }
}
