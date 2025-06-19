<?php

namespace App\Contexts\Expenses\Application\DTOs;

use DateTime;

class CreateExpenseDTO
{
    public function __construct(
        public readonly int $transportId,
        public readonly DateTime $date,
        public readonly string $detail,
        public readonly float $amount
    ) {
    }
}
