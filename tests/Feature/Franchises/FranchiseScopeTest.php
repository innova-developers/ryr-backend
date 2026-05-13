<?php

namespace Tests\Feature\Franchises;

use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Franchise;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FranchiseScopeTest extends TestCase
{
    use RefreshDatabase;

    private Franchise $franchiseA;
    private Franchise $franchiseB;
    private User $adminA;
    private User $adminB;
    private User $matrixAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::factory()->create();

        $this->franchiseA = Franchise::factory()->create(['name' => 'Franquicia A']);
        $this->franchiseB = Franchise::factory()->create(['name' => 'Franquicia B']);

        $this->adminA = User::factory()->create([
            'role' => 'admin_franquicia',
            'branch_id' => $branch->id,
            'franchise_id' => $this->franchiseA->id,
        ]);

        $this->adminB = User::factory()->create([
            'role' => 'admin_franquicia',
            'branch_id' => $branch->id,
            'franchise_id' => $this->franchiseB->id,
        ]);

        $this->matrixAdmin = User::factory()->create([
            'role' => 'administrador',
            'branch_id' => $branch->id,
        ]);
    }

    public function test_franchise_admin_without_franchise_id_gets_rejected()
    {
        $orphan = User::factory()->create([
            'role' => 'admin_franquicia',
            'franchise_id' => null,
        ]);

        $this->actingAs($orphan, 'sanctum');
        $response = $this->getJson('/api/franchises');
        $response->assertForbidden();
    }

    public function test_franchise_admin_a_cannot_see_franchise_b_details()
    {
        $this->actingAs($this->adminA, 'sanctum');

        $response = $this->getJson("/api/franchises/{$this->franchiseB->id}");
        $response->assertForbidden();
    }

    public function test_matrix_admin_can_see_all_franchises()
    {
        $this->actingAs($this->matrixAdmin, 'sanctum');

        $response = $this->getJson('/api/franchises');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(2, count($data));
    }

    public function test_franchise_admin_can_access_own_dashboard()
    {
        $this->actingAs($this->adminA, 'sanctum');

        $response = $this->getJson("/api/franchises/{$this->franchiseA->id}/dashboard");
        $response->assertOk();
        $response->assertJsonStructure([
            'kpis' => [
                'total_commissions',
                'total_commission_amount',
                'total_expenses',
                'total_incomes',
                'matrix_debt',
                'net_result',
            ],
        ]);
    }

    public function test_franchise_admin_cannot_access_other_franchise_dashboard()
    {
        $this->actingAs($this->adminA, 'sanctum');

        $response = $this->getJson("/api/franchises/{$this->franchiseB->id}/dashboard");
        $response->assertForbidden();
    }

    public function test_matrix_admin_can_access_any_franchise_dashboard()
    {
        $this->actingAs($this->matrixAdmin, 'sanctum');

        $response = $this->getJson("/api/franchises/{$this->franchiseA->id}/dashboard");
        $response->assertOk();

        $response = $this->getJson("/api/franchises/{$this->franchiseB->id}/dashboard");
        $response->assertOk();
    }

    public function test_settlement_requires_date_range()
    {
        $this->actingAs($this->matrixAdmin, 'sanctum');

        $response = $this->getJson("/api/franchises/{$this->franchiseA->id}/settlement");
        $response->assertStatus(422);
    }

    public function test_settlement_returns_correct_structure()
    {
        $this->actingAs($this->matrixAdmin, 'sanctum');

        $response = $this->getJson("/api/franchises/{$this->franchiseA->id}/settlement?date_from=2026-01-01&date_to=2026-12-31");
        $response->assertOk();
        $response->assertJsonStructure([
            'franchise' => ['id', 'name', 'commission_percentage'],
            'period' => ['from', 'to'],
            'gross_income',
            'total_commissions',
            'expenses',
            'matrix_amount',
            'net_for_franchise',
        ]);
    }

    public function test_franchise_admin_cannot_access_other_settlement()
    {
        $this->actingAs($this->adminA, 'sanctum');

        $response = $this->getJson("/api/franchises/{$this->franchiseB->id}/settlement?date_from=2026-01-01&date_to=2026-12-31");
        $response->assertForbidden();
    }

    public function test_dashboard_stats_are_scoped_to_franchise()
    {
        $destination = Destination::factory()->create();
        $customerA = Customer::factory()->create(['franchise_id' => $this->franchiseA->id]);

        Commission::factory()->count(3)->create([
            'franchise_id' => $this->franchiseA->id,
            'client_id' => $customerA->id,
            'destination_id' => $destination->id,
            'status' => 'PAGO_CONFIRMADO',
            'total' => 1000,
            'date' => now()->subDays(5)->toDateString(),
        ]);

        Commission::factory()->count(2)->create([
            'franchise_id' => $this->franchiseB->id,
            'client_id' => $customerA->id,
            'destination_id' => $destination->id,
            'status' => 'PAGO_CONFIRMADO',
            'total' => 500,
            'date' => now()->subDays(5)->toDateString(),
        ]);

        $this->actingAs($this->matrixAdmin, 'sanctum');

        $responseA = $this->getJson("/api/franchises/{$this->franchiseA->id}/dashboard");
        $responseA->assertOk();
        $this->assertEquals(3, $responseA->json('kpis.total_commissions'));

        $responseB = $this->getJson("/api/franchises/{$this->franchiseB->id}/dashboard");
        $responseB->assertOk();
        $this->assertEquals(2, $responseB->json('kpis.total_commissions'));
    }
}
