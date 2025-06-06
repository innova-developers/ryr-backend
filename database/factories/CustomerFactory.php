<?php

namespace Database\Factories;

use App\Shared\Models\Customer;
use App\Shared\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'dni' => $this->faker->unique()->numerify('########'),
            'name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'mobile' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'address' => $this->faker->address(),
            'city' => $this->faker->city(),
            'phone' => $this->faker->phoneNumber(),
            'maps_url' => $this->faker->url(),
            'business_hours' => '9-18',
            'observations' => $this->faker->sentence(),
            'is_premium' => $this->faker->boolean(),
            'user_id' => User::factory()->create(['role' => 'customer'])->id
        ];
    }
} 