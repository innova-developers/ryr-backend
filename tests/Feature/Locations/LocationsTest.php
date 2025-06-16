<?php

namespace Tests\Feature\Locations;

use App\Shared\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_locations(): void
    {
        Location::factory()->count(3)->create();

        $response = $this->getJson('/api/locations');

        $response->assertStatus(200)
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
    }

    public function test_can_create_location(): void
    {
        $data = [
            'name' => 'Test Location',
            'address' => 'Test Address',
            'origin' => 'Test Origin',
            'phone' => '1234567890',
            'map' => 'https://maps.google.com',
            'schedule' => '9:00 - 18:00',
            'observation' => 'Test observation',
        ];

        $response = $this->postJson('/api/locations', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'name',
                'address',
                'origin',
                'phone',
                'map',
                'schedule',
                'observation',
            ]);

        $this->assertDatabaseHas('locations', [
            'name' => $data['name'],
            'address' => $data['address'],
            'origin' => $data['origin'],
            'phone' => $data['phone'],
            'map' => $data['map'],
            'schedule' => $data['schedule'],
            'observation' => $data['observation'],
        ]);
    }

    public function test_cannot_create_location_without_required_fields(): void
    {
        $response = $this->postJson('/api/locations', []);

        $response->assertStatus(422);
    }

    public function test_can_update_location(): void
    {
        $location = Location::factory()->create();

        $data = [
            'name' => 'Updated Location',
            'address' => 'Updated Address',
            'origin' => 'Updated Origin',
            'phone' => '0987654321',
            'map' => 'https://maps.google.com/updated',
            'schedule' => '10:00 - 19:00',
            'observation' => 'Updated observation',
        ];

        $response = $this->putJson("/api/locations/{$location->id}", $data);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'address',
                'origin',
                'phone',
                'map',
                'schedule',
                'observation',
            ]);

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'name' => $data['name'],
            'address' => $data['address'],
            'origin' => $data['origin'],
            'phone' => $data['phone'],
            'map' => $data['map'],
            'schedule' => $data['schedule'],
            'observation' => $data['observation'],
        ]);
    }

    public function test_cannot_update_nonexistent_location(): void
    {
        $data = [
            'name' => 'Test Location',
            'address' => 'Test Address',
            'origin' => 'Test Origin',
            'phone' => '1234567890',
            'schedule' => '9:00 - 18:00',
        ];

        $response = $this->putJson('/api/locations/999', $data);

        $response->assertStatus(404);
    }

    public function test_can_delete_location(): void
    {
        $location = Location::factory()->create();

        $response = $this->deleteJson("/api/locations/{$location->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('locations', [
            'id' => $location->id,
        ]);
    }

    public function test_cannot_delete_nonexistent_location(): void
    {
        $response = $this->deleteJson('/api/locations/999');

        $response->assertStatus(404);
    }
}
