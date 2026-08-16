<?php

namespace Tests\Feature;

use App\Shared\Enums\CurrentAccountStatus;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\CommissionItem;
use App\Shared\Models\CurrentAccount;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RC-1 (DIFERENCIA ENTRE PDF Y CTA CTE) y RC-498 (PDF - DETALLE DE COMISION).
 *
 * El PDF de Clientes.jsx armaba su propio libro mayor desde la tabla commissions más
 * los créditos, con el saldo arrancando en 0 y ordenando sólo por fecha. La pantalla
 * leía current_accounts. Dos fuentes, dos resultados: saldos distintos, signos
 * invertidos y distinta cantidad de movimientos (295 comisiones sin movimiento de
 * cuenta corriente y 298 con fecha distinta a la de su movimiento en producción).
 *
 * Este extracto es la fuente única que consumen las dos vistas.
 */
class CurrentAccountStatementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'administrador', 'branch_id' => null]);
        $this->customer = Customer::factory()->create();
    }

    private function movimiento(string $type, float $amount, string $date, ?string $reference = null): CurrentAccount
    {
        return CurrentAccount::factory()->create([
            'customer_id' => $this->customer->id,
            'type' => $type,
            'status' => CurrentAccountStatus::OK->value,
            'amount' => $amount,
            'balance' => 0,
            'reference' => $reference,
            'transaction_date' => $date,
        ]);
    }

    private function statement(array $params = []): array
    {
        return $this->actingAs($this->admin)
            ->getJson("/api/customers/{$this->customer->id}/current-account/statement?" . http_build_query($params))
            ->assertOk()
            ->json('data');
    }

    public function test_running_balance_is_cumulative_and_signed(): void
    {
        $this->movimiento('debit', 10000, '2026-08-01');
        $this->movimiento('credit', 4000, '2026-08-02');

        $d = $this->statement();

        $this->assertEquals(-10000, $d['movements'][0]['balance']);
        $this->assertEquals(-6000, $d['movements'][1]['balance']);
        $this->assertEquals(-6000, $d['closing_balance']);
    }

    public function test_debits_are_negative_and_credits_positive(): void
    {
        $this->movimiento('debit', 7500, '2026-08-01');
        $this->movimiento('credit', 7500, '2026-08-02');

        $d = $this->statement();

        $this->assertEquals(-7500, $d['movements'][0]['signed_amount']);
        $this->assertEquals(7500, $d['movements'][1]['signed_amount']);
    }

    public function test_date_range_starts_from_the_opening_balance(): void
    {
        // Este es el corazón de RC-1: con un rango de fechas, el saldo del extracto
        // tiene que seguir siendo absoluto, no reiniciarse en 0.
        $this->movimiento('debit', 10000, '2026-07-15');
        $this->movimiento('debit', 5000, '2026-08-03');

        $d = $this->statement(['date_from' => '2026-08-01', 'date_to' => '2026-08-31']);

        $this->assertEquals(-10000, $d['opening_balance']);
        $this->assertEquals(-15000, $d['movements'][0]['balance']);
        $this->assertEquals(-15000, $d['closing_balance']);
    }

    public function test_opening_balance_is_zero_without_date_from(): void
    {
        $this->movimiento('debit', 10000, '2026-07-15');

        $this->assertEquals(0, $this->statement()['opening_balance']);
    }

    public function test_same_day_movements_keep_a_stable_order(): void
    {
        $primero = $this->movimiento('debit', 1000, '2026-08-05');
        $segundo = $this->movimiento('credit', 300, '2026-08-05');

        $d = $this->statement();

        $this->assertSame($primero->id, $d['movements'][0]['id']);
        $this->assertSame($segundo->id, $d['movements'][1]['id']);
    }

    public function test_pending_movements_are_excluded(): void
    {
        $this->movimiento('debit', 10000, '2026-08-01');
        CurrentAccount::factory()->create([
            'customer_id' => $this->customer->id,
            'type' => 'credit',
            'status' => CurrentAccountStatus::PENDIENTE->value,
            'amount' => 9999,
            'balance' => 0,
            'transaction_date' => '2026-08-02',
        ]);

        $d = $this->statement();

        $this->assertSame(1, $d['movements_count']);
        $this->assertEquals(-10000, $d['closing_balance']);
    }

    public function test_movement_carries_commission_detail(): void
    {
        $branch = Branch::factory()->create();
        $destination = Destination::factory()->create();
        $commission = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $destination->id,
            'branch_id' => $branch->id,
            'user_id' => $this->admin->id,
            'total' => 8000,
            'date' => '2026-08-01',
        ]);
        CommissionItem::factory()->create([
            'commission_id' => $commission->id,
            'size' => 'CHICO',
            'quantity' => 3,
        ]);
        CommissionItem::factory()->create([
            'commission_id' => $commission->id,
            'size' => 'GRANDE',
            'quantity' => 2,
        ]);

        $this->movimiento('debit', 8000, '2026-08-01', "COM-{$commission->id}");

        $detalle = $this->statement()['movements'][0]['commission'];

        // RC-498: el PDF mostraba sólo la descripción del movimiento, sin el detalle
        // que el cliente necesita para identificar a qué corresponde el importe.
        $this->assertSame($commission->id, $detalle['id']);
        $this->assertSame(3, $detalle['bultos_chicos']);
        $this->assertSame(2, $detalle['bultos_grandes']);
    }

    public function test_movement_without_commission_has_null_detail(): void
    {
        $this->movimiento('credit', 5000, '2026-08-01', 'PAGO-123');

        $this->assertNull($this->statement()['movements'][0]['commission']);
    }

    public function test_totals_are_reported(): void
    {
        $this->movimiento('debit', 10000, '2026-08-01');
        $this->movimiento('debit', 5000, '2026-08-02');
        $this->movimiento('credit', 6000, '2026-08-03');

        $d = $this->statement();

        $this->assertEquals(15000, $d['total_debits']);
        $this->assertEquals(6000, $d['total_credits']);
        $this->assertSame(3, $d['movements_count']);
    }

    public function test_unknown_customer_returns_404(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/customers/999999/current-account/statement')
            ->assertStatus(404);
    }
}
