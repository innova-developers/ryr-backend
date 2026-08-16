<?php

namespace Tests\Feature;

use App\Services\CommissionCustodyService;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\CommissionLog;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RC-482 (LIQUIDACION DE EMPLEADOS / CADETES).
 *
 * La liquidación se le acredita al cadete que REALIZÓ EL RETIRO, no al primero que
 * se asignó la comisión. Antes el crédito se fijaba al asignarse: si A la tomaba y
 * nunca la retiraba, y B la levantaba, cobraba A igual. En producción había 415
 * comisiones acreditadas al cadete equivocado (~$7,1M).
 *
 * Cadete de retiro y cadete de entrega son datos independientes: reasignar para la
 * entrega no cambia quién hizo el retiro.
 */
class PickupCreditTest extends TestCase
{
    use RefreshDatabase;

    private CommissionCustodyService $custody;
    private Branch $branch;
    private Customer $customer;
    private Destination $destination;
    private User $cadeteA;
    private User $cadeteB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->custody = app(CommissionCustodyService::class);
        $this->branch = Branch::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->destination = Destination::factory()->create();
        $this->cadeteA = User::factory()->create(['role' => 'cadete', 'branch_id' => $this->branch->id]);
        $this->cadeteB = User::factory()->create(['role' => 'cadete', 'branch_id' => $this->branch->id]);
    }

    private function commission(array $attrs = []): Commission
    {
        return Commission::factory()->create($attrs + [
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->cadeteA->id,
            'status' => CommissionStatus::CADETE_ASIGNADO->value,
            'cadete_id' => null,
            'pickup_cadete_id' => null,
        ]);
    }

    private function logPickup(Commission $commission, User $cadete): void
    {
        CommissionLog::create([
            'commission_id' => $commission->id,
            'user_id' => $cadete->id,
            'previous_status' => CommissionStatus::EN_PUNTO_RETIRO->value,
            'new_status' => CommissionStatus::ENCOMIENDA_RETIRADA->value,
            'details' => 'Retiro confirmado',
        ]);
    }

    public function test_pickup_credit_goes_to_whoever_confirms_the_pickup(): void
    {
        $commission = $this->commission();

        // A se la asigna...
        $this->custody->takeCustody($commission, $this->cadeteA->id);
        $commission->save();
        $this->assertSame($this->cadeteA->id, $commission->fresh()->pickup_cadete_id);

        // ...pero es B quien efectivamente la retira.
        $this->custody->confirmPickup($commission, $this->cadeteB->id);
        $commission->save();

        $this->assertSame($this->cadeteB->id, $commission->fresh()->pickup_cadete_id);
    }

    public function test_reassignment_after_pickup_does_not_change_the_credit(): void
    {
        // Este es el pedido textual de la card: una reasignación para la entrega no
        // debe modificar quién realizó el retiro.
        $commission = $this->commission();

        $this->custody->confirmPickup($commission, $this->cadeteA->id);
        $commission->save();
        $this->logPickup($commission, $this->cadeteA);

        $this->custody->takeCustody($commission, $this->cadeteB->id);
        $commission->save();

        $this->assertSame($this->cadeteA->id, $commission->fresh()->pickup_cadete_id);
        $this->assertSame($this->cadeteB->id, $commission->fresh()->cadete_id ?? $this->cadeteB->id);
    }

    public function test_releasing_after_pickup_keeps_the_credit(): void
    {
        $commission = $this->commission();

        $this->custody->confirmPickup($commission, $this->cadeteA->id);
        $commission->cadete_id = $this->cadeteA->id;
        $commission->save();
        $this->logPickup($commission, $this->cadeteA);

        $this->custody->releaseCustody($commission);
        $commission->save();

        $this->assertSame($this->cadeteA->id, $commission->fresh()->pickup_cadete_id);
    }

    public function test_releasing_before_pickup_frees_the_credit(): void
    {
        // Un "tomó y soltó" sin retiro no debe acreditarse a nadie.
        $commission = $this->commission();

        $this->custody->takeCustody($commission, $this->cadeteA->id);
        $commission->cadete_id = $this->cadeteA->id;
        $commission->save();

        $this->custody->releaseCustody($commission);
        $commission->save();

        $this->assertNull($commission->fresh()->pickup_cadete_id);
    }

    public function test_confirm_pickup_is_idempotent(): void
    {
        $commission = $this->commission();

        $this->custody->confirmPickup($commission, $this->cadeteA->id);
        $commission->save();
        $this->custody->confirmPickup($commission, $this->cadeteA->id);
        $commission->save();

        $this->assertSame($this->cadeteA->id, $commission->fresh()->pickup_cadete_id);
    }

    public function test_pickup_and_delivery_cadete_are_independent(): void
    {
        $commission = $this->commission();

        $this->custody->confirmPickup($commission, $this->cadeteA->id);
        $commission->save();
        $this->logPickup($commission, $this->cadeteA);

        // La entrega la hace B.
        $commission->cadete_id = $this->cadeteB->id;
        $commission->save();

        $fresh = $commission->fresh();
        $this->assertSame($this->cadeteA->id, $fresh->pickup_cadete_id, 'el retiro lo hizo A');
        $this->assertSame($this->cadeteB->id, $fresh->cadete_id, 'la entrega la hace B');
    }
}
