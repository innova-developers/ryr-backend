<?php

namespace Database\Factories;

use App\Shared\Models\Income;
use App\Shared\Models\IncomeCategory;
use App\Shared\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class IncomeFactory extends Factory
{
    protected $model = Income::class;

    public function definition(): array
    {
        return [
            'income_category_id' => IncomeCategory::factory(),
            'user_id' => $this->faker->optional()->randomElement([User::factory(), null]),
            'date' => $this->faker->date(),
            'detail' => $this->faker->sentence(),
            'amount' => $this->faker->randomFloat(2, 10, 10000),
        ];
    }
} 