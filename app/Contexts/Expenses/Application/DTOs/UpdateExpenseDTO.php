<?php

namespace App\Contexts\Expenses\Application\DTOs;

use DateTime;

class UpdateExpenseDTO
{
    public function __construct(
        public readonly int $id,
        public readonly DateTime $date,
        public readonly string $detail,
        public readonly float $amount
    ) {
    }
}
