<?php

namespace Database\Factories;

use App\Shared\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company,
            'address' => $this->faker->address,
            'schedule' => $this->faker->time(),
            'phone' => $this->faker->phoneNumber,
        ];
    }

}
