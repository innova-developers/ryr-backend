<?php

namespace Tests\Feature\Cadete;

use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use App\Shared\Models\Transport;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CadeteDeliveriesTest extends TestCase
{
    use RefreshDatabase;

    private User $cadete;
    private Transport $transport;
    private Branch $branch;
    private Customer $client;
    private Location $originLocation;
    private Location $destinationLocation;
    private Destination $destination;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Crear sucursal
        $this->branch = Branch::factory()->create([
            'name' => 'Sucursal Centro',
            'address' => 'Av. Principal 123',
            'phone' => '011-1234-5678',
        ]);
        
        // Crear cadete con porcentaje de comisión
        $this->cadete = User::factory()->create([
            'role' => UserRole::CADETE,
            'branch_id' => $this->branch->id,
            'commission_percentage' => 25.0, // 25% de comisión
        ]);
        
        // Crear transporte asignado al cadete
        $this->transport = Transport::factory()->create([
            'cadete_id' => $this->cadete->id,
            'plate' => 'ABC123',
            'description' => 'Moto Honda CG 150',
        ]);
        
        // Crear cliente
        $this->client = Customer::factory()->create([
            'name' => 'Juan Pérez',
            'last_name' => 'García',
            'phone' => '+1234567890',
            'address' => 'Av. Principal 123, Ciudad',
        ]);
        
        // Crear ubicaciones
        $this->originLocation = Location::factory()->create([
            'name' => 'Almacén Central',
            'address' => 'Zona Industrial',
            'phone' => '+1234567891',
        ]);
        
        $this->destinationLocation = Location::factory()->create([
            'name' => 'Oficina Cliente',
            'address' => 'Centro Comercial',
            'phone' => '+1234567892',
        ]);
        
        $this->destination = Destination::factory()->create();
    }

    public function test_cadete_can_access_deliveries()
    {
        $response = $this->actingAs($this->cadete)
                         ->getJson('/api/cadete/deliveries');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'deliveries',
                        'pagination',
                        'summary'
                    ]
                ]);
    }

    public function test_deliveries_returns_empty_when_no_commissions()
    {
        $cadeteWithoutCommissions = User::factory()->create([
            'role' => UserRole::CADETE,
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($cadeteWithoutCommissions)
                         ->getJson('/api/cadete/deliveries');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Entregas obtenidas correctamente',
                    'data' => [
                        'deliveries' => [],
                        'summary' => [
                            'total_deliveries' => 0,
                            'pending' => 0,
                            'in_progress' => 0,
                            'completed' => 0,
                            'cancelled' => 0,
                            'total_earnings' => 0
                        ]
                    ]
                ]);
    }

    public function test_deliveries_returns_commissions_with_correct_structure()
    {
        // Crear comisión
        $commission = Commission::factory()->create([
            'client_id' => $this->client->id,
            'transport_id' => $this->transport->id,
            'branch_id' => $this->branch->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::EN_TRANSITO_DESTINO,
            'total' => 25.50,
            'date' => now(),
            'cadete_id' => $this->cadete->id, // Asignar explícitamente el cadete_id
        ]);

        $response = $this->actingAs($this->cadete)
                         ->getJson('/api/cadete/deliveries');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'deliveries' => [
                            [
                                'id' => $commission->id,
                                'tracking_number' => $commission->id,
                                'customer_name' => 'Juan Pérez García',
                                'customer_address' => 'Av. Principal 123, Ciudad',
                                'customer_phone' => '+1234567890',
                                'pickup_address' => 'Almacén Central, Zona Industrial',
                                'pickup_phone' => '+1234567891',
                                'status' => 'En tránsito a destino',
                                'commission_amount' => '25.50',
                            ]
                        ]
                    ]
                ]);
    }

    public function test_deliveries_filter_by_status()
    {
        // Crear comisiones con diferentes estados
        Commission::factory()->create([
            'client_id' => $this->client->id,
            'transport_id' => $this->transport->id,
            'branch_id' => $this->branch->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::ENTREGADO,
            'cadete_id' => $this->cadete->id,
        ]);

        Commission::factory()->create([
            'client_id' => $this->client->id,
            'transport_id' => $this->transport->id,
            'branch_id' => $this->branch->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::EN_TRANSITO_DESTINO,
            'cadete_id' => $this->cadete->id,
        ]);

        $response = $this->actingAs($this->cadete)
                         ->getJson('/api/cadete/deliveries?status=EN_TRANSITO_DESTINO');

        $response->assertStatus(200);
        
        $deliveries = $response->json('data.deliveries');
        $this->assertCount(1, $deliveries);
        $this->assertEquals('En tránsito a destino', $deliveries[0]['status']);
    }

    public function test_deliveries_filter_by_date_range()
    {
        $today = now();
        $yesterday = now()->subDay();
        
        // Comisión de hoy
        Commission::factory()->create([
            'client_id' => $this->client->id,
            'transport_id' => $this->transport->id,
            'branch_id' => $this->branch->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'date' => $today,
            'cadete_id' => $this->cadete->id,
        ]);

        // Comisión de ayer
        Commission::factory()->create([
            'client_id' => $this->client->id,
            'transport_id' => $this->transport->id,
            'branch_id' => $this->branch->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'date' => $yesterday,
            'cadete_id' => $this->cadete->id,
        ]);

        $response = $this->actingAs($this->cadete)
                         ->getJson('/api/cadete/deliveries?date_from=' . $today->format('Y-m-d'));

        $response->assertStatus(200);
        
        $deliveries = $response->json('data.deliveries');
        $this->assertCount(1, $deliveries);
    }

    public function test_deliveries_search_functionality()
    {
        Commission::factory()->create([
            'client_id' => $this->client->id,
            'transport_id' => $this->transport->id,
            'branch_id' => $this->branch->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'cadete_id' => $this->cadete->id,
        ]);

        $response = $this->actingAs($this->cadete)
                         ->getJson('/api/cadete/deliveries?search=Juan');

        $response->assertStatus(200);
        
        $deliveries = $response->json('data.deliveries');
        $this->assertCount(1, $deliveries);
        $this->assertStringContainsString('Juan', $deliveries[0]['customer_name']);
    }

    public function test_deliveries_pagination()
    {
        // Crear 25 comisiones
        for ($i = 0; $i < 25; $i++) {
            Commission::factory()->create([
                'client_id' => $this->client->id,
                'transport_id' => $this->transport->id,
                'branch_id' => $this->branch->id,
                'destination_id' => $this->destination->id,
                'origin_location_id' => $this->originLocation->id,
                'destination_location_id' => $this->destinationLocation->id,
                'cadete_id' => $this->cadete->id,
            ]);
        }

        $response = $this->actingAs($this->cadete)
                         ->getJson('/api/cadete/deliveries?page=2&per_page=10');

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'pagination' => [
                            'current_page' => 2,
                            'per_page' => 10,
                            'total' => 25,
                            'last_page' => 3,
                        ]
                    ]
                ]);
    }

    public function test_deliveries_sorting()
    {
        // Crear comisiones con diferentes montos
        Commission::factory()->create([
            'client_id' => $this->client->id,
            'transport_id' => $this->transport->id,
            'branch_id' => $this->branch->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'total' => 100.00,
            'cadete_id' => $this->cadete->id,
        ]);

        Commission::factory()->create([
            'client_id' => $this->client->id,
            'transport_id' => $this->transport->id,
            'branch_id' => $this->branch->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'total' => 50.00,
            'cadete_id' => $this->cadete->id,
        ]);

        $response = $this->actingAs($this->cadete)
                         ->getJson('/api/cadete/deliveries?sort_by=commission_amount&sort_order=desc');

        $response->assertStatus(200);
        
        $deliveries = $response->json('data.deliveries');
        $this->assertCount(2, $deliveries);
        $this->assertEquals(100.00, $deliveries[0]['commission_amount']);
    }

    public function test_deliveries_summary_calculation()
    {
        // Crear comisiones con diferentes estados
        Commission::factory()->create([
            'client_id' => $this->client->id,
            'transport_id' => $this->transport->id,
            'branch_id' => $this->branch->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::ENTREGADO,
            'total' => 100.00,
            'cadete_id' => $this->cadete->id,
        ]);

        Commission::factory()->create([
            'client_id' => $this->client->id,
            'transport_id' => $this->transport->id,
            'branch_id' => $this->branch->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::EN_TRANSITO_DESTINO,
            'total' => 50.00,
            'cadete_id' => $this->cadete->id,
        ]);

        Commission::factory()->create([
            'client_id' => $this->client->id,
            'transport_id' => $this->transport->id,
            'branch_id' => $this->branch->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::CANCELADO,
            'total' => 25.00,
            'cadete_id' => $this->cadete->id,
        ]);

        $response = $this->actingAs($this->cadete)
                         ->getJson('/api/cadete/deliveries');

        $response->assertStatus(200);
        
        $data = $response->json('data.summary');
        $this->assertEquals(3, $data['total_deliveries']);
        $this->assertEquals(1, $data['completed']);
        $this->assertEquals(1, $data['pending']);
        $this->assertEquals(0, $data['cancelled']);
        $this->assertGreaterThan(0, $data['total_earnings']);
        $this->assertArrayHasKey('total_commission_amount', $data);
        $this->assertArrayHasKey('commission_percentage', $data);
        
        // Verificar que los valores calculados son consistentes
        $this->assertEquals(100.00, $data['total_commission_amount']); // 100 + 50 + 25 = 175, pero solo se cuenta el entregado
        $this->assertGreaterThan(0, $data['commission_percentage']);
    }

    public function test_deliveries_requires_authentication()
    {
        $response = $this->getJson('/api/cadete/deliveries');
        $response->assertStatus(401);
    }

    public function test_deliveries_requires_cadete_role()
    {
        $admin = User::factory()->create(['role' => UserRole::ADMINISTRADOR]);
        
        $response = $this->actingAs($admin)
                         ->getJson('/api/cadete/deliveries');
        
        $response->assertStatus(403);
    }

    public function test_deliveries_with_combined_filters()
    {
        // Crear comisiones con diferentes características
        Commission::factory()->create([
            'client_id' => $this->client->id,
            'transport_id' => $this->transport->id,
            'branch_id' => $this->branch->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::ENTREGADO,
            'date' => now(),
            'total' => 100.00,
            'cadete_id' => $this->cadete->id,
        ]);

        $response = $this->actingAs($this->cadete)
                         ->getJson('/api/cadete/deliveries?status=ENTREGADO&date_from=' . now()->format('Y-m-d') . '&page=1&per_page=10&search=Juan');

        $response->assertStatus(200);
        
        $deliveries = $response->json('data.deliveries');
        $this->assertCount(1, $deliveries);
        $this->assertEquals('Entregado', $deliveries[0]['status']);
    }
}
