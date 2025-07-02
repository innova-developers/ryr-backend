<?php

namespace App\Contexts\Expenses\Application\DTOs;

class ExpenseFilterDTO
{
    public ?string $dateFrom;
    public ?string $dateTo;
    public ?int $categoryId;
    public ?int $transportId;

    public function __construct(?string $dateFrom = null, ?string $dateTo = null, ?int $categoryId = null, ?int $transportId = null)
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->categoryId = $categoryId;
        $this->transportId = $transportId;
    }
}
