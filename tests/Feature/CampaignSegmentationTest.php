<?php

namespace Tests\Feature;

use App\Services\CampaignService;
use App\Shared\Enums\CurrentAccountStatus;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\CurrentAccount;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RC-485 (INCONSISTENCIA) y RC-488 (CAMPAÑAS WHATSAPP).
 *
 * Campañas segmentaba por is_premium y lo rotulaba "Premium", mientras la ficha del
 * cliente usa customers.type ("Cliente común" / "Empresa"). Son campos distintos, y
 * en producción is_premium está en 0 para los 3418 clientes, así que ese filtro no
 * podía devolver a nadie.
 *
 * Además faltaba el filtro por monto, y los cortes numéricos usaban empty(), que
 * descarta el 0 en silencio.
 */
class CampaignSegmentationTest extends TestCase
{
    use RefreshDatabase;

    private CampaignService $service;
    private Branch $branch;
    private Destination $destination;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CampaignService::class);
        $this->branch = Branch::factory()->create();
        $this->destination = Destination::factory()->create();
        $this->user = User::factory()->create(['role' => 'administrador', 'branch_id' => $this->branch->id]);
    }

    private function commissionFor(Customer $customer, float $total): void
    {
        Commission::factory()->create([
            'client_id' => $customer->id,
            'destination_id' => $this->destination->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'total' => $total,
        ]);
    }

    public function test_filters_by_customer_type(): void
    {
        $empresa = Customer::factory()->create(['type' => 'company']);
        Customer::factory()->create(['type' => 'individual']);

        $result = $this->service->getSegmentedCustomers(['type' => ['company']]);

        $this->assertCount(1, $result);
        $this->assertSame($empresa->id, $result->first()->id);
    }

    public function test_type_filter_accepts_multiple_categories(): void
    {
        Customer::factory()->create(['type' => 'company']);
        Customer::factory()->create(['type' => 'individual']);

        $result = $this->service->getSegmentedCustomers(['type' => ['company', 'individual']]);

        $this->assertCount(2, $result);
    }

    public function test_no_type_filter_returns_all(): void
    {
        Customer::factory()->create(['type' => 'company']);
        Customer::factory()->create(['type' => 'individual']);

        $this->assertCount(2, $this->service->getSegmentedCustomers([]));
    }

    public function test_filters_by_minimum_billed_amount(): void
    {
        $grande = Customer::factory()->create();
        $chico = Customer::factory()->create();
        $this->commissionFor($grande, 8000);
        $this->commissionFor($grande, 4000); // total 12000
        $this->commissionFor($chico, 1000);

        $result = $this->service->getSegmentedCustomers(['min_commission_amount' => 10000]);

        $this->assertCount(1, $result);
        $this->assertSame($grande->id, $result->first()->id);
    }

    public function test_filters_by_maximum_billed_amount(): void
    {
        $grande = Customer::factory()->create();
        $chico = Customer::factory()->create();
        $this->commissionFor($grande, 12000);
        $this->commissionFor($chico, 1000);

        $result = $this->service->getSegmentedCustomers(['max_commission_amount' => 5000]);

        $this->assertCount(1, $result);
        $this->assertSame($chico->id, $result->first()->id);
    }

    public function test_min_commissions_zero_is_honoured(): void
    {
        // Con empty() este corte se ignoraba y devolvía a todos igual; el caso
        // "0 o más" tiene que ser explícito y devolver también a los que no tienen.
        Customer::factory()->create();

        $result = $this->service->getSegmentedCustomers(['min_commissions' => 0]);

        $this->assertCount(1, $result);
    }

    public function test_min_commissions_filters_by_count(): void
    {
        $conDos = Customer::factory()->create();
        $conUna = Customer::factory()->create();
        $this->commissionFor($conDos, 100);
        $this->commissionFor($conDos, 100);
        $this->commissionFor($conUna, 100);

        $result = $this->service->getSegmentedCustomers(['min_commissions' => 2]);

        $this->assertCount(1, $result);
        $this->assertSame($conDos->id, $result->first()->id);
    }

    public function test_balance_filter_includes_customers_without_movements_as_zero(): void
    {
        // Antes, whereHas + havingRaw dejaba afuera a los clientes sin movimientos,
        // aunque su saldo es 0 y debería entrar en un corte "saldo <= 0".
        $sinMovimientos = Customer::factory()->create();
        $deudor = Customer::factory()->create();

        CurrentAccount::factory()->create([
            'customer_id' => $deudor->id,
            'type' => 'debit',
            'amount' => 5000,
            'status' => CurrentAccountStatus::OK->value,
            'balance' => -5000,
            'transaction_date' => now()->toDateString(),
        ]);

        $result = $this->service->getSegmentedCustomers(['max_balance' => 0]);

        $ids = $result->pluck('id')->all();
        $this->assertContains($sinMovimientos->id, $ids);
        $this->assertContains($deudor->id, $ids);
    }

    public function test_min_balance_excludes_debtors(): void
    {
        $acreedor = Customer::factory()->create();
        $deudor = Customer::factory()->create();

        CurrentAccount::factory()->create([
            'customer_id' => $acreedor->id,
            'type' => 'credit',
            'amount' => 3000,
            'status' => CurrentAccountStatus::OK->value,
            'balance' => 3000,
            'transaction_date' => now()->toDateString(),
        ]);
        CurrentAccount::factory()->create([
            'customer_id' => $deudor->id,
            'type' => 'debit',
            'amount' => 3000,
            'status' => CurrentAccountStatus::OK->value,
            'balance' => -3000,
            'transaction_date' => now()->toDateString(),
        ]);

        $result = $this->service->getSegmentedCustomers(['min_balance' => 1000]);

        $this->assertCount(1, $result);
        $this->assertSame($acreedor->id, $result->first()->id);
    }

    public function test_type_combines_with_other_filters(): void
    {
        // La card pide explícitamente que la categoría se pueda combinar con monto.
        $empresaGrande = Customer::factory()->create(['type' => 'company']);
        $empresaChica = Customer::factory()->create(['type' => 'company']);
        $individuoGrande = Customer::factory()->create(['type' => 'individual']);

        $this->commissionFor($empresaGrande, 20000);
        $this->commissionFor($empresaChica, 500);
        $this->commissionFor($individuoGrande, 20000);

        $result = $this->service->getSegmentedCustomers([
            'type' => ['company'],
            'min_commission_amount' => 10000,
        ]);

        $this->assertCount(1, $result);
        $this->assertSame($empresaGrande->id, $result->first()->id);
    }
}
