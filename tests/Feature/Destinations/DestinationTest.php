<?php

namespace Tests\Feature\Destinations;

use App\Shared\Models\Destination;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestinationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create(['role' => 'administrador']);
        $this->actingAs($user, 'sanctum');
    }

    public function test_can_create_destination(): void
    {
        $destinationData = [
            'origin' => 'Buenos Aires',
            'destination' => 'Córdoba',
            'fixed_price' => 1000.50,
            'small_bulk_price' => 800.25,
            'large_bulk_price' => 600.75,
        ];

        $response = $this->postJson('/api/destinations', $destinationData);

        $response->assertStatus(201)
            ->assertJson([
                'origin' => 'Buenos Aires',
                'destination' => 'Córdoba',
                'fixed_price' => 1000.50,
                'small_bulk_price' => 800.25,
                'large_bulk_price' => 600.75,
            ]);

        $this->assertDatabaseHas('destinations', $destinationData);
    }

    public function test_can_get_all_destinations(): void
    {
        Destination::factory()->count(3)->create();

        $response = $this->getJson('/api/destinations');

        $response->assertStatus(200)
            ->assertJsonCount(3)
            ->assertJsonStructure([
                '*' => [
                    'origin',
                    'destination',
                    'fixed_price',
                    'small_bulk_price',
                    'large_bulk_price',
                ],
            ]);
    }

    public function test_can_get_single_destination(): void
    {
        $destination = Destination::factory()->create();

        $response = $this->getJson("/api/destinations/{$destination->id}");

        $response->assertStatus(200)
            ->assertJson([
                'origin' => $destination->origin,
                'destination' => $destination->destination,
                'fixed_price' => (float) $destination->fixed_price,
                'small_bulk_price' => (float) $destination->small_bulk_price,
                'large_bulk_price' => (float) $destination->large_bulk_price,
            ]);
    }

    public function test_returns_404_when_destination_not_found(): void
    {
        $response = $this->getJson('/api/destinations/999');
        $response->assertStatus(404);
    }

    public function test_can_update_destination(): void
    {
        $destination = Destination::factory()->create();
        $updateData = [
            'origin' => 'Rosario',
            'destination' => 'Mendoza',
            'fixed_price' => 1200.00,
            'small_bulk_price' => 900.00,
            'large_bulk_price' => 700.00,
        ];

        $response = $this->putJson("/api/destinations/{$destination->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'origin' => 'Rosario',
                'destination' => 'Mendoza',
                'fixed_price' => 1200.00,
                'small_bulk_price' => 900.00,
                'large_bulk_price' => 700.00,
            ]);

        $this->assertDatabaseHas('destinations', $updateData);
    }

    public function test_can_delete_destination(): void
    {
        $destination = Destination::factory()->create();

        $response = $this->deleteJson("/api/destinations/{$destination->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('destinations', ['id' => $destination->id]);
    }

    public function test_validates_required_fields_on_create(): void
    {
        $response = $this->postJson('/api/destinations', []);

        $response->assertStatus(422);
    }

    public function test_validates_numeric_fields(): void
    {
        $invalidData = [
            'origin' => 'Buenos Aires',
            'destination' => 'Córdoba',
            'fixed_price' => 'not-a-number',
            'small_bulk_price' => 'invalid',
            'large_bulk_price' => 'wrong',
        ];

        $response = $this->postJson('/api/destinations', $invalidData);

        $response->assertStatus(422);
    }
}
