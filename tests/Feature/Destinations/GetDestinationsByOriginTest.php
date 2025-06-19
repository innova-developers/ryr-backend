<?php

namespace Tests\Feature\Destinations;

use App\Shared\Models\Destination;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetDestinationsByOriginTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'administrador']);
        $this->actingAs($this->user, 'sanctum');
    }

    public function test_can_get_destinations_by_origin(): void
    {
        // Arrange
        $origin = 'Buenos Aires';
        $destinations = Destination::factory()->count(3)->create(['origin' => $origin]);
        Destination::factory()->create(['origin' => 'Cordoba']);

        // Act
        $response = $this->getJson("/api/destinations/origin/{$origin}");
        // Assert
        $response->assertStatus(200);
        $response->assertJsonCount(3, );
    }

    public function test_returns_empty_array_when_no_destinations_found(): void
    {
        // Act
        $response = $this->getJson('/api/destinations/origin/NonExistentOrigin');

        // Assert
        $response->assertStatus(200)
            ->assertJson([]);
    }

    public function test_can_get_destination_by_id(): void
    {
        // Arrange
        $destination = Destination::factory()->create();

        // Act
        $response = $this->getJson("/api/destinations/{$destination->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'origin' => $destination->origin,
                'destination' => $destination->destination,
            ]);
    }
}
