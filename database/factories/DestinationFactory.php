<?php

namespace Database\Factories;

use App\Shared\Models\Destination;
use Illuminate\Database\Eloquent\Factories\Factory;

class DestinationFactory extends Factory
{
    protected $model = Destination::class;

    public function definition(): array
    {
        return [
            'origin' => $this->faker->city,
            'destination' => $this->faker->city,
            'fixed_price' => $this->faker->randomFloat(2, 500, 2000),
            'small_bulk_price' => $this->faker->randomFloat(2, 300, 1500),
            'large_bulk_price' => $this->faker->randomFloat(2, 200, 1000),
        ];
    }
} 