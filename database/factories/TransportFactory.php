<?php

namespace Database\Factories;

use App\Shared\Models\Transport;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransportFactory extends Factory
{
    protected $model = Transport::class;

    public function definition(): array
    {
        return [
            'plate' => strtoupper($this->faker->bothify('???###')),
            'description' => $this->faker->sentence(),
            'phone' => $this->faker->phoneNumber(),
            'insurance' => $this->faker->company() . ' Insurance',
            'usage' => $this->faker->randomElement(['Carga general', 'Carga refrigerada', 'Pasajeros', 'Mudanzas']),
            'observation' => $this->faker->optional()->sentence(),
        ];
    }
}
