<?php

namespace Database\Factories;

use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\FeedbackSurvey;
use App\Shared\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FeedbackSurveyFactory extends Factory
{
    protected $model = FeedbackSurvey::class;

    public function definition(): array
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $customer = Customer::factory()->create();
        $destination = Destination::factory()->create();

        return [
            'commission_id' => Commission::factory()->create([
                'client_id' => $customer->id,
                'destination_id' => $destination->id,
                'branch_id' => $branch->id,
                'user_id' => $user->id,
            ])->id,
            'customer_id' => $customer->id,
            'franchise_id' => null,
            'rating' => null,
            'comment' => null,
            'status' => 'pending',
            'token' => Str::random(64),
            'sent_at' => now(),
        ];
    }

    public function responded(): static
    {
        return $this->state(fn () => [
            'rating' => $this->faker->numberBetween(1, 5),
            'comment' => $this->faker->sentence(),
            'status' => 'responded',
            'responded_at' => now(),
        ]);
    }
}
