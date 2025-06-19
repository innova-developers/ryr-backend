<?php

namespace Database\Factories;

use App\Shared\Models\Expense;
use App\Shared\Models\Transport;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'transport_id' => Transport::factory(),
            'date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'detail' => $this->faker->sentence(),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
        ];
    }
} 