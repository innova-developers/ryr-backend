<?php

namespace Tests\Feature\Users;

use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Expense;
use App\Shared\Models\ExpenseCategory;
use App\Shared\Models\Income;
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

        // Crear datos relacionados necesarios
        $this->customer = Customer::factory()->create();
        $this->destination = Destination::factory()->create();
        $this->branch = Branch::factory()->create();
        $this->originLocation = Location::factory()->create();
        $this->destinationLocation = Location::factory()->create();
        $this->expenseCategory = ExpenseCategory::factory()->create();

        $this->user = User::factory()->create([
            'base_salary' => 1000.00,
            'commission_percentage' => 10.00,
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_can_calculate_salary_for_current_month(): void
    {
        // Crear comisiones para el usuario
        Commission::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'branch_id' => $this->branch->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'total' => 5000.00,
            'date' => now(),
        ]);

        Commission::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'branch_id' => $this->branch->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'total' => 3000.00,
            'date' => now(),
        ]);

        // Crear ingresos para el usuario
        Income::factory()->create([
            'user_id' => $this->user->id,
            'amount' => 2000.00,
            'date' => now(),
        ]);

        Income::factory()->create([
            'user_id' => $this->user->id,
            'amount' => 1000.00,
            'date' => now(),
        ]);

        // Crear gastos para el usuario
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
                'base_salary',
                'commissions_total',
                'commission_amount',
                'incomes_total',
                'income_amount',
                'subtotal_before_expenses',
                'expenses_amount',
                'subtotal_after_expenses',
                'available_for_advances',
            ]);

        $data = $response->json();

        // Verificar cálculos
        $this->assertEquals(1000.00, $data['base_salary']);
        $this->assertEquals(8000.00, $data['commissions_total']); // 5000 + 3000
        $this->assertEquals(800.00, $data['commission_amount']); // 8000 * 10%
        $this->assertEquals(3000.00, $data['incomes_total']); // 2000 + 1000
        $this->assertEquals(300.00, $data['income_amount']); // 3000 * 10%
        $this->assertEquals(2100.00, $data['subtotal_before_expenses']); // 1000 + 800 + 300
        $this->assertEquals(800.00, $data['expenses_amount']); // 500 + 300
        $this->assertEquals(1300.00, $data['subtotal_after_expenses']); // 2100 - 800
        $this->assertEquals(300.00, $data['available_for_advances']); // 800 + 300 - 800
    }

    public function test_can_calculate_salary_for_specific_month(): void
    {
        $specificMonth = '2024-01';

        // Crear comisiones para el mes específico
        Commission::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'branch_id' => $this->branch->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'total' => 4000.00,
            'date' => '2024-01-15',
        ]);

        // Crear ingresos para el mes específico
        Income::factory()->create([
            'user_id' => $this->user->id,
            'amount' => 1500.00,
            'date' => '2024-01-20',
        ]);

        // Crear gastos para el mes específico
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
        $this->assertEquals(400.00, $data['commission_amount']); // 4000 * 10%
        $this->assertEquals(1500.00, $data['incomes_total']);
        $this->assertEquals(150.00, $data['income_amount']); // 1500 * 10%
        $this->assertEquals(1550.00, $data['subtotal_before_expenses']); // 1000 + 400 + 150
        $this->assertEquals(200.00, $data['expenses_amount']);
        $this->assertEquals(1350.00, $data['subtotal_after_expenses']); // 1550 - 200
        $this->assertEquals(350.00, $data['available_for_advances']); // 400 + 150 - 200
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
        $this->assertEquals(0.00, $data['incomes_total']);
        $this->assertEquals(0.00, $data['income_amount']);
        $this->assertEquals(1000.00, $data['subtotal_before_expenses']);
        $this->assertEquals(0.00, $data['expenses_amount']);
        $this->assertEquals(1000.00, $data['subtotal_after_expenses']);
        $this->assertEquals(0.00, $data['available_for_advances']);
    }

    public function test_excludes_commissions_and_incomes_from_other_months(): void
    {
        // Crear comisiones para el mes actual
        Commission::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'branch_id' => $this->branch->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'total' => 2000.00,
            'date' => now(),
        ]);

        // Crear comisiones para el mes anterior (no deben incluirse)
        Commission::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'branch_id' => $this->branch->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'total' => 5000.00,
            'date' => now()->subMonth(),
        ]);

        // Crear ingresos para el mes actual
        Income::factory()->create([
            'user_id' => $this->user->id,
            'amount' => 1000.00,
            'date' => now(),
        ]);

        // Crear ingresos para el mes anterior (no deben incluirse)
        Income::factory()->create([
            'user_id' => $this->user->id,
            'amount' => 3000.00,
            'date' => now()->subMonth(),
        ]);

        // Crear gastos para el mes actual
        Expense::factory()->create([
            'user_id' => $this->user->id,
            'expense_category_id' => $this->expenseCategory->id,
            'amount' => 150.00,
            'date' => now(),
        ]);

        // Crear gastos para el mes anterior (no deben incluirse)
        Expense::factory()->create([
            'user_id' => $this->user->id,
            'expense_category_id' => $this->expenseCategory->id,
            'amount' => 500.00,
            'date' => now()->subMonth(),
        ]);

        $response = $this->getJson("/api/users/{$this->user->id}/salary");

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertEquals(2000.00, $data['commissions_total']); // Solo del mes actual
        $this->assertEquals(200.00, $data['commission_amount']); // 2000 * 10%
        $this->assertEquals(1000.00, $data['incomes_total']); // Solo del mes actual
        $this->assertEquals(100.00, $data['income_amount']); // 1000 * 10%
        $this->assertEquals(1300.00, $data['subtotal_before_expenses']); // 1000 + 200 + 100
        $this->assertEquals(150.00, $data['expenses_amount']); // Solo del mes actual
        $this->assertEquals(1150.00, $data['subtotal_after_expenses']); // 1300 - 150
        $this->assertEquals(150.00, $data['available_for_advances']); // 200 + 100 - 150
    }
}
