<?php

namespace App\Contexts\Incomes\Application\DTOs;

class IncomeFilterDTO
{
    public ?string $dateFrom;
    public ?string $dateTo;
    public ?int $categoryId;
    public ?int $userId;
    public ?string $search;

    public function __construct(?string $dateFrom = null, ?string $dateTo = null, ?int $categoryId = null, ?int $userId = null, ?string $search = null)
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->categoryId = $categoryId;
        $this->userId = $userId;
        $this->search = $search;
    }
}
