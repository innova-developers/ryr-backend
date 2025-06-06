<?php

namespace Database\Factories;

use App\Shared\Models\ExtraordinaryCommission;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExtraordinaryCommissionFactory extends Factory
{
    protected $model = ExtraordinaryCommission::class;

    public function definition(): array
    {
        return [
            'origin' => $this->faker->city,
            'destination' => $this->faker->city,
            'detail' => $this->faker->sentence,
            'price' => $this->faker->randomFloat(2, 100, 5000),
            'observations' => $this->faker->optional()->paragraph
        ];
    }
}
