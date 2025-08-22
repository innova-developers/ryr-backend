<?php

namespace Tests\Feature\Cadete;

use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use App\Shared\Models\ShipmentLocation;
use App\Shared\Models\Transport;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CadeteEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $cadete;
    private User $cadeteExterno;
    private User $admin;
    private Transport $transport;
    private Commission $commission;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear datos de prueba
        $this->branch = Branch::factory()->create();
        
        $this->cadete = User::factory()->create([
            'role' => UserRole::CADETE,
            'branch_id' => $this->branch->id,
        ]);

        $this->cadeteExterno = User::factory()->create([
            'role' => UserRole::CADETE_EXTERNO,
            'branch_id' => $this->branch->id,
        ]);

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMINISTRADOR,
            'branch_id' => $this->branch->id,
        ]);

        // Crear transporte asignado al cadete
        $this->transport = Transport::factory()->create([
            'cadete_id' => $this->cadete->id,
        ]);

        // Crear comisión asignada al transporte
        $customer = Customer::factory()->create();
        $destination = Destination::factory()->create();
        $originLocation = Location::factory()->create();
        $destinationLocation = Location::factory()->create();

        $this->commission = Commission::factory()->create([
            'client_id' => $customer->id,
            'destination_id' => $destination->id,
            'branch_id' => $this->branch->id,
            'transport_id' => $this->transport->id,
            'cadete_id' => $this->cadete->id,
            'origin_location_id' => $originLocation->id,
            'destination_location_id' => $destinationLocation->id,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA,
        ]);
    }

    public function test_cadete_can_access_profile()
    {
        Sanctum::actingAs($this->cadete);

        $response = $this->getJson('/api/cadete/profile');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'cadete' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'branch_id',
                        'transport' => [
                            'id',
                            'plate',
                            'description',
                            'phone',
                            'insurance',
                            'usage',
                        ]
                    ]
                ])
                ->assertJsonPath('cadete.id', $this->cadete->id)
                ->assertJsonPath('cadete.role', UserRole::CADETE->value)
                ->assertJsonPath('cadete.transport.id', $this->transport->id);
    }

    public function test_cadete_externo_can_access_profile()
    {
        Sanctum::actingAs($this->cadeteExterno);

        $response = $this->getJson('/api/cadete/profile');

        $response->assertStatus(200)
                ->assertJsonPath('cadete.role', UserRole::CADETE_EXTERNO->value);
    }

    public function test_admin_cannot_access_cadete_endpoints()
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/cadete/profile');

        $response->assertStatus(403)
                ->assertJson([
                    'message' => 'Acceso denegado. Solo cadetes y cadetes externos pueden acceder a esta funcionalidad.',
                    'user_role' => UserRole::ADMINISTRADOR->value
                ]);
    }

    public function test_unauthenticated_user_cannot_access_cadete_endpoints()
    {
        $response = $this->getJson('/api/cadete/profile');

        $response->assertStatus(401);
    }

    public function test_cadete_can_get_shipments()
    {
        Sanctum::actingAs($this->cadete);

        $response = $this->getJson('/api/cadete/shipments');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'shipments' => [
                        '*' => [
                            'id',
                            'tracking_number',
                            'status',
                            'status_label',
                            'date',
                            'total',
                            'client',
                            'origin',
                            'destination',
                            'items',
                            'transport'
                        ]
                    ],
                    'total',
                    'filters'
                ])
                ->assertJsonPath('shipments.0.id', $this->commission->id)
                ->assertJsonPath('shipments.0.status', CommissionStatus::SOLICITUD_RECIBIDA->value);
    }

    public function test_cadete_can_filter_shipments_by_status()
    {
        Sanctum::actingAs($this->cadete);

        $response = $this->getJson('/api/cadete/shipments?status=SOLICITUD_RECIBIDA');

        $response->assertStatus(200)
                ->assertJsonPath('filters.status', 'SOLICITUD_RECIBIDA')
                ->assertJsonCount(1, 'shipments');
    }

    public function test_cadete_can_filter_shipments_by_date()
    {
        Sanctum::actingAs($this->cadete);

        $date = $this->commission->date->format('Y-m-d');
        $response = $this->getJson("/api/cadete/shipments?date={$date}");

        $response->assertStatus(200)
                ->assertJsonPath('filters.date', $date)
                ->assertJsonCount(1, 'shipments');
    }

    public function test_cadete_without_transport_gets_empty_shipments()
    {
        $cadeteWithoutTransport = User::factory()->create([
            'role' => UserRole::CADETE,
            'branch_id' => $this->branch->id,
        ]);

        Sanctum::actingAs($cadeteWithoutTransport);

        $response = $this->getJson('/api/cadete/shipments');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'No tienes transportes asignados',
                    'shipments' => []
                ]);
    }

    public function test_cadete_can_update_shipment_status()
    {
        Sanctum::actingAs($this->cadete);

        $response = $this->putJson("/api/cadete/shipments/{$this->commission->id}", [
            'status' => 'EN_TRANSITO_DESTINO',
            'observation' => 'Recogido del origen',
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'shipment' => [
                        'id',
                        'status',
                        'status_label',
                        'updated_at'
                    ]
                ])
                ->assertJsonPath('shipment.status', 'EN_TRANSITO_DESTINO');

        // Verificar que se actualizó en la base de datos
        $this->assertDatabaseHas('commissions', [
            'id' => $this->commission->id,
            'status' => CommissionStatus::EN_TRANSITO_DESTINO->value,
        ]);
    }

    public function test_cadete_cannot_update_shipment_not_assigned()
    {
        // Crear otro cadete con otro transporte
        $otherCadete = User::factory()->create(['role' => UserRole::CADETE]);
        $otherTransport = Transport::factory()->create(['cadete_id' => $otherCadete->id]);
        
        // Crear comisión asignada al otro transporte
        $otherCommission = Commission::factory()->create([
            'transport_id' => $otherTransport->id,
            'branch_id' => $this->branch->id,
        ]);

        Sanctum::actingAs($this->cadete);

        $response = $this->putJson("/api/cadete/shipments/{$otherCommission->id}", [
            'status' => 'EN_TRANSITO_DESTINO',
        ]);

        $response->assertStatus(404)
                ->assertJson([
                    'message' => 'Envío no encontrado o no tienes permisos para modificarlo'
                ]);
    }

    public function test_cadete_can_send_location()
    {
        Sanctum::actingAs($this->cadete);

        $locationData = [
            'latitude' => -34.6037,
            'longitude' => -58.3816,
            'address' => 'Buenos Aires, Argentina',
            'observation' => 'Llegando al destino',
        ];

        $response = $this->postJson("/api/cadete/shipments/{$this->commission->id}/location", $locationData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'location' => [
                        'id',
                        'latitude',
                        'longitude',
                        'address',
                        'observation',
                        'recorded_at'
                    ]
                ])
                ->assertJsonPath('location.latitude', function($value) use ($locationData) {
                    return abs($value - $locationData['latitude']) < 0.0001;
                })
                ->assertJsonPath('location.longitude', function($value) use ($locationData) {
                    return abs($value - $locationData['longitude']) < 0.0001;
                });

        // Verificar que se guardó en la base de datos
        $this->assertDatabaseHas('shipment_locations', [
            'commission_id' => $this->commission->id,
            'cadete_id' => $this->cadete->id,
            'latitude' => $locationData['latitude'],
            'longitude' => $locationData['longitude'],
        ]);
    }

    public function test_cadete_cannot_send_location_for_unassigned_shipment()
    {
        $otherCadete = User::factory()->create(['role' => UserRole::CADETE]);
        $otherTransport = Transport::factory()->create(['cadete_id' => $otherCadete->id]);
        $otherCommission = Commission::factory()->create([
            'transport_id' => $otherTransport->id,
            'branch_id' => $this->branch->id,
        ]);

        Sanctum::actingAs($this->cadete);

        $response = $this->postJson("/api/cadete/shipments/{$otherCommission->id}/location", [
            'latitude' => -34.6037,
            'longitude' => -58.3816,
        ]);

        $response->assertStatus(404)
                ->assertJson([
                    'message' => 'Envío no encontrado o no tienes permisos para enviar ubicación'
                ]);
    }

    public function test_cadete_can_get_stats()
    {
        Sanctum::actingAs($this->cadete);

        $response = $this->getJson('/api/cadete/stats');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'stats' => [
                        'period' => [
                            'start_date',
                            'end_date'
                        ],
                        'totals' => [
                            'shipments',
                            'revenue',
                            'delivered',
                            'delivery_rate'
                        ],
                        'by_status' => [
                            'pendiente',
                            'en_transito',
                            'entregado',
                            'cancelado'
                        ],
                        'daily_shipments',
                        'transports'
                    ]
                ]);
    }

    public function test_cadete_stats_with_custom_date_range()
    {
        Sanctum::actingAs($this->cadete);

        $startDate = now()->subDays(7)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->getJson("/api/cadete/stats?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200)
                ->assertJsonPath('stats.period.start_date', $startDate)
                ->assertJsonPath('stats.period.end_date', $endDate);
    }

    public function test_location_validation_requires_valid_coordinates()
    {
        Sanctum::actingAs($this->cadete);

        // Test invalid latitude
        $response = $this->postJson("/api/cadete/shipments/{$this->commission->id}/location", [
            'latitude' => 91, // Invalid: greater than 90
            'longitude' => -58.3816,
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['latitude']);

        // Test invalid longitude
        $response = $this->postJson("/api/cadete/shipments/{$this->commission->id}/location", [
            'latitude' => -34.6037,
            'longitude' => 181, // Invalid: greater than 180
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['longitude']);
    }

    public function test_status_update_validation()
    {
        Sanctum::actingAs($this->cadete);

        // Test invalid status
        $response = $this->putJson("/api/cadete/shipments/{$this->commission->id}", [
            'status' => 'invalid_status',
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['status']);

        // Test missing status
        $response = $this->putJson("/api/cadete/shipments/{$this->commission->id}", []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['status']);
    }
}