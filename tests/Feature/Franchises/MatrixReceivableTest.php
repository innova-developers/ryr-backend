<?php

namespace Tests\Feature\Franchises;

use App\Services\MatrixCommissionService;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Franchise;
use App\Shared\Models\MatrixReceivable;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatrixReceivableTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $franchiseAdmin;
    private Franchise $franchise;
    private MatrixCommissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $branch = Branch::factory()->create();

        $this->franchise = Franchise::factory()->create([
            'commission_percentage_to_matrix' => 15.00,
        ]);

        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'branch_id' => $branch->id,
        ]);

        $this->franchiseAdmin = User::factory()->create([
            'role' => 'admin_franquicia',
            'branch_id' => $branch->id,
            'franchise_id' => $this->franchise->id,
        ]);

        $this->service = new MatrixCommissionService();
    }

    public function test_creates_receivable_for_franchise_commission()
    {
        $destination = Destination::factory()->create();
        $customer = Customer::factory()->create();

        $commission = Commission::factory()->create([
            'franchise_id' => $this->franchise->id,
            'client_id' => $customer->id,
            'destination_id' => $destination->id,
            'total' => 1000.00,
            'status' => 'PAGO_CONFIRMADO',
        ]);

        $receivable = $this->service->createReceivableForCommission($commission);

        $this->assertNotNull($receivable);
        $this->assertEquals($this->franchise->id, $receivable->franchise_id);
        $this->assertEquals($commission->id, $receivable->commission_id);
        $this->assertEquals(150.00, $receivable->amount); // 15% of 1000
        $this->assertEquals(15.00, $receivable->percentage_applied);
        $this->assertEquals(1000.00, $receivable->commission_total);
        $this->assertEquals('pending', $receivable->status);
    }

    public function test_does_not_create_receivable_without_franchise()
    {
        $destination = Destination::factory()->create();
        $customer = Customer::factory()->create();

        $commission = Commission::factory()->create([
            'franchise_id' => null,
            'client_id' => $customer->id,
            'destination_id' => $destination->id,
            'total' => 1000.00,
        ]);

        $receivable = $this->service->createReceivableForCommission($commission);
        $this->assertNull($receivable);
    }

    public function test_does_not_duplicate_receivable()
    {
        $destination = Destination::factory()->create();
        $customer = Customer::factory()->create();

        $commission = Commission::factory()->create([
            'franchise_id' => $this->franchise->id,
            'client_id' => $customer->id,
            'destination_id' => $destination->id,
            'total' => 1000.00,
        ]);

        $first = $this->service->createReceivableForCommission($commission);
        $second = $this->service->createReceivableForCommission($commission);

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, MatrixReceivable::where('commission_id', $commission->id)->count());
    }

    public function test_mark_as_paid()
    {
        $receivable = MatrixReceivable::factory()->create([
            'franchise_id' => $this->franchise->id,
            'status' => 'pending',
        ]);

        $result = $this->service->markAsPaid($receivable->id, 'REF-123');

        $this->assertEquals('paid', $result->status);
        $this->assertNotNull($result->paid_at);
        $this->assertEquals('REF-123', $result->payment_reference);
    }

    public function test_mark_as_cancelled()
    {
        $receivable = MatrixReceivable::factory()->create([
            'franchise_id' => $this->franchise->id,
            'status' => 'pending',
        ]);

        $result = $this->service->markAsCancelled($receivable->id, 'Motivo de cancelación');

        $this->assertEquals('cancelled', $result->status);
        $this->assertEquals('Motivo de cancelación', $result->notes);
    }

    public function test_get_summary()
    {
        MatrixReceivable::factory()->count(3)->create([
            'franchise_id' => $this->franchise->id,
            'status' => 'pending',
            'amount' => 100.00,
        ]);

        MatrixReceivable::factory()->count(2)->create([
            'franchise_id' => $this->franchise->id,
            'status' => 'paid',
            'amount' => 200.00,
        ]);

        $summary = $this->service->getSummary($this->franchise->id);

        $this->assertEquals(300.00, $summary['pending_amount']);
        $this->assertEquals(400.00, $summary['paid_amount']);
        $this->assertEquals(3, $summary['pending_count']);
        $this->assertEquals(2, $summary['paid_count']);
    }

    public function test_api_list_receivables_as_matrix_admin()
    {
        MatrixReceivable::factory()->count(3)->create([
            'franchise_id' => $this->franchise->id,
        ]);

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->getJson('/api/admin/matrix-receivables');
        $response->assertOk();

        $data = $response->json();
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
    }

    public function test_api_franchise_admin_only_sees_own_receivables()
    {
        $otherFranchise = Franchise::factory()->create();

        MatrixReceivable::factory()->count(3)->create([
            'franchise_id' => $this->franchise->id,
        ]);
        MatrixReceivable::factory()->count(2)->create([
            'franchise_id' => $otherFranchise->id,
        ]);

        $this->actingAs($this->franchiseAdmin, 'sanctum');
        $response = $this->getJson('/api/admin/matrix-receivables');
        $response->assertOk();

        $this->assertEquals(3, $response->json('pagination.total'));
    }

    public function test_api_summary()
    {
        MatrixReceivable::factory()->create([
            'franchise_id' => $this->franchise->id,
            'status' => 'pending',
            'amount' => 500,
        ]);

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->getJson('/api/admin/matrix-receivables/summary');
        $response->assertOk();
        $response->assertJsonStructure([
            'pending_amount',
            'paid_amount',
            'cancelled_amount',
            'total_amount',
            'pending_count',
            'paid_count',
        ]);
    }

    public function test_api_mark_paid_requires_matrix_admin()
    {
        $receivable = MatrixReceivable::factory()->create([
            'franchise_id' => $this->franchise->id,
        ]);

        $this->actingAs($this->franchiseAdmin, 'sanctum');
        $response = $this->patchJson("/api/admin/matrix-receivables/{$receivable->id}/mark-paid");
        $response->assertForbidden();

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->patchJson("/api/admin/matrix-receivables/{$receivable->id}/mark-paid", [
            'payment_reference' => 'PAY-999',
        ]);
        $response->assertOk();
        $this->assertEquals('paid', $response->json('status'));
    }

    public function test_api_mark_cancelled_requires_matrix_admin()
    {
        $receivable = MatrixReceivable::factory()->create([
            'franchise_id' => $this->franchise->id,
        ]);

        $this->actingAs($this->franchiseAdmin, 'sanctum');
        $response = $this->patchJson("/api/admin/matrix-receivables/{$receivable->id}/mark-cancelled");
        $response->assertForbidden();

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->patchJson("/api/admin/matrix-receivables/{$receivable->id}/mark-cancelled", [
            'notes' => 'Anulada por error',
        ]);
        $response->assertOk();
        $this->assertEquals('cancelled', $response->json('status'));
    }

    public function test_api_filter_receivables_by_status()
    {
        MatrixReceivable::factory()->create([
            'franchise_id' => $this->franchise->id,
            'status' => 'pending',
        ]);
        MatrixReceivable::factory()->create([
            'franchise_id' => $this->franchise->id,
            'status' => 'paid',
        ]);

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->getJson('/api/admin/matrix-receivables?status=pending');
        $response->assertOk();
        $this->assertEquals(1, $response->json('pagination.total'));
    }

    public function test_receivable_calculation_with_different_percentages()
    {
        $franchise25 = Franchise::factory()->create(['commission_percentage_to_matrix' => 25.00]);
        $destination = Destination::factory()->create();
        $customer = Customer::factory()->create();

        $commission = Commission::factory()->create([
            'franchise_id' => $franchise25->id,
            'client_id' => $customer->id,
            'destination_id' => $destination->id,
            'total' => 2000.00,
        ]);

        $receivable = $this->service->createReceivableForCommission($commission);

        $this->assertEquals(500.00, $receivable->amount); // 25% of 2000
        $this->assertEquals(25.00, $receivable->percentage_applied);
    }

    public function test_zero_percentage_franchise_no_receivable()
    {
        $franchise0 = Franchise::factory()->create(['commission_percentage_to_matrix' => 0]);
        $destination = Destination::factory()->create();
        $customer = Customer::factory()->create();

        $commission = Commission::factory()->create([
            'franchise_id' => $franchise0->id,
            'client_id' => $customer->id,
            'destination_id' => $destination->id,
            'total' => 1000.00,
        ]);

        $receivable = $this->service->createReceivableForCommission($commission);
        $this->assertNull($receivable);
    }
}
