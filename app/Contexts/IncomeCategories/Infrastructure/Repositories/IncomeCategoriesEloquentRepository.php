<?php

namespace App\Contexts\IncomeCategories\Infrastructure\Repositories;

use App\Contexts\IncomeCategories\Application\DTOs\CreateIncomeCategoryDTO;
use App\Contexts\IncomeCategories\Application\DTOs\UpdateIncomeCategoryDTO;
use App\Contexts\IncomeCategories\Domain\Repositories\IncomeCategoriesRepository;
use App\Shared\Models\IncomeCategory;
use Illuminate\Database\Eloquent\Collection;

class IncomeCategoriesEloquentRepository implements IncomeCategoriesRepository
{
    public function findById(int $id): IncomeCategory
    {
        $category = IncomeCategory::find($id);

        if (! $category) {
            throw new \Exception('Categoría de ingreso no encontrada');
        }

        return $category;
    }

    public function findAll(): Collection
    {
        return IncomeCategory::orderBy('name')->get();
    }

    public function create(CreateIncomeCategoryDTO $dto): IncomeCategory
    {
        try {
            $category = new IncomeCategory();
            $category->name = $dto->name;
            $category->description = $dto->description;
            $category->save();

            return $category;
        } catch (\Exception $e) {
            throw new \Exception('Error al crear la categoría de ingreso: ' . $e->getMessage());
        }
    }

    public function update(UpdateIncomeCategoryDTO $dto): IncomeCategory
    {
        $category = IncomeCategory::find($dto->id);

        if (! $category) {
            throw new \Exception('Categoría de ingreso no encontrada');
        }

        try {
            $category->name = $dto->name;
            $category->description = $dto->description;
            $category->save();

            return $category;
        } catch (\Exception $e) {
            throw new \Exception('Error al actualizar la categoría de ingreso: ' . $e->getMessage());
        }
    }

    public function delete(int $id): void
    {
        $category = IncomeCategory::find($id);

        if (! $category) {
            throw new \Exception('Categoría de ingreso no encontrada');
        }

        try {
            $category->delete();
        } catch (\Exception $e) {
            throw new \Exception('Error al eliminar la categoría de ingreso: ' . $e->getMessage());
        }
    }
}
