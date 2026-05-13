<?php

namespace Database\Factories;

use App\Shared\Models\User;
use App\Shared\Models\WhatsAppCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

class WhatsAppCampaignFactory extends Factory
{
    protected $model = WhatsAppCampaign::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'message_template' => 'Hola {nombre}, ' . $this->faker->sentence(),
            'status' => 'draft',
            'segment_filters' => null,
            'created_by' => User::factory()->create(['role' => 'administrador'])->id,
            'total_recipients' => 0,
            'sent_count' => 0,
            'failed_count' => 0,
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => 'scheduled',
            'scheduled_at' => now()->addHours(2),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);
    }
}
