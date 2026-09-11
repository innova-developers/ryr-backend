<?php

namespace Tests\Feature;

use App\Contexts\CurrentAccount\Application\DTO\CreateCurrentAccountDTO;
use App\Contexts\CurrentAccount\Domain\Repositories\CurrentAccountRepository;
use App\Contexts\Commissions\Application\UpdateCommissionStatusUseCase;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\CommissionType;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\CurrentAccount;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RC-522.
 *
 * "Cliente abonó, se registró el pago y se confirmó pero la deuda sigue apareciendo en
 * pool de cobranzas."
 *
 * El pase de PAGO_VALIDACION a PAGO_CONFIRMADO se evaluaba en un solo punto —al
 * confirmar un crédito— y exigía saldo exactamente 0. Con eso se caían dos casos
 * reales: el cliente que queda con saldo a favor (0,01 o $500), y el pago confirmado
 * antes de que la comisión generara su débito, donde la regla no se volvía a mirar
 * nunca. En la copia de producción del 11/09 había 308 comisiones colgadas así, de 32
 * clientes que ya no debían nada.
 */
class CommissionSettlementTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private User $admin;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->admin = User::factory()->create(['role' => 'administrador', 'branch_id' => $this->branch->id]);

        $this->actingAs($this->admin, 'sanctum');
    }

    private function comision(float $total, string $status): Commission
    {
        return Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => Destination::factory()->create()->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'type' => CommissionType::ORDINARIA->value,
            'total' => $total,
            'status' => $status,
        ]);
    }

    private function pago(float $monto, string $fecha): CurrentAccount
    {
        return app(CurrentAccountRepository::class)->create(new CreateCurrentAccountDTO(
            customerId: $this->customer->id,
            type: 'credit',
            amount: $monto,
            description: 'PAGO REGISTRADO',
            reference: null,
            transactionDate: $fecha,
            paymentMethod: 'EFECTIVO',
            observations: null,
            userId: $this->admin->id,
        ));
    }

    private function estado(Commission $commission): string
    {
        return Commission::find($commission->id)->status->value;
    }

    public function test_confirmar_el_pago_cierra_la_comision(): void
    {
        $commission = $this->comision(41000, CommissionStatus::PAGO_VALIDACION->value);

        CurrentAccount::factory()->create([
            'customer_id' => $this->customer->id,
            'type' => 'debit',
            'amount' => 41000,
            'balance' => -41000,
            'status' => 'OK',
            'reference' => "COM-{$commission->id}",
            'transaction_date' => now()->toDateString(),
        ]);

        $pago = $this->pago(41000, now()->toDateString());
        app(CurrentAccountRepository::class)->confirmTransaction($pago->id);

        $this->assertSame(CommissionStatus::PAGO_CONFIRMADO->value, $this->estado($commission));
    }

    public function test_un_saldo_a_favor_de_centavos_no_deja_la_comision_colgada(): void
    {
        // El caso del cliente 2315 en producción: saldo 0,01 a favor y dos comisiones
        // que nunca cerraban porque la comparación era == 0.
        $commission = $this->comision(99249.99, CommissionStatus::PAGO_VALIDACION->value);

        CurrentAccount::factory()->create([
            'customer_id' => $this->customer->id,
            'type' => 'debit',
            'amount' => 99249.99,
            'balance' => -99249.99,
            'status' => 'OK',
            'reference' => "COM-{$commission->id}",
            'transaction_date' => now()->toDateString(),
        ]);

        $pago = $this->pago(99250.00, now()->toDateString());
        app(CurrentAccountRepository::class)->confirmTransaction($pago->id);

        $this->assertSame(CommissionStatus::PAGO_CONFIRMADO->value, $this->estado($commission));
    }

    public function test_un_pago_confirmado_antes_del_debito_cierra_la_comision_al_facturarla(): void
    {
        // El caso del cliente 3306: pagó el 05/04, se confirmó el 08/04, y la comisión
        // recién generó su débito después. La comisión quedó en PAGO_VALIDACION un año.
        $pago = $this->pago(41000, now()->subDays(5)->toDateString());
        app(CurrentAccountRepository::class)->confirmTransaction($pago->id);

        $commission = $this->comision(41000, CommissionStatus::EN_PLANTA->value);

        app(UpdateCommissionStatusUseCase::class)(
            $commission->id,
            CommissionStatus::PENDIENTE_PAGO,
            'Facturación'
        );

        $this->assertSame(CommissionStatus::PAGO_CONFIRMADO->value, $this->estado($commission));
    }

    public function test_un_cliente_que_sigue_debiendo_no_cierra_sus_comisiones(): void
    {
        $commission = $this->comision(41000, CommissionStatus::PAGO_VALIDACION->value);

        CurrentAccount::factory()->create([
            'customer_id' => $this->customer->id,
            'type' => 'debit',
            'amount' => 41000,
            'balance' => -41000,
            'status' => 'OK',
            'reference' => "COM-{$commission->id}",
            'transaction_date' => now()->toDateString(),
        ]);

        $pago = $this->pago(10000, now()->toDateString());
        app(CurrentAccountRepository::class)->confirmTransaction($pago->id);

        $this->assertSame(CommissionStatus::PAGO_VALIDACION->value, $this->estado($commission));
    }

    public function test_un_pago_parcial_cierra_la_comision_mas_vieja_y_deja_viva_la_otra(): void
    {
        // El caso del video de la card: el cliente 417 tenía dos comisiones de $11.500
        // —31/08 y 04/09— y pagó una sola. Las dos siguieron en el pool.
        $vieja = $this->comision(11500, CommissionStatus::PAGO_VALIDACION->value);
        $nueva = $this->comision(11500, CommissionStatus::PAGO_VALIDACION->value);

        foreach ([[$vieja, 11, -11500], [$nueva, 7, -23000]] as [$commission, $diasAtras, $balance]) {
            CurrentAccount::factory()->create([
                'customer_id' => $this->customer->id,
                'type' => 'debit',
                'amount' => 11500,
                'balance' => $balance,
                'status' => 'OK',
                'reference' => "COM-{$commission->id}",
                'transaction_date' => now()->subDays($diasAtras)->toDateString(),
            ]);
        }

        $pago = $this->pago(11500, now()->toDateString());
        app(CurrentAccountRepository::class)->confirmTransaction($pago->id);

        $this->assertSame(
            CommissionStatus::PAGO_CONFIRMADO->value,
            $this->estado($vieja),
            'el pago tiene que imputarse a la comisión más vieja'
        );
        $this->assertSame(
            CommissionStatus::PAGO_VALIDACION->value,
            $this->estado($nueva),
            'la comisión que sigue impaga no puede cerrarse'
        );
    }

    public function test_el_cliente_saldado_deja_de_tener_cobrador_asignado(): void
    {
        $this->customer->update(['internal_user_id' => $this->admin->id]);

        $commission = $this->comision(11500, CommissionStatus::PAGO_VALIDACION->value);

        CurrentAccount::factory()->create([
            'customer_id' => $this->customer->id,
            'type' => 'debit',
            'amount' => 11500,
            'balance' => -11500,
            'status' => 'OK',
            'reference' => "COM-{$commission->id}",
            'transaction_date' => now()->toDateString(),
        ]);

        $pago = $this->pago(11500, now()->toDateString());
        app(CurrentAccountRepository::class)->confirmTransaction($pago->id);

        $this->assertNull(Customer::find($this->customer->id)->internal_user_id);
    }
}
