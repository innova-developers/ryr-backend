<?php

namespace Tests\Feature\Public;

use App\Shared\Enums\CommissionItemSize;
use App\Shared\Enums\CommissionItemType;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicCommissionControllerTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private Destination $destination;
    private Location $originLocation;
    private Location $destinationLocation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::factory()->create();
        $this->destination = Destination::factory()->create();
        $this->originLocation = Location::factory()->create();
        $this->destinationLocation = Location::factory()->create();
    }

    public function test_can_create_commission_with_formdata(): void
    {
        Storage::fake('public');

        $commissionData = [
            'client_id' => $this->customer->id,
            'date' => '2025-09-12',
            'origin' => $this->destination->origin,
            'destination' => $this->destination->destination,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => 'PENDIENTE', // El frontend puede enviar cualquier estado, se forzará a SOLICITUD_RECIBIDA
            'a_cuenta' => false,
            'items' => [
                [
                    'type' => CommissionItemType::ORDINARIA->value,
                    'size' => CommissionItemSize::LARGE->value,
                    'quantity' => 1,
                    'unit_price' => 1500,
                    'subtotal' => 1500,
                ]
            ],
            'total' => 1500,
            'notes' => 'Comisión con notas desde FormData',
        ];

        $paymentProof = UploadedFile::fake()->create('payment_proof.pdf', 100);

        $response = $this->postJson('/api/commissions/public', [
            'commission' => json_encode($commissionData),
            'payment_proof' => $paymentProof,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Comisión creada correctamente',
                'commission' => [
                    'id' => 1,
                    'tracking_number' => '1',
                    'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
                    'total' => 1500,
                    'notes' => 'Comisión con notas desde FormData',
                ],
            ]);

        $this->assertDatabaseHas('commissions', [
            'client_id' => $this->customer->id,
            'total' => 1500,
            'notes' => 'Comisión con notas desde FormData',
        ]);

        $this->assertDatabaseCount('commission_items', 1);
    }

    public function test_can_create_commission_without_notes(): void
    {
        $commissionData = [
            'client_id' => $this->customer->id,
            'date' => '2025-09-12',
            'origin' => $this->destination->origin,
            'destination' => $this->destination->destination,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => 'PENDIENTE', // El frontend puede enviar cualquier estado, se forzará a SOLICITUD_RECIBIDA
            'a_cuenta' => false,
            'total' => 1000,
        ];

        $response = $this->postJson('/api/commissions/public', [
            'commission' => json_encode($commissionData),
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('commissions', [
            'client_id' => $this->customer->id,
            'total' => 1000,
            'notes' => null,
        ]);
    }

    public function test_validates_notes_max_length(): void
    {
        $commissionData = [
            'client_id' => $this->customer->id,
            'date' => '2025-09-12',
            'origin' => $this->destination->origin,
            'destination' => $this->destination->destination,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => 'PENDIENTE', // El frontend puede enviar cualquier estado, se forzará a SOLICITUD_RECIBIDA
            'total' => 1000,
            'notes' => str_repeat('a', 1001), // Más de 1000 caracteres
        ];

        $response = $this->postJson('/api/commissions/public', [
            'commission' => json_encode($commissionData),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['notes']);
    }

    public function test_handles_direct_json_data(): void
    {
        $commissionData = [
            'client_id' => $this->customer->id,
            'date' => '2025-09-12',
            'origin' => $this->destination->origin,
            'destination' => $this->destination->destination,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => 'PENDIENTE', // El frontend puede enviar cualquier estado, se forzará a SOLICITUD_RECIBIDA
            'total' => 1000,
            'notes' => 'Notas directas',
        ];

        $response = $this->postJson('/api/commissions/public', $commissionData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('commissions', [
            'client_id' => $this->customer->id,
            'notes' => 'Notas directas',
            'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
        ]);
    }

    public function test_always_creates_with_solicitud_recibida_status(): void
    {
        // Probar con diferentes estados que el frontend podría enviar
        $invalidStatuses = ['PENDIENTE', 'INVALID_STATUS', 'CUALQUIER_ESTADO'];

        foreach ($invalidStatuses as $status) {
            $commissionData = [
                'client_id' => $this->customer->id,
                'date' => '2025-09-12',
                'origin' => $this->destination->origin,
                'destination' => $this->destination->destination,
                'origin_location_id' => $this->originLocation->id,
                'destination_location_id' => $this->destinationLocation->id,
                'status' => $status,
                'total' => 1000,
            ];

            $response = $this->postJson('/api/commissions/public', $commissionData);

            $response->assertStatus(201);

            $this->assertDatabaseHas('commissions', [
                'client_id' => $this->customer->id,
                'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
            ]);
        }
    }
}
