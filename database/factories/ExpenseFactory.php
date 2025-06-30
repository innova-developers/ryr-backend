<?php

namespace Database\Factories;

use App\Shared\Models\Expense;
use App\Shared\Models\ExpenseCategory;
use App\Shared\Models\Transport;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'transport_id' => Transport::factory(),
            'expense_category_id' => ExpenseCategory::factory(),
            'date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'detail' => $this->faker->sentence(),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
        ];
    }

    public function withoutTransport(): static
    {
        return $this->state(fn (array $attributes) => [
            'transport_id' => null,
        ]);
    }

    public function withoutCategory(): static
    {
        return $this->state(fn (array $attributes) => [
            'expense_category_id' => null,
        ]);
    }
} 