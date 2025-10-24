<?php

namespace Tests\Feature\Commissions;

use App\Shared\Enums\CommissionItemSize;
use App\Shared\Enums\CommissionItemType;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Customer $customer;
    private Destination $destination;
    private Branch $branch;
    private Location $originLocation;
    private Location $destinationLocation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'administrador']);
        $this->customer = Customer::factory()->create();
        $this->destination = Destination::factory()->create();
        $this->branch = Branch::factory()->create();
        $this->originLocation = Location::factory()->create();
        $this->destinationLocation = Location::factory()->create();
    }

    public function test_can_create_commission(): void
    {
        $this->actingAs($this->user);

        $data = [
            'client_id' => $this->customer->id,
            'date' => '2024-03-21',
            'origin' => $this->destination->origin,
            'destination' => $this->destination->destination,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'items' => [
                [
                    'type' => CommissionItemType::ORDINARIA->value,
                    'size' => CommissionItemSize::SMALL->value,
                    'quantity' => 2,
                    'unit_price' => 500,
                    'subtotal' => 1000,
                ],
                [
                    'type' => CommissionItemType::ORDINARIA->value,
                    'size' => CommissionItemSize::LARGE->value,
                    'quantity' => 1,
                    'unit_price' => 800,
                    'subtotal' => 800,
                ],
                [
                    'type' => CommissionItemType::EXTRAORDINARIA->value,
                    'quantity' => 1,
                    'unit_price' => 1500,
                    'subtotal' => 1500,
                    'detail' => 'Manejo especial',
                ],
            ],
            'total' => 3300,
            'notes' => 'Comisión con notas especiales',
        ];

        $response = $this->postJson('/api/commissions', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('commissions', [
            'client_id' => $this->customer->id,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'notes' => 'Comisión con notas especiales',
        ]);

        $this->assertDatabaseCount('commission_items', 3);
    }

    public function test_can_get_commission_statuses(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/commissions/statuses');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'value',
                    'label',
                ],
            ]);

        $statuses = collect($response->json())->pluck('value')->toArray();
        $this->assertEquals(
            array_map(fn ($status) => $status->value, CommissionStatus::cases()),
            $statuses
        );

        // Verificar que las etiquetas sean las del admin
        $statuses = $response->json();
        $this->assertEquals('Solicitud recibida', $statuses[0]['label']);
    }

    public function test_can_get_client_commission_statuses(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/commissions/statuses/client');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'value',
                    'label',
                ],
            ]);

        // Verificar que las etiquetas sean las del cliente
        $statuses = $response->json();
        $this->assertEquals('Solicitud recibida', $statuses[0]['label']);
    }

    public function test_can_create_commission_without_items(): void
    {
        $this->actingAs($this->user);

        $data = [
            'client_id' => $this->customer->id,
            'date' => '2024-03-21',
            'origin' => $this->destination->origin,
            'destination' => $this->destination->destination,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'total' => 1000,
        ];

        $response = $this->postJson('/api/commissions', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('commissions', [
            'client_id' => $this->customer->id,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'total' => 1000,
        ]);

        $this->assertDatabaseCount('commission_items', 0);
    }

    public function test_can_create_commission_with_empty_items_array(): void
    {
        $this->actingAs($this->user);

        $data = [
            'client_id' => $this->customer->id,
            'date' => '2024-03-21',
            'origin' => $this->destination->origin,
            'destination' => $this->destination->destination,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'items' => [],
            'total' => 1000,
        ];

        $response = $this->postJson('/api/commissions', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('commissions', [
            'client_id' => $this->customer->id,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'total' => 1000,
        ]);

        $this->assertDatabaseCount('commission_items', 0);
    }

    public function test_can_update_commission(): void
    {
        $this->actingAs($this->user);

        // Crear una comisión inicial
        $commission = \App\Shared\Models\Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'total' => 1000,
        ]);

        // Crear algunos items iniciales
        \App\Shared\Models\CommissionItem::factory()->create([
            'commission_id' => $commission->id,
            'type' => \App\Shared\Enums\CommissionItemType::ORDINARIA,
            'size' => \App\Shared\Enums\CommissionItemSize::SMALL,
            'quantity' => 1,
            'unit_price' => 500,
            'subtotal' => 500,
        ]);

        $updateData = [
            'client_id' => $this->customer->id,
            'date' => '2024-03-22',
            'origin' => $this->destination->origin,
            'destination' => $this->destination->destination,
            'status' => \App\Shared\Enums\CommissionStatus::EN_PROCESO_ENTREGA->value,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'items' => [
                [
                    'type' => \App\Shared\Enums\CommissionItemType::ORDINARIA->value,
                    'size' => \App\Shared\Enums\CommissionItemSize::LARGE->value,
                    'quantity' => 2,
                    'unit_price' => 800,
                    'subtotal' => 1600,
                ],
                [
                    'type' => \App\Shared\Enums\CommissionItemType::EXTRAORDINARIA->value,
                    'quantity' => 1,
                    'unit_price' => 400,
                    'subtotal' => 400,
                    'detail' => 'Manejo especial actualizado',
                ],
            ],
            'total' => 2000,
        ];

        $response = $this->putJson("/api/commissions/{$commission->id}", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('commissions', [
            'id' => $commission->id,
            'client_id' => $this->customer->id,
            'status' => \App\Shared\Enums\CommissionStatus::EN_PROCESO_ENTREGA->value,
            'total' => 2000,
        ]);

        $this->assertDatabaseCount('commission_items', 2);
    }

    public function test_can_update_commission_remove_all_items(): void
    {
        $this->actingAs($this->user);

        // Crear una comisión inicial con items
        $commission = \App\Shared\Models\Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'total' => 1000,
        ]);

        // Crear algunos items iniciales
        \App\Shared\Models\CommissionItem::factory()->create([
            'commission_id' => $commission->id,
            'type' => \App\Shared\Enums\CommissionItemType::ORDINARIA,
            'size' => \App\Shared\Enums\CommissionItemSize::SMALL,
            'quantity' => 1,
            'unit_price' => 500,
            'subtotal' => 500,
        ]);

        $updateData = [
            'client_id' => $this->customer->id,
            'date' => '2024-03-22',
            'origin' => $this->destination->origin,
            'destination' => $this->destination->destination,
            'status' => \App\Shared\Enums\CommissionStatus::EN_PROCESO_ENTREGA->value,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'items' => [],
            'total' => 500,
        ];

        $response = $this->putJson("/api/commissions/{$commission->id}", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('commissions', [
            'id' => $commission->id,
            'total' => 500,
        ]);

        $this->assertDatabaseCount('commission_items', 0);
    }

    public function test_can_update_commission_without_items(): void
    {
        $this->actingAs($this->user);

        // Crear una comisión inicial
        $commission = \App\Shared\Models\Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'total' => 1000,
        ]);

        $updateData = [
            'client_id' => $this->customer->id,
            'date' => '2024-03-22',
            'origin' => $this->destination->origin,
            'destination' => $this->destination->destination,
            'status' => \App\Shared\Enums\CommissionStatus::EN_PROCESO_ENTREGA->value,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'total' => 1500,
        ];

        $response = $this->putJson("/api/commissions/{$commission->id}", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('commissions', [
            'id' => $commission->id,
            'total' => 1500,
        ]);

        $this->assertDatabaseCount('commission_items', 0);
    }

    public function test_cannot_update_nonexistent_commission(): void
    {
        $this->actingAs($this->user);

        $updateData = [
            'client_id' => $this->customer->id,
            'date' => '2024-03-22',
            'origin' => $this->destination->origin,
            'destination' => $this->destination->destination,
            'status' => \App\Shared\Enums\CommissionStatus::EN_PROCESO_ENTREGA->value,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'total' => 1500,
        ];

        $response = $this->putJson('/api/commissions/99999', $updateData);

        $response->assertStatus(404);
    }

    public function test_can_update_commission_with_partial_data(): void
    {
        $this->actingAs($this->user);

        // Crear una comisión inicial
        $commission = \App\Shared\Models\Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'total' => 1000,
        ]);

        // Payload similar al que envía el frontend
        $updateData = [
            'items' => [
                [
                    'id' => 3,
                    'commission_id' => $commission->id,
                    'type' => 'ORDINARIA',
                    'size' => 'GRANDE',
                    'quantity' => 15,
                    'unit_price' => 200000,
                    'subtotal' => 3000000,
                    'detail' => null,
                    'created_at' => '2025-08-29T14:15:08.000000Z',
                    'updated_at' => '2025-08-29T14:15:08.000000Z'
                ]
            ],
            'total' => 3000000
        ];

        $response = $this->putJson("/api/commissions/{$commission->id}", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('commissions', [
            'id' => $commission->id,
            'total' => 3000000,
        ]);

        $this->assertDatabaseCount('commission_items', 1);
    }

    public function test_validates_required_fields(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/commissions', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'client_id',
                'date',
                'origin',
                'destination',
                'status',
                'origin_location_id',
                'destination_location_id',
            ]);
    }

    public function test_can_create_commission_with_notes(): void
    {
        $this->actingAs($this->user);

        $data = [
            'client_id' => $this->customer->id,
            'date' => '2024-03-21',
            'origin' => $this->destination->origin,
            'destination' => $this->destination->destination,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'total' => 1000,
            'notes' => 'Notas especiales para esta comisión',
        ];

        $response = $this->postJson('/api/commissions', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('commissions', [
            'client_id' => $this->customer->id,
            'notes' => 'Notas especiales para esta comisión',
        ]);
    }

    public function test_can_update_commission_notes(): void
    {
        $this->actingAs($this->user);

        $commission = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'user_id' => $this->user->id,
            'branch_id' => $this->branch->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'notes' => 'Notas originales',
        ]);

        $updateData = [
            'notes' => 'Notas actualizadas',
        ];

        $response = $this->putJson("/api/commissions/{$commission->id}", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('commissions', [
            'id' => $commission->id,
            'notes' => 'Notas actualizadas',
        ]);
    }

    public function test_commission_response_includes_iva_fields(): void
    {
        $this->actingAs($this->user);

        // Crear comisión con IVA
        $commission = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'user_id' => $this->user->id,
            'branch_id' => $this->branch->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'payment_method' => \App\Shared\Enums\PaymentMethod::TRANSFERENCIA,
            'total' => 1210.00,
            'iva_amount' => 210.00,
            'iva_applied' => true,
        ]);

        $response = $this->getJson("/api/commissions/{$commission->id}");

        $response->assertStatus(200);
        
        // Debug: ver qué está devolviendo la respuesta
        $responseData = $response->json();
        $this->assertArrayHasKey('data', $responseData);
        
        $commissionData = $responseData['data'];
        $this->assertArrayHasKey('id', $commissionData);
        $this->assertArrayHasKey('total', $commissionData);
        $this->assertArrayHasKey('iva_amount', $commissionData);
        $this->assertArrayHasKey('iva_applied', $commissionData);
        $this->assertArrayHasKey('payment_method', $commissionData);
        $this->assertArrayHasKey('payment_method_label', $commissionData);
        
        $this->assertEquals($commission->id, $commissionData['id']);
        $this->assertEquals(1210.00, $commissionData['total']);
        $this->assertEquals(210.00, $commissionData['iva_amount']);
        $this->assertTrue($commissionData['iva_applied']);
        $this->assertEquals('TRANSFERENCIA', $commissionData['payment_method']);
        $this->assertEquals('Transferencia', $commissionData['payment_method_label']);
    }

    public function test_commission_list_includes_iva_fields(): void
    {
        $this->actingAs($this->user);

        // Crear comisiones con y sin IVA
        Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'user_id' => $this->user->id,
            'branch_id' => $this->branch->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'payment_method' => \App\Shared\Enums\PaymentMethod::TRANSFERENCIA,
            'total' => 1210.00,
            'iva_amount' => 210.00,
            'iva_applied' => true,
        ]);

        Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'user_id' => $this->user->id,
            'branch_id' => $this->branch->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'payment_method' => \App\Shared\Enums\PaymentMethod::EFECTIVO,
            'total' => 1000.00,
            'iva_amount' => 0.00,
            'iva_applied' => false,
        ]);

        $response = $this->getJson('/api/commissions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'client_id',
                        'branch_id',
                        'date',
                        'status',
                        'payment_method',
                        'payment_method_label',
                        'user_id',
                        'total',
                        'iva_amount',
                        'iva_applied',
                        'notes',
                        'created_at',
                        'updated_at',
                        'client',
                        'origin',
                        'destination',
                        'origin_location',
                        'destination_location',
                    ]
                ]
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        
        // Verificar que ambas comisiones incluyen los campos de IVA
        foreach ($data as $commission) {
            $this->assertArrayHasKey('iva_amount', $commission);
            $this->assertArrayHasKey('iva_applied', $commission);
            $this->assertArrayHasKey('payment_method', $commission);
            $this->assertArrayHasKey('payment_method_label', $commission);
        }
    }
}
