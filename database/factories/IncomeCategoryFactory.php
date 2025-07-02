<?php

namespace Database\Factories;

use App\Shared\Models\IncomeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class IncomeCategoryFactory extends Factory
{
    protected $model = IncomeCategory::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
        ];
    }
} 