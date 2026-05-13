<?php

namespace Database\Factories;

use App\Shared\Models\MatrixReceivable;
use Illuminate\Database\Eloquent\Factories\Factory;

class MatrixReceivableFactory extends Factory
{
    protected $model = MatrixReceivable::class;

    public function definition(): array
    {
        return [
            'franchise_id' => \App\Shared\Models\Franchise::factory(),
            'commission_id' => null,
            'amount' => $this->faker->randomFloat(2, 100, 5000),
            'percentage_applied' => $this->faker->randomFloat(2, 5, 30),
            'commission_total' => $this->faker->randomFloat(2, 500, 20000),
            'status' => 'pending',
            'due_date' => $this->faker->dateTimeBetween('now', '+30 days'),
            'paid_at' => null,
            'payment_reference' => null,
            'notes' => null,
        ];
    }

    public function paid(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'paid_at' => now(),
            'payment_reference' => 'PAY-' . $this->faker->randomNumber(6),
        ]);
    }
}
