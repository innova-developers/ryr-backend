<?php

namespace Tests\Feature;

use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Branch;
use App\Shared\Models\BranchLocality;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RC-483 (SUCURSALES).
 *
 * La propiedad administrativa de la comisión es de la sucursal que la cargó y no se
 * mueve. Lo que faltaba era poder derivar la EJECUCIÓN: el pool de cadetes filtraba
 * estricto por la sucursal del cadete, así que un cadete de otra localidad no podía
 * tomar el trabajo aunque el retiro fuera en su zona.
 *
 * Ahora cada sucursal declara qué localidades atiende. Sin localidades configuradas
 * el comportamiento es el de siempre.
 */
class BranchLocalityPoolTest extends TestCase
{
    use RefreshDatabase;

    private Branch $sanGenaro;
    private Branch $rosario;
    private Customer $customer;
    private Destination $destination;
    private User $cadeteRosario;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanGenaro = Branch::factory()->create(['name' => 'San Genaro']);
        $this->rosario = Branch::factory()->create(['name' => 'Rosario']);
        $this->customer = Customer::factory()->create();
        $this->destination = Destination::factory()->create();
        $this->cadeteRosario = User::factory()->create(['role' => 'cadete', 'branch_id' => $this->rosario->id]);
        $this->admin = User::factory()->create(['role' => 'administrador', 'branch_id' => null]);
    }

    private function comision(Branch $owner, string $origen, string $destino): Commission
    {
        return Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'branch_id' => $owner->id,
            'user_id' => $this->admin->id,
            'cadete_id' => null,
            'status' => CommissionStatus::BUSCANDO_CADETE->value,
            'origin_location_id' => Location::factory()->create(['origin' => $origen])->id,
            'destination_location_id' => Location::factory()->create(['origin' => $destino])->id,
        ]);
    }

    private function pool(): array
    {
        return $this->actingAs($this->cadeteRosario)
            ->getJson('/api/cadete/commissions/available')
            ->assertOk()
            ->json('data');
    }

    public function test_without_localities_branch_sees_only_its_own(): void
    {
        $this->comision($this->sanGenaro, 'ROSARIO', 'SAN GENARO');
        $propia = $this->comision($this->rosario, 'ROSARIO', 'DIAZ');

        $ids = collect($this->pool())->pluck('id');

        $this->assertCount(1, $ids);
        $this->assertContains($propia->id, $ids);
    }

    public function test_commission_from_another_branch_appears_when_origin_matches(): void
    {
        BranchLocality::create(['branch_id' => $this->rosario->id, 'locality' => 'ROSARIO']);
        $ajena = $this->comision($this->sanGenaro, 'ROSARIO', 'SAN GENARO');

        $this->assertContains($ajena->id, collect($this->pool())->pluck('id'));
    }

    public function test_commission_from_another_branch_appears_when_destination_matches(): void
    {
        BranchLocality::create(['branch_id' => $this->rosario->id, 'locality' => 'ROSARIO']);
        $ajena = $this->comision($this->sanGenaro, 'SAN GENARO', 'ROSARIO');

        $this->assertContains($ajena->id, collect($this->pool())->pluck('id'));
    }

    public function test_unrelated_locality_stays_out_of_the_pool(): void
    {
        BranchLocality::create(['branch_id' => $this->rosario->id, 'locality' => 'ROSARIO']);
        $this->comision($this->sanGenaro, 'RAFAELA', 'DIAZ');

        $this->assertCount(0, $this->pool());
    }

    public function test_locality_matching_ignores_case_and_spaces(): void
    {
        BranchLocality::create(['branch_id' => $this->rosario->id, 'locality' => 'rosario']);
        $ajena = $this->comision($this->sanGenaro, '  Rosario ', 'SAN GENARO');

        $this->assertContains($ajena->id, collect($this->pool())->pluck('id'));
    }

    public function test_taking_a_derived_commission_does_not_change_its_owner(): void
    {
        // El punto central de la card: la sucursal de origen conserva la propiedad.
        BranchLocality::create(['branch_id' => $this->rosario->id, 'locality' => 'ROSARIO']);
        $ajena = $this->comision($this->sanGenaro, 'ROSARIO', 'SAN GENARO');

        $this->actingAs($this->cadeteRosario)
            ->postJson("/api/cadete/commissions/{$ajena->id}/self-assign")
            ->assertSuccessful();

        $this->assertSame($this->sanGenaro->id, $ajena->fresh()->branch_id);
        $this->assertSame($this->cadeteRosario->id, $ajena->fresh()->cadete_id);
    }

    // --- ABM de localidades ---

    public function test_admin_can_set_branch_localities(): void
    {
        $this->actingAs($this->admin)
            ->putJson("/api/admin/branches/{$this->rosario->id}/localities", [
                'localities' => ['rosario', ' Funes ', 'ROSARIO'],
            ])
            ->assertOk();

        $guardadas = BranchLocality::where('branch_id', $this->rosario->id)->pluck('locality')->sort()->values();

        // Normaliza y deduplica.
        $this->assertSame(['FUNES', 'ROSARIO'], $guardadas->all());
    }

    public function test_sync_replaces_previous_localities(): void
    {
        BranchLocality::create(['branch_id' => $this->rosario->id, 'locality' => 'VIEJA']);

        $this->actingAs($this->admin)
            ->putJson("/api/admin/branches/{$this->rosario->id}/localities", ['localities' => ['NUEVA']])
            ->assertOk();

        $this->assertSame(['NUEVA'], BranchLocality::where('branch_id', $this->rosario->id)->pluck('locality')->all());
    }

    public function test_empty_list_clears_localities(): void
    {
        BranchLocality::create(['branch_id' => $this->rosario->id, 'locality' => 'ROSARIO']);

        $this->actingAs($this->admin)
            ->putJson("/api/admin/branches/{$this->rosario->id}/localities", ['localities' => []])
            ->assertOk();

        $this->assertSame(0, BranchLocality::where('branch_id', $this->rosario->id)->count());
    }

    public function test_unknown_branch_returns_404(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/admin/branches/999999/localities')
            ->assertStatus(404);
    }
}
