<?php

namespace App\Contexts\Expenses\Application\DTOs;

use DateTime;

class UpdateExpenseDTO
{
    public function __construct(
        public readonly int $id,
        public readonly ?int $transportId,
        public readonly ?int $expenseCategoryId,
        public readonly DateTime $date,
        public readonly string $detail,
        public readonly float $amount
    ) {
    }
}
