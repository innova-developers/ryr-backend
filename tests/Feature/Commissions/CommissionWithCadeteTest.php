<?php

namespace Tests\Feature\Commissions;

use App\Shared\Models\Commission;
use App\Shared\Models\User;
use App\Shared\Models\Customer;
use App\Shared\Models\Branch;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommissionWithCadeteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $cadete;
    private Commission $commission;
    private Customer $customer;
    private Branch $branch;
    private Destination $destination;
    private Location $originLocation;
    private Location $destinationLocation;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear admin
        $this->admin = User::factory()->create([
            'role' => UserRole::ADMINISTRADOR,
        ]);

        // Crear cadete
        $this->cadete = User::factory()->create([
            'role' => UserRole::CADETE,
        ]);

        // Crear customer
        $this->customer = Customer::factory()->create();

        // Crear branch
        $this->branch = Branch::factory()->create();

        // Crear destination
        $this->destination = Destination::factory()->create();

        // Crear locations
        $this->originLocation = Location::factory()->create();
        $this->destinationLocation = Location::factory()->create();

        // Crear comisión
        $this->commission = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::PAGO_CONFIRMADO,
            'cadete_id' => $this->cadete->id,
            'total' => 5000.00,
        ]);

        Sanctum::actingAs($this->admin);
    }

    public function test_commission_list_includes_cadete_information(): void
    {
        $response = $this->getJson('/api/commissions');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'client_id',
                    'branch_id',
                    'date',
                    'status',
                    'user_id',
                    'total',
                    'created_at',
                    'updated_at',
                    'client',
                    'origin',
                    'destination',
                    'origin_location',
                    'destination_location',
                    'current_branch',
                    'cadete',
                    'items',
                ]
            ],
            'meta'
        ]);

        $commissionData = $response->json('data')[0];
        $this->assertNotNull($commissionData['cadete']);
        $this->assertEquals($this->cadete->id, $commissionData['cadete']['id']);
        $this->assertEquals($this->cadete->name, $commissionData['cadete']['name']);
        $this->assertEquals($this->cadete->email, $commissionData['cadete']['email']);
        $this->assertEquals($this->cadete->role->value, $commissionData['cadete']['role']);
    }

    public function test_commission_show_includes_cadete_information(): void
    {
        $response = $this->getJson("/api/commissions/{$this->commission->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'client_id',
                'client',
                'origin',
                'destination',
                'origin_location',
                'destination_location',
                'branch_id',
                'branch',
                'current_branch',
                'date',
                'status',
                'user_id',
                'user',
                'cadete',
                'total',
                'items',
                'logs',
                'created_at',
                'updated_at',
            ]
        ]);

        $commissionData = $response->json('data');
        $this->assertNotNull($commissionData['cadete']);
        $this->assertEquals($this->cadete->id, $commissionData['cadete']['id']);
        $this->assertEquals($this->cadete->name, $commissionData['cadete']['name']);
        $this->assertEquals($this->cadete->email, $commissionData['cadete']['email']);
        $this->assertEquals($this->cadete->role->value, $commissionData['cadete']['role']);
    }

    public function test_commission_without_cadete_returns_null_cadete(): void
    {
        // Crear comisión sin cadete
        $commissionWithoutCadete = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA,
            'cadete_id' => null,
            'total' => 3000.00,
        ]);

        $response = $this->getJson("/api/commissions/{$commissionWithoutCadete->id}");

        $response->assertStatus(200);
        $commissionData = $response->json('data');
        $this->assertNull($commissionData['cadete']);
    }

    public function test_commission_list_without_cadete_returns_null_cadete(): void
    {
        // Crear comisión sin cadete
        $commissionWithoutCadete = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA,
            'cadete_id' => null,
            'total' => 4000.00,
        ]);

        $response = $this->getJson('/api/commissions');

        $response->assertStatus(200);
        $commissions = $response->json('data');
        
        // Encontrar la comisión sin cadete
        $commissionData = collect($commissions)->firstWhere('id', $commissionWithoutCadete->id);
        $this->assertNotNull($commissionData);
        $this->assertNull($commissionData['cadete']);
    }
}
