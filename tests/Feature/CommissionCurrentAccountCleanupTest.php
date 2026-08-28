<?php

namespace Tests\Feature;

use App\Contexts\Commissions\Application\DeleteCommissionUseCase;
use App\Contexts\Commissions\Application\UpdateCommissionStatusUseCase;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\CurrentAccount;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RC-512 y RC-516.
 *
 * El débito de cuenta corriente se creaba al facturar la comisión pero no se daba de
 * baja nunca: ni al borrarla, ni al retroceder su estado desde PAGO_VALIDACION. En
 * producción eso dejó 11 movimientos huérfanos por $190.001 en 10 clientes. El efecto
 * visible es doble: el cliente arrastra deuda de una comisión que ya no existe, y como
 * su saldo nunca vuelve a cero, el marcado masivo a PAGO_CONFIRMADO no se dispara y no
 * sale más del pool de cobranza.
 */
class CommissionCurrentAccountCleanupTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Commission $commission;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->admin = User::factory()->create(['role' => 'administrador', 'branch_id' => $branch->id]);

        // Los use cases registran el log con Auth::id(), que es obligatorio.
        $this->actingAs($this->admin, 'sanctum');

        $this->commission = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => Destination::factory()->create()->id,
            'branch_id' => $branch->id,
            'user_id' => $this->admin->id,
            'total' => 17500,
            'status' => CommissionStatus::PAGO_VALIDACION->value,
        ]);

        CurrentAccount::factory()->create([
            'customer_id' => $this->customer->id,
            'type' => 'debit',
            'amount' => 17500,
            'balance' => -17500,
            'status' => 'OK',
            'reference' => "COM-{$this->commission->id}",
            'transaction_date' => now()->toDateString(),
        ]);
    }

    private function debitosVivos(): int
    {
        return CurrentAccount::where('reference', "COM-{$this->commission->id}")->count();
    }

    public function test_borrar_una_comision_da_de_baja_su_debito(): void
    {
        $this->assertSame(1, $this->debitosVivos());

        app(DeleteCommissionUseCase::class)($this->commission->id);

        $this->assertSame(0, $this->debitosVivos(), 'el débito quedó huérfano tras borrar la comisión');
    }

    public function test_retroceder_desde_pago_validacion_da_de_baja_el_debito(): void
    {
        app(UpdateCommissionStatusUseCase::class)(
            $this->commission->id,
            CommissionStatus::EN_PLANTA,
            'Retroceso de estado'
        );

        $this->assertSame(0, $this->debitosVivos(), 'el débito sobrevivió al retroceso de estado');
    }

    public function test_el_saldo_del_cliente_vuelve_a_cero_al_borrar_la_comision(): void
    {
        // Es la mitad que importa para el pool: si el saldo no vuelve a cero, el
        // cliente no sale nunca de la lista de deudores.
        app(DeleteCommissionUseCase::class)($this->commission->id);

        $saldo = CurrentAccount::where('customer_id', $this->customer->id)
            ->where('status', 'OK')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->value('balance');

        $this->assertTrue($saldo === null || abs((float) $saldo) < 0.01, "saldo esperado 0, quedó {$saldo}");
    }
}
