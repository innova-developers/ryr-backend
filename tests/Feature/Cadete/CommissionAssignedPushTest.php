<?php

namespace Tests\Feature\Cadete;

use App\Services\FcmNotificationService;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Tests\TestCase;

/**
 * RC-553. La asignación desde el mostrador sólo quedaba en la base, sin push. En Android el
 * cadete se enteraba porque la app consultaba el contador cada 60 s aunque estuviera en
 * segundo plano; en iPhone, nunca. En septiembre hubo 163 asignaciones y sólo 4 tuvieron
 * un push cerca. La app nueva deja de consultar en segundo plano, así que el aviso pasa a
 * ser un push real.
 */
class CommissionAssignedPushTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, array{user: int, payload: array}> */
    private array $pushes = [];

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();

        $this->mock(FcmNotificationService::class, function ($mock) {
            $mock->shouldReceive('sendPushToUser')->andReturnUsing(function (int $userId, array $payload) {
                $this->pushes[] = ['user' => $userId, 'payload' => $payload];

                return ['success' => true, 'message' => '', 'sent' => 1, 'failed' => 0, 'invalid_tokens' => []];
            });
            $mock->shouldReceive('sendPushToAllCadetes')->andReturn(['total_users' => 0, 'successful' => 0, 'failed' => 0, 'details' => []]);
            $mock->shouldReceive('sendPushToUsers')->andReturn(['total_users' => 0, 'successful' => 0, 'failed' => 0, 'details' => []]);
        });
    }

    private function cadete(): User
    {
        return User::factory()->create(['role' => 'cadete', 'branch_id' => $this->branch->id]);
    }

    private function comision(?int $cadeteId = null): Commission
    {
        return Commission::factory()->create([
            'client_id' => Customer::factory()->create()->id,
            'destination_id' => Destination::factory()->create()->id,
            'branch_id' => $this->branch->id,
            'cadete_id' => $cadeteId,
            'status' => CommissionStatus::BUSCANDO_CADETE->value,
            'origin_location_id' => Location::factory()->create()->id,
            'destination_location_id' => Location::factory()->create()->id,
        ]);
    }

    private function pushesDeAsignacion(): array
    {
        return array_values(array_filter($this->pushes, fn ($p) => ($p['payload']['data']['type'] ?? null) === 'commission_assigned'));
    }

    public function test_el_mostrador_asigna_y_el_cadete_recibe_el_push(): void
    {
        $cadete = $this->cadete();
        $comision = $this->comision();
        $mostrador = User::factory()->create(['role' => 'mostrador', 'branch_id' => $this->branch->id]);

        $this->actingAs($mostrador, 'sanctum')
            ->postJson("/api/commissions/{$comision->id}/assign-cadete", ['cadete_id' => $cadete->id])
            ->assertSuccessful();

        // Que salga después de responder lo garantiza EnviosExternos (ver
        // EnviosExternosDespuesDeResponderTest); en el test el request simulado ya ejecuta
        // los envíos diferidos al terminar.
        app(DeferredCallbackCollection::class)->invoke();

        $pushes = $this->pushesDeAsignacion();
        $this->assertCount(1, $pushes, 'tiene que salir un solo push de asignación');
        $this->assertSame($cadete->id, $pushes[0]['user']);
        $this->assertSame('Nueva comisión asignada', $pushes[0]['payload']['title']);
        $this->assertStringContainsString("#{$comision->id}", $pushes[0]['payload']['body']);
        $this->assertSame($comision->id, $pushes[0]['payload']['data']['commission_id']);
    }

    public function test_la_notificacion_de_la_base_sigue_creandose_igual(): void
    {
        $cadete = $this->cadete();
        $comision = $this->comision();
        $admin = User::factory()->create(['role' => 'administrador', 'branch_id' => $this->branch->id]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/commissions/{$comision->id}/assign-cadete", ['cadete_id' => $cadete->id])
            ->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $cadete->id,
            'commission_id' => $comision->id,
            'type' => 'commission_assigned',
        ]);
    }

    public function test_cambiar_de_cadete_le_avisa_al_nuevo(): void
    {
        $viejo = $this->cadete();
        $nuevo = $this->cadete();
        $comision = $this->comision($viejo->id);
        $admin = User::factory()->create(['role' => 'administrador', 'branch_id' => $this->branch->id]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/commissions/{$comision->id}/change-cadete", ['cadete_id' => $nuevo->id])
            ->assertSuccessful();

        app(DeferredCallbackCollection::class)->invoke();

        $pushes = $this->pushesDeAsignacion();
        $this->assertCount(1, $pushes);
        $this->assertSame($nuevo->id, $pushes[0]['user']);
    }

    public function test_si_el_cadete_se_la_asigna_el_mismo_desde_el_pool_no_le_llega_push(): void
    {
        $cadete = $this->cadete();
        $comision = $this->comision();

        $this->actingAs($cadete, 'sanctum')
            ->postJson("/api/cadete/commissions/{$comision->id}/self-assign")
            ->assertSuccessful();

        app(DeferredCallbackCollection::class)->invoke();

        $this->assertCount(0, $this->pushesDeAsignacion());
        $this->assertDatabaseHas('notifications', ['user_id' => $cadete->id, 'type' => 'commission_assigned']);
    }
}
