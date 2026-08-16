<?php

namespace App\Contexts\ExpenseCategories\Application\DTO;

class CreateExpenseCategoryDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $description,
        public readonly bool $isActive = true,
        // RC-487: marca la categoría como gasto extraordinario. Antes se deducía
        // buscando "extra" en el nombre, que no matcheaba ninguna de las 107 categorías.
        public readonly bool $isExtraordinary = false,
    ) {
    }
}
