<?php

namespace Tests\Feature\Locations;

use App\Shared\Models\Location;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetLocationsByOriginTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'administrador']);
        $this->actingAs($this->user, 'sanctum');
    }

    public function test_can_get_locations_by_origin(): void
    {
        // Arrange
        $origin = 'Buenos Aires';
        $locations = Location::factory()->count(3)->create(['origin' => $origin]);
        Location::factory()->create(['origin' => 'Córdoba']); // Otra ubicación que no debería aparecer

        // Act
        $response = $this->getJson("/api/locations/origin/{$origin}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(3)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'name',
                    'address',
                    'origin',
                    'phone',
                    'map',
                    'schedule',
                    'observation',
                ],
            ]);

        foreach ($locations as $location) {
            $response->assertJsonFragment([
                'name' => $location->name,
                'origin' => $origin,
            ]);
        }
    }

    public function test_returns_empty_array_when_no_locations_found(): void
    {
        // Act
        $response = $this->getJson('/api/locations/origin/NonExistentOrigin');

        // Assert
        $response->assertStatus(200)
            ->assertJson([]);
    }
}
