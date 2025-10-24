<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\SuperAdmin>
 */
class SuperAdminFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'phone' => $this->faker->optional(0.7)->phoneNumber(),
            'is_active' => $this->faker->boolean(90), // 90% chance of being active
            'permissions' => $this->faker->optional(0.3)->randomElements([
                'franchise.create',
                'franchise.update',
                'franchise.delete',
                'franchise.activate',
                'franchise.deactivate',
                'dashboard.view',
                'reports.view',
            ], $this->faker->numberBetween(1, 7)),
            'last_login_at' => $this->faker->optional(0.8)->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
