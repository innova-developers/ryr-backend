<?php

namespace Tests\Feature;

use App\Contexts\CurrentAccount\Application\DTO\UpdateCurrentAccountDTO;
use App\Contexts\CurrentAccount\Infrastructure\Repositories\CurrentAccountEloquentRepository;
use App\Shared\Enums\CurrentAccountStatus;
use App\Shared\Models\CurrentAccount;
use App\Shared\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RC-499 (EDICION DE COMISION).
 *
 * Al cambiar el cliente de una comisión, el movimiento de cuenta corriente quedaba
 * colgado del cliente anterior: la comisión aparecía en las dos cuentas. En la base
 * de producción había 4 movimientos así.
 *
 * El UpdateCurrentAccountDTO ni siquiera tenía customer_id, así que no había forma
 * de moverlo.
 */
class CommissionClientChangeTest extends TestCase
{
    use RefreshDatabase;

    private CurrentAccountEloquentRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = app(CurrentAccountEloquentRepository::class);
    }

    private function debito(Customer $customer, float $amount, string $reference): CurrentAccount
    {
        return CurrentAccount::factory()->create([
            'customer_id' => $customer->id,
            'type' => 'debit',
            'status' => CurrentAccountStatus::OK->value,
            'amount' => $amount,
            'balance' => -$amount,
            'reference' => $reference,
            'transaction_date' => now()->toDateString(),
        ]);
    }

    private function dto(int $id, ?int $customerId = null, ?float $amount = null): UpdateCurrentAccountDTO
    {
        return new UpdateCurrentAccountDTO(
            id: $id,
            type: null,
            amount: $amount,
            description: 'Comisión #1 - A a B',
            reference: null,
            transactionDate: null,
            paymentMethod: null,
            observations: null,
            customerId: $customerId,
        );
    }

    public function test_movement_is_reassigned_to_the_new_customer(): void
    {
        $viejo = Customer::factory()->create();
        $nuevo = Customer::factory()->create();
        $mov = $this->debito($viejo, 10000, 'COM-1');

        $this->repo->update($this->dto($mov->id, $nuevo->id));

        $this->assertSame($nuevo->id, $mov->fresh()->customer_id);
    }

    public function test_previous_customer_no_longer_carries_the_debt(): void
    {
        $viejo = Customer::factory()->create();
        $nuevo = Customer::factory()->create();
        $mov = $this->debito($viejo, 10000, 'COM-1');

        $this->repo->update($this->dto($mov->id, $nuevo->id));

        $this->assertSame(0, CurrentAccount::where('customer_id', $viejo->id)->count());
        $this->assertSame(1, CurrentAccount::where('customer_id', $nuevo->id)->count());
    }

    public function test_balances_are_recalculated_for_both_customers(): void
    {
        $viejo = Customer::factory()->create();
        $nuevo = Customer::factory()->create();

        // El viejo tiene otra deuda propia que debe quedar bien tras el traspaso.
        $propia = $this->debito($viejo, 4000, 'COM-9');
        $mov = $this->debito($viejo, 10000, 'COM-1');

        $this->repo->update($this->dto($mov->id, $nuevo->id));

        // El viejo queda debiendo sólo lo suyo.
        $this->assertEquals(-4000, $propia->fresh()->balance);
        // El nuevo arranca su cuenta con el movimiento recibido.
        $this->assertEquals(-10000, $mov->fresh()->balance);
    }

    public function test_same_customer_does_not_trigger_a_move(): void
    {
        $customer = Customer::factory()->create();
        $mov = $this->debito($customer, 10000, 'COM-1');

        $this->repo->update($this->dto($mov->id, $customer->id));

        $this->assertSame($customer->id, $mov->fresh()->customer_id);
        $this->assertEquals(-10000, $mov->fresh()->balance);
    }

    public function test_null_customer_id_leaves_the_owner_untouched(): void
    {
        // Actualizar sólo el monto no debe mover el movimiento de cliente.
        $customer = Customer::factory()->create();
        $mov = $this->debito($customer, 10000, 'COM-1');

        $this->repo->update($this->dto($mov->id, null, 7000));

        $this->assertSame($customer->id, $mov->fresh()->customer_id);
        $this->assertEquals(-7000, $mov->fresh()->balance);
    }
}
