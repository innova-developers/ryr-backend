<?php

namespace App\Contexts\Incomes\Application\DTOs;

class CreateIncomeDTO
{
    public function __construct(
        public readonly int $incomeCategoryId,
        public readonly \DateTime $date,
        public readonly string $detail,
        public readonly float $amount,
        public readonly ?int $userId = null
    ) {
    }
}
