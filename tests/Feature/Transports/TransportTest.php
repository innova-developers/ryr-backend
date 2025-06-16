<?php

namespace Tests\Feature\Transports;

use App\Shared\Models\Transport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransportTest extends TestCase
{
    use RefreshDatabase;

    private array $validTransportData = [
        'plate' => 'ABC123',
        'description' => 'Camión de carga',
        'phone' => '1234567890',
        'insurance' => 'Seguro XYZ',
        'usage' => 'Carga general',
        'observation' => 'Observación de prueba',
    ];

    public function test_can_get_all_transports(): void
    {
        Transport::factory()->count(3)->create();

        $response = $this->getJson('/api/transports');

        $response->assertStatus(200)
            ->assertJsonCount(3)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'plate',
                    'description',
                    'phone',
                    'insurance',
                    'usage',
                    'observation',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                ],
            ]);
    }

    public function test_can_create_transport(): void
    {
        $response = $this->postJson('/api/transports', $this->validTransportData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'plate',
                'description',
                'phone',
                'insurance',
                'usage',
                'observation',
            ]);

        $this->assertDatabaseHas('transports', [
            'plate' => $this->validTransportData['plate'],
            'description' => $this->validTransportData['description'],
            'phone' => $this->validTransportData['phone'],
            'insurance' => $this->validTransportData['insurance'],
            'usage' => $this->validTransportData['usage'],
            'observation' => $this->validTransportData['observation'],
        ]);
    }

    public function test_cannot_create_transport_with_duplicate_plate(): void
    {
        Transport::factory()->create(['plate' => 'ABC123']);

        $response = $this->postJson('/api/transports', $this->validTransportData);

        $response->assertStatus(400);
    }

    public function test_cannot_create_transport_with_missing_required_fields(): void
    {
        $response = $this->postJson('/api/transports', []);

        $response->assertStatus(422);
    }

    public function test_can_update_transport(): void
    {
        $transport = Transport::factory()->create();
        $updateData = [
            'plate' => 'XYZ789',
            'description' => 'Nueva descripción',
            'phone' => '9876543210',
            'insurance' => 'Nuevo seguro',
            'usage' => 'Nuevo uso',
        ];

        $response = $this->putJson("/api/transports/{$transport->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'plate' => $updateData['plate'],
                'description' => $updateData['description'],
                'phone' => $updateData['phone'],
                'insurance' => $updateData['insurance'],
                'usage' => $updateData['usage'],
            ]);
    }

    public function test_cannot_update_nonexistent_transport(): void
    {
        $response = $this->putJson('/api/transports/999', $this->validTransportData);

        $response->assertStatus(400);
    }

    public function test_can_delete_transport(): void
    {
        $transport = Transport::factory()->create();

        $response = $this->deleteJson("/api/transports/{$transport->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('transports', ['id' => $transport->id]);
    }

    public function test_cannot_delete_nonexistent_transport(): void
    {
        $response = $this->deleteJson('/api/transports/999');

        $response->assertStatus(400);
    }
}
