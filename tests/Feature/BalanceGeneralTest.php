<?php

namespace Tests\Feature;

use App\Shared\Enums\CurrentAccountStatus;
use App\Shared\Models\Branch;
use App\Shared\Models\CurrentAccount;
use App\Shared\Models\Customer;
use App\Shared\Models\Expense;
use App\Shared\Models\ExpenseCategory;
use App\Shared\Models\Income;
use App\Shared\Models\IncomeCategory;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RC-487 (BALANCE AUTOMÁTICO).
 *
 * Tres defectos verificados sobre la base de producción:
 *
 * 1. Los 838 movimientos manuales de Ingresos ($203M) no se computaban. De esos,
 *    sólo "Ventas" es ingreso independiente: el resto de las categorías son formas
 *    de pago con las que se registra a mano la misma plata que ya entra por cuenta
 *    corriente, así que sumarlas duplicaría.
 * 2. El filtro de sucursal se aplicaba a los ingresos pero no a los egresos (la
 *    condición dependía de expenses.branch_id, columna que no existe).
 * 3. Ordinario vs extraordinario se deducía buscando "extra" en el nombre de la
 *    categoría, y ninguna de las 107 categorías reales lo contiene.
 */
class BalanceGeneralTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::factory()->create();
        $this->admin = User::factory()->create(['role' => 'administrador', 'branch_id' => null]);
    }

    private function cobro(float $amount, ?Customer $customer = null): void
    {
        CurrentAccount::factory()->create([
            'customer_id' => ($customer ?? Customer::factory()->create())->id,
            'type' => 'credit',
            'status' => CurrentAccountStatus::OK->value,
            'amount' => $amount,
            'balance' => $amount,
            'transaction_date' => now()->toDateString(),
        ]);
    }

    private function ingresoManual(string $categoria, float $amount, ?User $user = null): void
    {
        $cat = IncomeCategory::factory()->create(['name' => $categoria]);
        Income::factory()->create([
            'income_category_id' => $cat->id,
            'user_id' => ($user ?? $this->admin)->id,
            'amount' => $amount,
            'date' => now()->toDateString(),
        ]);
    }

    private function gasto(float $amount, bool $extraordinario = false, ?User $user = null): void
    {
        // El nombre lleva sufijo único: expense_categories.name tiene índice único.
        $cat = ExpenseCategory::factory()->create([
            'name' => ($extraordinario ? 'Compra de unidad ' : 'Combustible ') . uniqid(),
            'is_extraordinary' => $extraordinario,
        ]);
        Expense::factory()->create([
            'expense_category_id' => $cat->id,
            'user_id' => ($user ?? $this->admin)->id,
            'amount' => $amount,
            'date' => now()->toDateString(),
        ]);
    }

    private function balance(array $params = []): array
    {
        return $this->actingAs($this->admin)
            ->getJson('/api/admin/balances/general?' . http_build_query($params))
            ->assertOk()
            ->json('data');
    }

    public function test_sales_income_is_reported_and_added_to_final_balance(): void
    {
        $this->cobro(10000);
        $this->ingresoManual('ventas', 5000);
        $this->gasto(3000);

        $d = $this->balance();

        $this->assertEquals(10000, $d['total_ingresos']);
        $this->assertEquals(5000, $d['total_ingresos_ventas']);
        $this->assertEquals(12000, $d['balance_final']); // 10000 + 5000 - 3000
    }

    public function test_other_manual_income_is_reported_but_not_summed(): void
    {
        // "efectivo"/"transferencia" registran a mano cobranzas ya contadas: si
        // sumaran, el balance duplicaría esos ingresos.
        $this->cobro(10000);
        $this->ingresoManual('efectivo', 7000);
        $this->ingresoManual('transferencia', 3000);

        $d = $this->balance();

        $this->assertEquals(10000, $d['total_ingresos']);
        $this->assertEquals(0, $d['total_ingresos_ventas']);
        $this->assertEquals(10000, $d['total_ingresos_manuales_otros']);
        $this->assertEquals(10000, $d['balance_final']);
    }

    public function test_extraordinary_expenses_are_split_by_flag_not_by_name(): void
    {
        $this->gasto(1000, false);
        $this->gasto(9000, true);

        $d = $this->balance();

        $this->assertEquals(1000, $d['total_egresos_ordinarios']);
        $this->assertEquals(9000, $d['total_egresos_extraordinarios']);
        $this->assertEquals(-1000, $d['balance_final']); // sólo ordinarios restan
    }

    public function test_category_named_extra_is_not_extraordinary_by_itself(): void
    {
        // Renombrar una categoría ya no cambia el balance.
        $cat = ExpenseCategory::factory()->create(['name' => 'Gastos extras varios', 'is_extraordinary' => false]);
        Expense::factory()->create([
            'expense_category_id' => $cat->id,
            'user_id' => $this->admin->id,
            'amount' => 4000,
            'date' => now()->toDateString(),
        ]);

        $d = $this->balance();

        $this->assertEquals(4000, $d['total_egresos_ordinarios']);
        $this->assertEquals(0, $d['total_egresos_extraordinarios']);
    }

    public function test_branch_filter_applies_to_expenses_too(): void
    {
        $otraSucursal = Branch::factory()->create();
        $empleadoA = User::factory()->create(['role' => 'mostrador', 'branch_id' => $this->branch->id]);
        $empleadoB = User::factory()->create(['role' => 'mostrador', 'branch_id' => $otraSucursal->id]);

        $this->gasto(1000, false, $empleadoA);
        $this->gasto(8000, false, $empleadoB);

        $d = $this->balance(['branch_id' => $this->branch->id]);

        // Antes devolvía 9000: los ingresos se acotaban por sucursal y los egresos no.
        $this->assertEquals(1000, $d['total_egresos_ordinarios']);
    }

    public function test_branch_filter_applies_to_manual_income_too(): void
    {
        $otraSucursal = Branch::factory()->create();
        $empleadoA = User::factory()->create(['role' => 'mostrador', 'branch_id' => $this->branch->id]);
        $empleadoB = User::factory()->create(['role' => 'mostrador', 'branch_id' => $otraSucursal->id]);

        $this->ingresoManual('ventas', 2000, $empleadoA);
        $this->ingresoManual('ventas', 6000, $empleadoB);

        $d = $this->balance(['branch_id' => $this->branch->id]);

        $this->assertEquals(2000, $d['total_ingresos_ventas']);
    }

    public function test_amounts_are_taken_by_real_date(): void
    {
        $this->cobro(10000);
        $this->ingresoManual('ventas', 1000);

        // Movimiento fuera del rango: no debe entrar.
        $catVieja = IncomeCategory::factory()->create(['name' => 'ventas']);
        Income::factory()->create([
            'income_category_id' => $catVieja->id,
            'user_id' => $this->admin->id,
            'amount' => 999999,
            'date' => now()->subMonths(3)->toDateString(),
        ]);

        $d = $this->balance([
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->endOfMonth()->toDateString(),
        ]);

        $this->assertEquals(1000, $d['total_ingresos_ventas']);
    }

    public function test_pending_collections_are_not_counted_as_income(): void
    {
        CurrentAccount::factory()->create([
            'customer_id' => Customer::factory()->create()->id,
            'type' => 'credit',
            'status' => CurrentAccountStatus::PENDIENTE->value,
            'amount' => 50000,
            'balance' => 0,
            'transaction_date' => now()->toDateString(),
        ]);

        $this->assertEquals(0, $this->balance()['total_ingresos']);
    }
}
