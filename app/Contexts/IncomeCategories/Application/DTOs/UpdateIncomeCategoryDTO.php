<?php

namespace App\Contexts\IncomeCategories\Application\DTOs;

class UpdateIncomeCategoryDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description = null
    ) {
    }
}
