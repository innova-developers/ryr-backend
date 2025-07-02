<?php

namespace App\Contexts\IncomeCategories\Domain\Repositories;

use App\Contexts\IncomeCategories\Application\DTOs\CreateIncomeCategoryDTO;
use App\Contexts\IncomeCategories\Application\DTOs\UpdateIncomeCategoryDTO;
use App\Shared\Models\IncomeCategory;
use Illuminate\Database\Eloquent\Collection;

interface IncomeCategoriesRepository
{
    public function findById(int $id): IncomeCategory;
    public function findAll(): Collection;
    public function create(CreateIncomeCategoryDTO $dto): IncomeCategory;
    public function update(UpdateIncomeCategoryDTO $dto): IncomeCategory;
    public function delete(int $id): void;
}
