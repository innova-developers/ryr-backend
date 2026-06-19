<?php

namespace Tests\Feature;

use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCommissionTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private Destination $destination;
    private Location $originLocation;
    private Location $destinationLocation;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear cliente
        $this->customer = Customer::factory()->create([
            'name' => 'Juan',
            'last_name' => 'Pérez',
            'email' => 'juan@test.com',
            'mobile' => '2915662430',
        ]);

        // Crear ubicaciones
        $this->originLocation = Location::factory()->create([
            'name' => 'Escaleras',
        ]);

        $this->destinationLocation = Location::factory()->create([
            'name' => 'Torres',
        ]);

        // Crear destino
        $this->destination = Destination::factory()->create([
            'origin' => 'Escaleras',
            'destination' => 'Torres',
            'fixed_price' => 1000.00,
        ]);
    }

    public function test_can_create_commission_publicly()
    {
        $payload = [
            'client_id' => $this->customer->id,
            'user_id' => null, // Opcional
            'date' => '2025-07-31',
            'origin' => 'Escaleras',
            'destination' => 'Torres',
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
            'a_cuenta' => false,
            'items' => [
                [
                    'type' => 'ORDINARIA',
                    'size' => 'GRANDE',
                    'quantity' => 1,
                    'unit_price' => 2.00,
                    'subtotal' => 2.00,
                ],
            ],
            'total' => 2.00,
        ];

        $response = $this->postJson('/api/commissions/public', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Comisión creada correctamente',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'commission' => [
                    'id',
                    'tracking_number',
                    'status',
                    'total',
                    'date',
                    'origin',
                    'destination',
                    'items_count',
                ],
            ]);

        // Verificar que la comisión se creó en la base de datos
        $this->assertDatabaseHas('commissions', [
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
            'total' => 2.00,
            // user_id puede ser null o el ID del usuario encontrado por email
        ]);

        // Verificar que el item se creó
        $commission = Commission::where('client_id', $this->customer->id)->first();
        $this->assertDatabaseHas('commission_items', [
            'commission_id' => $commission->id,
            'type' => 'ORDINARIA',
            'size' => 'GRANDE',
            'quantity' => 1,
            'unit_price' => 2.00,
            'subtotal' => 2.00,
        ]);
    }

    public function test_validates_required_fields()
    {
        $response = $this->postJson('/api/commissions/public', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Datos inválidos',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
            ]);
    }

    public function test_validates_client_exists()
    {
        $payload = [
            'client_id' => 999, // Cliente que no existe
            'date' => '2025-07-31',
            'origin' => 'Escaleras',
            'destination' => 'Torres',
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
            'a_cuenta' => false,
            'items' => [
                [
                    'type' => 'ORDINARIA',
                    'size' => 'GRANDE',
                    'quantity' => 1,
                    'unit_price' => 2.00,
                    'subtotal' => 2.00,
                ],
            ],
            'total' => 2.00,
        ];

        $response = $this->postJson('/api/commissions/public', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Datos inválidos',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'client_id',
                ],
            ]);
    }

    public function test_validates_destination_exists()
    {
        $payload = [
            'client_id' => $this->customer->id,
            'date' => '2025-07-31',
            'origin' => 'OrigenInexistente',
            'destination' => 'DestinoInexistente',
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
            'a_cuenta' => false,
            'items' => [
                [
                    'type' => 'ORDINARIA',
                    'size' => 'GRANDE',
                    'quantity' => 1,
                    'unit_price' => 2.00,
                    'subtotal' => 2.00,
                ],
            ],
            'total' => 2.00,
        ];

        $response = $this->postJson('/api/commissions/public', $payload);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Ruta de origen a destino no encontrada',
            ]);
    }

    public function test_validates_locations_exist()
    {
        $payload = [
            'client_id' => $this->customer->id,
            'date' => '2025-07-31',
            'origin' => 'Escaleras',
            'destination' => 'Torres',
            'origin_location_id' => 999, // Ubicación que no existe
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
            'a_cuenta' => false,
            'items' => [
                [
                    'type' => 'ORDINARIA',
                    'size' => 'GRANDE',
                    'quantity' => 1,
                    'unit_price' => 2.00,
                    'subtotal' => 2.00,
                ],
            ],
            'total' => 2.00,
        ];

        $response = $this->postJson('/api/commissions/public', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Datos inválidos',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'origin_location_id',
                ],
            ]);
    }

    public function test_creates_commission_with_multiple_items()
    {
        $payload = [
            'client_id' => $this->customer->id,
            'date' => '2025-07-31',
            'origin' => 'Escaleras',
            'destination' => 'Torres',
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
            'a_cuenta' => false,
            'items' => [
                [
                    'type' => 'ORDINARIA',
                    'size' => 'GRANDE',
                    'quantity' => 1,
                    'unit_price' => 2.00,
                    'subtotal' => 2.00,
                ],
                [
                    'type' => 'EXTRAORDINARIA',
                    'size' => 'CHICO',
                    'quantity' => 2,
                    'unit_price' => 1.50,
                    'subtotal' => 3.00,
                ],
            ],
            'total' => 5.00,
        ];

        $response = $this->postJson('/api/commissions/public', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'commission' => [
                    // items_count = suma de cantidades de los ítems (1 + 2 = 3), no nº de filas.
                    'items_count' => 3,
                ],
            ]);

        // Verificar que se crearon ambos items
        $commission = Commission::where('client_id', $this->customer->id)->first();
        $this->assertEquals(2, $commission->items()->count());
    }

    public function test_can_create_commission_with_user_id()
    {
        // Crear un usuario para la prueba
        $user = \App\Shared\Models\User::factory()->create([
            'name' => 'Test User',
            'email' => 'testuser@test.com',
        ]);

        $payload = [
            'client_id' => $this->customer->id,
            'user_id' => $user->id,
            'date' => '2025-07-31',
            'origin' => 'Escaleras',
            'destination' => 'Torres',
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
            'a_cuenta' => false,
            'items' => [
                [
                    'type' => 'ORDINARIA',
                    'size' => 'GRANDE',
                    'quantity' => 1,
                    'unit_price' => 2.00,
                    'subtotal' => 2.00,
                ],
            ],
            'total' => 2.00,
        ];

        $response = $this->postJson('/api/commissions/public', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Comisión creada correctamente',
            ]);

        // Verificar que la comisión se creó con el user_id correcto
        $this->assertDatabaseHas('commissions', [
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
            'total' => 2.00,
            'user_id' => $user->id, // Debe usar el user_id proporcionado (tiene prioridad)
        ]);
    }

    public function test_validates_user_id_exists()
    {
        $payload = [
            'client_id' => $this->customer->id,
            'user_id' => 999, // Usuario que no existe
            'date' => '2025-07-31',
            'origin' => 'Escaleras',
            'destination' => 'Torres',
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
            'a_cuenta' => false,
            'items' => [
                [
                    'type' => 'ORDINARIA',
                    'size' => 'GRANDE',
                    'quantity' => 1,
                    'unit_price' => 2.00,
                    'subtotal' => 2.00,
                ],
            ],
            'total' => 2.00,
        ];

        $response = $this->postJson('/api/commissions/public', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Datos inválidos',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'user_id',
                ],
            ]);
    }

    public function test_automatically_finds_user_by_customer_email()
    {
        // Crear un usuario con el mismo email que el cliente
        $user = \App\Shared\Models\User::factory()->create([
            'name' => 'Auto User',
            'email' => $this->customer->email,
            'role' => 'cliente',
        ]);

        $payload = [
            'client_id' => $this->customer->id,
            // No enviar user_id para que busque automáticamente
            'date' => '2025-07-31',
            'origin' => 'Escaleras',
            'destination' => 'Torres',
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
            'a_cuenta' => false,
            'items' => [
                [
                    'type' => 'ORDINARIA',
                    'size' => 'GRANDE',
                    'quantity' => 1,
                    'unit_price' => 2.00,
                    'subtotal' => 2.00,
                ],
            ],
            'total' => 2.00,
        ];

        $response = $this->postJson('/api/commissions/public', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Comisión creada correctamente',
            ]);

        // Verificar que la comisión se creó con el user_id encontrado automáticamente
        $this->assertDatabaseHas('commissions', [
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
            'total' => 2.00,
            'user_id' => $user->id, // Debe usar el usuario encontrado por email
        ]);
    }
}
