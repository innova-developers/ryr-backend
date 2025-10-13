<?php

namespace App\Contexts\ExpenseCategories\Application\DTO;

class UpdateExpenseCategoryDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $description,
        public readonly bool $isActive,
    ) {
    }
}
