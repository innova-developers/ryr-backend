<?php

namespace Database\Factories;

use App\Shared\Models\Commission;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\PaymentMethod;
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
            'date' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'status' => $this->faker->randomElement([CommissionStatus::SOLICITUD_RECIBIDA->value, CommissionStatus::ENTREGADO->value, CommissionStatus::EN_PROCESO_ENTREGA->value]),
            'payment_method' => $this->faker->optional(0.7)->randomElement(PaymentMethod::cases()),
            'total' => $this->faker->randomFloat(2, 100, 10000),
            'cadete_id' => null, // Por defecto sin cadete asignado
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
