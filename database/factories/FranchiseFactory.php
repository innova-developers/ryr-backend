<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Franchise>
 */
class FranchiseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = $this->faker->unique()->slug(2);
        $name = $this->faker->company();
        
        return [
            'name' => $name,
            'code' => strtoupper($code),
            'database_name' => 'franchise_' . $code,
            'subdomain' => $this->faker->optional(0.5)->slug(1),
            'description' => $this->faker->optional(0.7)->sentence(),
            'address' => $this->faker->optional(0.8)->address(),
            'phone' => $this->faker->optional(0.6)->phoneNumber(),
            'email' => $this->faker->optional(0.7)->companyEmail(),
            'contact_person' => $this->faker->optional(0.5)->name(),
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
            'settings' => $this->faker->optional(0.3)->randomElements([
                'auto_iva' => true,
                'currency' => 'ARS',
                'timezone' => 'America/Argentina/Buenos_Aires',
            ]),
            'activated_at' => $this->faker->optional(0.8)->dateTimeBetween('-1 year', 'now'),
            'deactivated_at' => $this->faker->optional(0.1)->dateTimeBetween('-6 months', 'now'),
        ];
    }
}
