<?php

namespace Database\Factories;

use App\Shared\Models\Commission;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommissionFactory extends Factory
{
    protected $model = Commission::class;

    public function definition(): array
    {
        return [
            'client_id' => 1,
            'destination_id' => 1,
            'branch_id' => 1,
            'user_id' => 1,
            'origin_location_id' => Location::factory(),
            'destination_location_id' => Location::factory(),
            'date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'status' => $this->faker->randomElement([CommissionStatus::DEPOSITO->value, CommissionStatus::ENTREGADO->value, CommissionStatus::ENTREGAR_Y_RETIRAR->value]),
            'total' => $this->faker->randomFloat(2, 100, 10000),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
