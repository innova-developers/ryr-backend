<?php

namespace Database\Factories;

use App\Shared\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company,
            'address' => $this->faker->address,
            'origin' => $this->faker->city,
            'phone' => $this->faker->phoneNumber,
            'map' => $this->faker->url,
            'schedule' => '9:00 - 18:00',
            'observation' => $this->faker->sentence
        ];
    }
} 