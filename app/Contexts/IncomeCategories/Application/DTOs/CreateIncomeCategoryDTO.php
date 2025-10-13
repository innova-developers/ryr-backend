<?php

namespace App\Contexts\IncomeCategories\Application\DTOs;

class CreateIncomeCategoryDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null
    ) {
    }
}
