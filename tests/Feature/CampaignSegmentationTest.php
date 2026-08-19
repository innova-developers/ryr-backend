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

    /**
     * Crea la comisión y su débito de cuenta corriente, que es lo que pasa en
     * producción al facturarla. El monto total del cliente sale de esos débitos.
     */
    private function commissionFor(Customer $customer, float $total): void
    {
        Commission::factory()->create([
            'client_id' => $customer->id,
            'destination_id' => $this->destination->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'total' => $total,
        ]);

        $this->movimiento($customer, 'debit', $total);
    }

    private function movimiento(Customer $customer, string $type, float $amount): void
    {
        CurrentAccount::factory()->create([
            'customer_id' => $customer->id,
            'type' => $type,
            'amount' => $amount,
            'status' => CurrentAccountStatus::OK->value,
            'balance' => $type === 'credit' ? $amount : -$amount,
            'transaction_date' => now()->toDateString(),
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

        $result = $this->service->getSegmentedCustomers(['min_total_amount' => 10000]);

        $this->assertCount(1, $result);
        $this->assertSame($grande->id, $result->first()->id);
    }

    public function test_filters_by_maximum_billed_amount(): void
    {
        $grande = Customer::factory()->create();
        $chico = Customer::factory()->create();
        $this->commissionFor($grande, 12000);
        $this->commissionFor($chico, 1000);

        $result = $this->service->getSegmentedCustomers(['max_total_amount' => 5000]);

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

    public function test_total_amount_cuenta_cargos_que_no_son_comisiones(): void
    {
        // El monto total es el libro mayor de débitos, no sólo las comisiones: un
        // cargo manual también se le facturó al cliente y antes no se contaba.
        $cliente = Customer::factory()->create();
        $this->commissionFor($cliente, 4000);
        $this->movimiento($cliente, 'debit', 6000);

        $this->assertCount(1, $this->service->getSegmentedCustomers(['min_total_amount' => 10000]));
        $this->assertCount(0, $this->service->getSegmentedCustomers(['min_total_amount' => 10001]));
    }

    public function test_total_amount_acepta_las_claves_viejas(): void
    {
        // Las campañas ya guardadas usan min/max_commission_amount.
        $cliente = Customer::factory()->create();
        $this->commissionFor($cliente, 12000);

        $this->assertCount(1, $this->service->getSegmentedCustomers(['min_commission_amount' => 10000]));
        $this->assertCount(0, $this->service->getSegmentedCustomers(['max_commission_amount' => 5000]));
    }

    public function test_balance_type_deudor_trae_a_los_que_deben(): void
    {
        $deudor = Customer::factory()->create();
        $acreedor = Customer::factory()->create();
        $enCero = Customer::factory()->create();

        $this->movimiento($deudor, 'debit', 5000);
        $this->movimiento($acreedor, 'credit', 3000);

        $result = $this->service->getSegmentedCustomers(['balance_type' => 'deudor']);

        $this->assertCount(1, $result);
        $this->assertSame($deudor->id, $result->first()->id);
        $this->assertNotContains($enCero->id, $result->pluck('id')->all());
    }

    public function test_balance_type_acreedor_trae_a_los_que_tienen_a_favor(): void
    {
        $deudor = Customer::factory()->create();
        $acreedor = Customer::factory()->create();

        $this->movimiento($deudor, 'debit', 5000);
        $this->movimiento($acreedor, 'credit', 3000);

        $result = $this->service->getSegmentedCustomers(['balance_type' => 'acreedor']);

        $this->assertCount(1, $result);
        $this->assertSame($acreedor->id, $result->first()->id);
    }

    public function test_monto_en_cc_se_carga_en_positivo_para_ambos_lados(): void
    {
        // El operador nunca escribe un signo: elige el lado y carga el monto.
        $debeMucho = Customer::factory()->create();
        $debePoco = Customer::factory()->create();

        $this->movimiento($debeMucho, 'debit', 9000);
        $this->movimiento($debePoco, 'debit', 1000);

        $result = $this->service->getSegmentedCustomers([
            'balance_type' => 'deudor',
            'min_balance_amount' => 5000,
        ]);

        $this->assertCount(1, $result);
        $this->assertSame($debeMucho->id, $result->first()->id);
    }

    public function test_saldo_neto_no_cuenta_al_que_ya_pago(): void
    {
        // Se le facturó y pagó todo: no es deudor ni acreedor, aunque tenga
        // movimientos por un monto alto.
        $salda = Customer::factory()->create();
        $this->commissionFor($salda, 8000);
        $this->movimiento($salda, 'credit', 8000);

        $this->assertCount(0, $this->service->getSegmentedCustomers(['balance_type' => 'deudor']));
        $this->assertCount(0, $this->service->getSegmentedCustomers(['balance_type' => 'acreedor']));
        // Pero sí entra por monto total facturado.
        $this->assertCount(1, $this->service->getSegmentedCustomers(['min_total_amount' => 8000]));
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
            'min_total_amount' => 10000,
        ]);

        $this->assertCount(1, $result);
        $this->assertSame($empresaGrande->id, $result->first()->id);
    }
}
