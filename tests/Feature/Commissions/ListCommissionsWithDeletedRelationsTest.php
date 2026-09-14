<?php

namespace Tests\Feature\Commissions;

use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\CommissionType;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /admin/sin-cotizar devolvía 500 en producción: "Error al traer las comisiones sin
 * cotizar". El listado pide las comisiones EXTRAORDINARIA en PENDIENTE_PAGO y el mapper
 * leía `$commission->destination->origin` sin null-safe.
 *
 * La relación aplica el scope de SoftDeletes, así que un destino dado de baja la vuelve
 * null aunque la fila siga en la tabla. Como el mapeo corre dentro de un map(), UNA sola
 * comisión así tiraba abajo el listado completo. En producción alcanzó con la comisión
 * #55274 (TOTORAS a LAS PAREJAS, destino borrado el 11/09/2026) para dejar sin pantalla
 * a las 15 comisiones pendientes de cotizar.
 */
class ListCommissionsWithDeletedRelationsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Branch $branch;

    private Customer $customer;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
            'role' => 'administrador',
        ]);
        $this->customer = Customer::factory()->create();
        $this->location = Location::factory()->create();
    }

    private function comision(Destination $destination): Commission
    {
        return Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $destination->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'type' => CommissionType::EXTRAORDINARIA->value,
            'status' => CommissionStatus::PENDIENTE_PAGO->value,
            'total' => 0,
            'origin_location_id' => $this->location->id,
            'destination_location_id' => $this->location->id,
        ]);
    }

    public function test_una_comision_con_el_destino_dado_de_baja_no_rompe_el_listado(): void
    {
        $vigente = Destination::factory()->create();
        $aBorrar = Destination::factory()->create();

        $sana = $this->comision($vigente);
        $rota = $this->comision($aBorrar);

        $aBorrar->delete();

        $response = $this->actingAs($this->user)
            ->getJson('/api/commissions?status=' . CommissionStatus::PENDIENTE_PAGO->value . '&type=' . CommissionType::EXTRAORDINARIA->value);

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($sana->id), 'la comisión sana desapareció del listado');
        $this->assertTrue($ids->contains($rota->id), 'la comisión del destino borrado tiene que seguir listándose');

        $fila = collect($response->json('data'))->firstWhere('id', $rota->id);
        $this->assertNull($fila['origin'], 'sin destino vigente, el origen viaja vacío');
        $this->assertNull($fila['destination'], 'sin destino vigente, el destino viaja vacío');
    }

    public function test_una_comision_sin_logs_no_rompe_el_listado(): void
    {
        // Mismo defecto por otra puerta: `logs->last()->user` encadenaba sobre null y el
        // `?? null` del final no lo cubría.
        $this->comision(Destination::factory()->create());

        $this->actingAs($this->user)
            ->getJson('/api/commissions')
            ->assertStatus(200);
    }
}
