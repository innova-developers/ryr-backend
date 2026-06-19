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

class CadeteHomeTest extends TestCase
{
    use RefreshDatabase;

    private User $cadete;
    private Transport $transport;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear sucursal
        $this->branch = Branch::factory()->create();

        // Crear cadete
        $this->cadete = User::factory()->create([
            'role' => UserRole::CADETE,
            'branch_id' => $this->branch->id,
        ]);

        // Crear transporte asignado al cadete
        $this->transport = Transport::factory()->create([
            'cadete_id' => $this->cadete->id,
        ]);
    }

    public function test_cadete_can_access_home_dashboard()
    {
        $response = $this->actingAs($this->cadete)
                         ->getJson('/api/cadete/home');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'summary',
                        'earnings',
                        'performance',
                        'stats',
                        'quick_actions',
                        'notifications',
                    ],
                ]);
    }

    public function test_home_dashboard_with_no_commissions()
    {
        $response = $this->actingAs($this->cadete)
                         ->getJson('/api/cadete/home');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'No tienes comisiones asignadas para este día',
                    'data' => [
                        'summary' => [
                            'total_deliveries' => 0,
                            'completed_deliveries' => 0,
                            'pending_deliveries' => 0,
                            'cancelled_deliveries' => 0,
                        ],
                        'earnings' => [
                            'today_total' => 0,
                            'today_cash' => 0,
                            'today_card' => 0,
                            'currency' => 'ARS',
                            'formatted_total' => '$0',
                        ],
                    ],
                ]);
    }

    public function test_home_dashboard_with_commissions_today()
    {
        // Crear cliente
        $client = Customer::factory()->create();

        // Crear ubicaciones
        $originLocation = Location::factory()->create();
        $destinationLocation = Location::factory()->create();

        // Crear destino
        $destination = Destination::factory()->create();

        // Crear comisiones para hoy
        $today = now()->format('Y-m-d');

        // Comisión completada
        Commission::factory()->create([
            'client_id' => $client->id,
            'transport_id' => $this->transport->id,
            'branch_id' => $this->branch->id,
            'destination_id' => $destination->id,
            'origin_location_id' => $originLocation->id,
            'destination_location_id' => $destinationLocation->id,
            'date' => $today,
            'status' => CommissionStatus::ENTREGADO,
            'total' => 25000,
            'cadete_id' => $this->cadete->id,
        ]);

        // Comisión pendiente
        Commission::factory()->create([
            'client_id' => $client->id,
            'transport_id' => $this->transport->id,
            'branch_id' => $this->branch->id,
            'destination_id' => $destination->id,
            'origin_location_id' => $originLocation->id,
            'destination_location_id' => $destinationLocation->id,
            'date' => $today,
            'status' => CommissionStatus::EN_TRANSITO_DESTINO,
            'total' => 30000,
            'cadete_id' => $this->cadete->id,
        ]);

        // Comisión cancelada
        Commission::factory()->create([
            'client_id' => $client->id,
            'transport_id' => $this->transport->id,
            'branch_id' => $this->branch->id,
            'destination_id' => $destination->id,
            'origin_location_id' => $originLocation->id,
            'destination_location_id' => $destinationLocation->id,
            'date' => $today,
            'status' => CommissionStatus::CANCELADO,
            'total' => 15000,
            'cadete_id' => $this->cadete->id,
        ]);

        $response = $this->actingAs($this->cadete)
                         ->getJson('/api/cadete/home');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'summary' => [
                            'total_deliveries' => 3,
                            'completed_deliveries' => 1,
                            'pending_deliveries' => 1,
                            'cancelled_deliveries' => 1,
                        ],
                        // Los montos de earnings dependen del % de comisión del cadete
                        // (ganancia real, no el total bruto), no determinístico con el factory.
                        'earnings' => [
                            'currency' => 'ARS',
                        ],
                        'performance' => [
                            'delivery_success_rate' => 33.3,
                        ],
                    ],
                ])
                ->assertJsonStructure([
                    'data' => ['earnings' => ['today_total', 'today_cash', 'today_card', 'formatted_total']],
                ]);
    }

    public function test_home_dashboard_with_specific_date()
    {
        // Crear cliente
        $client = Customer::factory()->create();

        // Crear ubicaciones
        $originLocation = Location::factory()->create();
        $destinationLocation = Location::factory()->create();

        // Crear destino
        $destination = Destination::factory()->create();

        // Crear comisión para fecha específica
        $specificDate = now()->format('Y-m-d');

        Commission::factory()->create([
            'client_id' => $client->id,
            'transport_id' => $this->transport->id,
            'branch_id' => $this->branch->id,
            'destination_id' => $destination->id,
            'origin_location_id' => $originLocation->id,
            'destination_location_id' => $destinationLocation->id,
            'date' => $specificDate,
            'status' => CommissionStatus::ENTREGADO,
            'total' => 50000,
            'cadete_id' => $this->cadete->id,
        ]);

        $response = $this->actingAs($this->cadete)
                         ->getJson('/api/cadete/home?date=' . $specificDate);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'summary' => [
                            'total_deliveries' => 1,
                            'completed_deliveries' => 1,
                            'pending_deliveries' => 0,
                            'cancelled_deliveries' => 0,
                        ],
                        // today_total depende del % de comisión del cadete (no determinístico).
                        'earnings' => [
                            'currency' => 'ARS',
                        ],
                    ],
                ])
                ->assertJsonStructure([
                    'data' => ['earnings' => ['today_total', 'formatted_total']],
                ]);
    }

    public function test_home_dashboard_without_transport()
    {
        // Crear cadete sin comisiones asignadas
        $cadeteWithoutCommissions = User::factory()->create([
            'role' => UserRole::CADETE,
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($cadeteWithoutCommissions)
                         ->getJson('/api/cadete/home');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'No tienes comisiones asignadas para este día',
                    'data' => [
                        'summary' => [
                            'total_deliveries' => 0,
                            'completed_deliveries' => 0,
                            'pending_deliveries' => 0,
                            'cancelled_deliveries' => 0,
                        ],
                        'earnings' => [
                            'today_total' => 0,
                            'today_cash' => 0,
                            'today_card' => 0,
                            'currency' => 'ARS',
                            'formatted_total' => '$0',
                        ],
                    ],
                ]);
    }

    public function test_home_dashboard_requires_authentication()
    {
        $response = $this->getJson('/api/cadete/home');
        $response->assertStatus(401);
    }

    public function test_home_dashboard_requires_cadete_role()
    {
        $admin = User::factory()->create(['role' => UserRole::ADMINISTRADOR]);

        $response = $this->actingAs($admin)
                         ->getJson('/api/cadete/home');

        $response->assertStatus(403);
    }
}
