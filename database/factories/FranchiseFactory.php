<?php

namespace Database\Factories;

use App\Shared\Models\Franchise;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FranchiseFactory extends Factory
{
    protected $model = Franchise::class;

    public function definition(): array
    {
        $name = $this->faker->company;

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . $this->faker->unique()->randomNumber(4),
            'address' => $this->faker->address,
            'phone' => $this->faker->phoneNumber,
            'email' => $this->faker->companyEmail,
            'commission_percentage_to_matrix' => $this->faker->randomFloat(2, 5, 30),
            'owner_user_id' => null,
            'status' => 'active',
            'contract_start_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'contract_end_date' => $this->faker->optional(0.5)->dateTimeBetween('+1 year', '+3 years'),
            'settings' => null,
        ];
    }

    public function active(): self
    {
        return $this->state(fn (array $attributes) => ['status' => 'active']);
    }

    public function suspended(): self
    {
        return $this->state(fn (array $attributes) => ['status' => 'suspended']);
    }

    public function terminated(): self
    {
        return $this->state(fn (array $attributes) => ['status' => 'terminated']);
    }
}
