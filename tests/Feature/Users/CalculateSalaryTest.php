<?php

namespace Tests\Feature\Users;

use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Expense;
use App\Shared\Models\ExpenseCategory;
use App\Shared\Models\Location;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculateSalaryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Customer $customer;
    private Destination $destination;
    private Branch $branch;
    private Location $originLocation;
    private Location $destinationLocation;
    private ExpenseCategory $expenseCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::factory()->create();
        $this->destination = Destination::factory()->create();
        $this->branch = Branch::factory()->create();
        $this->originLocation = Location::factory()->create();
        $this->destinationLocation = Location::factory()->create();
        $this->expenseCategory = ExpenseCategory::factory()->create();

        $this->user = User::factory()->create([
            'base_salary' => 1000.00,
            'commission_percentage' => 10.00,
            'contract_type' => 'fixed_salary',
            'branch_id' => $this->branch->id,
        ]);
    }

    /**
     * El cálculo de sueldo cuenta comisiones por cadete_id (no user_id) y sólo en
     * estado PAGO_VALIDACION/PAGO_CONFIRMADO. Helper para crear comisiones contables.
     */
    private function makeCommission(int $cadeteId, float $total, string $date): Commission
    {
        return Commission::factory()->create([
            'cadete_id' => $cadeteId,
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'branch_id' => $this->branch->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::PAGO_VALIDACION,
            'total' => $total,
            'date' => $date,
        ]);
    }

    public function test_can_calculate_salary_for_current_month(): void
    {
        $this->makeCommission($this->user->id, 5000.00, now()->format('Y-m-d'));
        $this->makeCommission($this->user->id, 3000.00, now()->format('Y-m-d'));

        Expense::factory()->create([
            'user_id' => $this->user->id,
            'expense_category_id' => $this->expenseCategory->id,
            'amount' => 500.00,
            'date' => now(),
        ]);
        Expense::factory()->create([
            'user_id' => $this->user->id,
            'expense_category_id' => $this->expenseCategory->id,
            'amount' => 300.00,
            'date' => now(),
        ]);

        $response = $this->getJson("/api/users/{$this->user->id}/salary");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user_id',
                'month',
                'contract_type',
                'base_salary',
                'commissions_total',
                'commission_amount',
                'subtotal_before_expenses',
                'expenses_amount',
                'subtotal_after_expenses',
                'available_for_advances',
            ]);

        $data = $response->json();

        // Contrato fixed_salary: sueldo base fijo, sin componente de comisión.
        $this->assertEquals(1000.00, $data['base_salary']);
        $this->assertEquals(8000.00, $data['commissions_total']); // 5000 + 3000
        $this->assertEquals(0.00, $data['commission_amount']); // fixed_salary no aplica %
        $this->assertEquals(1000.00, $data['subtotal_before_expenses']); // 1000 + 0
        $this->assertEquals(800.00, $data['expenses_amount']); // 500 + 300
        $this->assertEquals(200.00, $data['subtotal_after_expenses']); // 1000 - 800
        $this->assertEquals(-800.00, $data['available_for_advances']); // 0 - 800
    }

    public function test_can_calculate_salary_for_specific_month(): void
    {
        $specificMonth = '2024-01';

        $this->makeCommission($this->user->id, 4000.00, '2024-01-15');

        Expense::factory()->create([
            'user_id' => $this->user->id,
            'expense_category_id' => $this->expenseCategory->id,
            'amount' => 200.00,
            'date' => '2024-01-25',
        ]);

        $response = $this->getJson("/api/users/{$this->user->id}/salary?month={$specificMonth}");

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertEquals($specificMonth, $data['month']);
        $this->assertEquals(1000.00, $data['base_salary']);
        $this->assertEquals(4000.00, $data['commissions_total']);
        $this->assertEquals(0.00, $data['commission_amount']);
        $this->assertEquals(1000.00, $data['subtotal_before_expenses']);
        $this->assertEquals(200.00, $data['expenses_amount']);
        $this->assertEquals(800.00, $data['subtotal_after_expenses']); // 1000 - 200
        $this->assertEquals(-200.00, $data['available_for_advances']); // 0 - 200
    }

    public function test_returns_404_for_nonexistent_user(): void
    {
        $response = $this->getJson('/api/users/999/salary');

        $response->assertStatus(404)
            ->assertJson(['message' => 'Usuario no encontrado']);
    }

    public function test_calculates_zero_when_no_commissions_or_incomes(): void
    {
        $response = $this->getJson("/api/users/{$this->user->id}/salary");

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertEquals(1000.00, $data['base_salary']);
        $this->assertEquals(0.00, $data['commissions_total']);
        $this->assertEquals(0.00, $data['commission_amount']);
        $this->assertEquals(1000.00, $data['subtotal_before_expenses']);
        $this->assertEquals(0.00, $data['expenses_amount']);
        $this->assertEquals(1000.00, $data['subtotal_after_expenses']);
        $this->assertEquals(0.00, $data['available_for_advances']);
    }

    public function test_excludes_commissions_and_incomes_from_other_months(): void
    {
        // Comisión del mes actual (contable)
        $this->makeCommission($this->user->id, 2000.00, now()->format('Y-m-d'));
        // Comisión de un mes anterior (no debe contarse)
        $this->makeCommission($this->user->id, 5000.00, '2025-06-15');

        Expense::factory()->create([
            'user_id' => $this->user->id,
            'expense_category_id' => $this->expenseCategory->id,
            'amount' => 150.00,
            'date' => now(),
        ]);
        Expense::factory()->create([
            'user_id' => $this->user->id,
            'expense_category_id' => $this->expenseCategory->id,
            'amount' => 500.00,
            'date' => '2025-06-15',
        ]);

        $response = $this->getJson("/api/users/{$this->user->id}/salary");

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertEquals(2000.00, $data['commissions_total']); // Sólo del mes actual
        $this->assertEquals(0.00, $data['commission_amount']);
        $this->assertEquals(1000.00, $data['subtotal_before_expenses']);
        $this->assertEquals(150.00, $data['expenses_amount']); // Sólo del mes actual
        $this->assertEquals(850.00, $data['subtotal_after_expenses']); // 1000 - 150
        $this->assertEquals(-150.00, $data['available_for_advances']); // 0 - 150
    }

    public function test_can_calculate_salary_for_commission_based_contract(): void
    {
        // Contrato fixed_plus_commission: base fijo + % sobre comisiones.
        $commissionUser = User::factory()->create([
            'base_salary' => 0,
            'commission_percentage' => 20.00,
            'contract_type' => 'fixed_plus_commission',
            'branch_id' => $this->branch->id,
        ]);

        $this->makeCommission($commissionUser->id, 6000.00, now()->format('Y-m-d'));

        Expense::factory()->create([
            'user_id' => $commissionUser->id,
            'expense_category_id' => $this->expenseCategory->id,
            'amount' => 300.00,
            'date' => now(),
        ]);

        $response = $this->getJson("/api/users/{$commissionUser->id}/salary");

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertEquals('fixed_plus_commission', $data['contract_type']);
        $this->assertEquals(0.00, $data['base_salary']);
        $this->assertEquals(6000.00, $data['commissions_total']);
        $this->assertEquals(1200.00, $data['commission_amount']); // 6000 * 20%
        $this->assertEquals(1200.00, $data['subtotal_before_expenses']); // 0 + 1200
        $this->assertEquals(300.00, $data['expenses_amount']);
        $this->assertEquals(900.00, $data['subtotal_after_expenses']); // 1200 - 300
        $this->assertEquals(900.00, $data['available_for_advances']); // 1200 - 300
    }

    public function test_can_calculate_salary_for_commission_based_contract_with_no_commissions(): void
    {
        $commissionUser = User::factory()->create([
            'base_salary' => 0,
            'commission_percentage' => 25.00,
            'contract_type' => 'fixed_plus_commission',
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->getJson("/api/users/{$commissionUser->id}/salary");

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertEquals('fixed_plus_commission', $data['contract_type']);
        $this->assertEquals(0.00, $data['base_salary']);
        $this->assertEquals(0.00, $data['commissions_total']);
        $this->assertEquals(0.00, $data['commission_amount']);
        $this->assertEquals(0.00, $data['subtotal_before_expenses']);
        $this->assertEquals(0.00, $data['expenses_amount']);
        $this->assertEquals(0.00, $data['subtotal_after_expenses']);
        $this->assertEquals(0.00, $data['available_for_advances']);
    }
}
