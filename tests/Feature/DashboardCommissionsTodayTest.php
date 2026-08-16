<?php

namespace Tests\Feature;

use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RC-489 (INICIO - CANTIDAD DE COMISIONES).
 *
 * El Inicio mostraba el acumulado del mes porque el front arrancaba con el rango
 * de mes completo. Cambiar sólo el rango no alcanzaba: el filtro de estado por
 * defecto es PAGO_CONFIRMADO (puesto en sprint 8 para cuadrar con el Balance) y
 * una comisión tomada hoy todavía no está confirmada, así que habría mostrado 0.
 *
 * Por eso "tomadas hoy" es un indicador aparte, independiente de los filtros.
 */
class DashboardCommissionsTodayTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Branch $branch;
    private Customer $client;
    private Destination $destination;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::factory()->create();
        $this->client = Customer::factory()->create();
        $this->destination = Destination::factory()->create();
        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'branch_id' => null,
        ]);
    }

    private function commission(array $attrs = []): Commission
    {
        return Commission::factory()->create($attrs + [
            'client_id' => $this->client->id,
            'destination_id' => $this->destination->id,
            'user_id' => $this->admin->id,
            'branch_id' => $this->branch->id,
            'status' => CommissionStatus::BUSCANDO_CADETE->value,
        ]);
    }

    public function test_counts_commissions_dated_today(): void
    {
        $this->commission(['date' => now()->toDateString()]);
        $this->commission(['date' => now()->toDateString()]);
        $this->commission(['date' => now()->subDay()->toDateString()]);

        $this->actingAs($this->admin)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('commissions_today', 2);
    }

    public function test_today_counter_ignores_payment_status_filter(): void
    {
        // Estados que el filtro por defecto del Inicio excluye; igual deben contarse.
        $this->commission(['date' => now()->toDateString(), 'status' => CommissionStatus::PENDIENTE_PAGO->value]);
        $this->commission(['date' => now()->toDateString(), 'status' => CommissionStatus::PAGO_CONFIRMADO->value]);
        $this->commission(['date' => now()->toDateString(), 'status' => CommissionStatus::ENTREGADO->value]);

        $this->actingAs($this->admin)
            ->getJson('/api/dashboard/stats?statuses=PAGO_CONFIRMADO')
            ->assertOk()
            ->assertJsonPath('commissions_today', 3);
    }

    public function test_today_counter_ignores_date_range_filter(): void
    {
        $this->commission(['date' => now()->toDateString()]);

        // Aunque se pida un rango del mes pasado, el contador de hoy no cambia.
        $this->actingAs($this->admin)
            ->getJson('/api/dashboard/stats?date_from=' . now()->subMonth()->startOfMonth()->toDateString()
                . '&date_to=' . now()->subMonth()->endOfMonth()->toDateString())
            ->assertOk()
            ->assertJsonPath('commissions_today', 1);
    }

    public function test_today_counter_excludes_cancelled(): void
    {
        $this->commission(['date' => now()->toDateString()]);
        $this->commission(['date' => now()->toDateString(), 'status' => CommissionStatus::CANCELADO->value]);

        $this->actingAs($this->admin)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('commissions_today', 1);
    }

    public function test_today_counter_respects_user_branch(): void
    {
        $otherBranch = Branch::factory()->create();
        $this->commission(['date' => now()->toDateString()]);
        $this->commission(['date' => now()->toDateString(), 'branch_id' => $otherBranch->id]);

        $scoped = User::factory()->create([
            'role' => 'administrador',
            'branch_id' => $this->branch->id,
        ]);

        $this->actingAs($scoped)
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('commissions_today', 1);
    }
}
