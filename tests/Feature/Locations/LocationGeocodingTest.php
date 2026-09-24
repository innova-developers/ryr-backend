<?php

namespace Tests\Feature\Locations;

use App\Jobs\GeocodeLocationJob;
use App\Services\NominatimService;
use App\Shared\Models\Location;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * RC-549: la locación se geocodifica al guardarse, pero DESPUÉS de responder.
 *
 * Antes el `saving` del modelo llamaba a Nominatim síncrono: el alta o edición de una locación
 * (admin, app de cadetes, alta de cliente) esperaba hasta 2 llamadas con timeout de 10 s, y en
 * prod el 99,5% fallaba igual. Medido sobre la copia de prod con 150 ms por llamada: el alta
 * esperaba +322 ms con 429; ahora responde sin llamar y la llamada corre en el terminate.
 */
class LocationGeocodingTest extends TestCase
{
    use RefreshDatabase;

    private array $payload = [
        'name' => 'DEPOSITO RYR',
        'address' => 'RIVADAVIA 676',
        'origin' => 'SAN GENARO',
        'phone' => '3401000000',
        'map' => null,
        'schedule' => '8 a 17',
        'observation' => null,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml la apaga para el resto de la suite.
        config(['services.nominatim.geocode_on_save' => true]);
        Sleep::fake();
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create(['role' => 'administrador']), 'sanctum');
    }

    private function fakeNominatimFound(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([['lat' => '-32.3667239', 'lon' => '-61.3608211']])]);
    }

    /**
     * El alta responde con la locación todavía sin coordenadas (no esperó a Nominatim) y,
     * terminado el request, la locación quedó geocodificada.
     */
    public function test_alta_responde_sin_esperar_a_nominatim_y_geocodifica_despues(): void
    {
        $this->fakeNominatimFound();

        $response = $this->postJson('/api/locations', $this->payload);

        $response->assertCreated();
        $this->assertNull($response->json('latitude'));
        $location = Location::find($response->json('id'));
        $this->assertEquals(-32.3667239, (float) $location->latitude);
        $this->assertEquals(-61.3608211, (float) $location->longitude);
        Http::assertSentCount(1);
    }

    public function test_alta_despacha_el_job_despues_de_la_respuesta(): void
    {
        Bus::fake();

        $response = $this->postJson('/api/locations', $this->payload);

        Bus::assertDispatchedAfterResponse(GeocodeLocationJob::class, fn (GeocodeLocationJob $job) => $job->locationId === $response->json('id'));
        Http::assertNothingSent();
    }

    /**
     * Con Nominatim limitando (el 78% de las llamadas en prod) el alta responde igual y la
     * locación queda sin coordenadas, con una sola llamada (antes: 2, completa y simplificada).
     */
    public function test_alta_con_nominatim_limitado_responde_igual(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response('<html>429</html>', 429)]);

        $response = $this->postJson('/api/locations', ['address' => 'BV OROÑO 1234', 'origin' => 'ROSARIO'] + $this->payload);

        $response->assertCreated();
        $this->assertNull(Location::find($response->json('id'))->latitude);
        Http::assertSentCount(1);
    }

    public function test_editar_la_direccion_vuelve_a_geocodificar(): void
    {
        $location = Location::factory()->create(['address' => 'SAN JUAN 1768', 'origin' => 'ROSARIO', 'latitude' => -32.9468, 'longitude' => -60.6393]);
        $this->fakeNominatimFound();

        $this->putJson("/api/locations/{$location->id}", $this->payload)->assertOk();

        $this->assertEquals(-32.3667239, (float) $location->fresh()->latitude);
        Http::assertSent(fn ($request) => $request['q'] === 'RIVADAVIA 676, SAN GENARO, Argentina');
    }

    /**
     * Editar teléfono u horario no toca Nominatim (igual que antes: sólo address/origin).
     */
    public function test_editar_sin_cambiar_direccion_no_geocodifica(): void
    {
        $location = Location::factory()->create(['address' => 'RIVADAVIA 676', 'origin' => 'SAN GENARO', 'latitude' => -32.36, 'longitude' => -61.36]);
        Bus::fake();

        $this->putJson("/api/locations/{$location->id}", ['phone' => '3401999999', 'schedule' => '9 a 13'] + $this->payload)->assertOk();

        $this->assertSame('3401999999', $location->fresh()->phone);
        Bus::assertNotDispatchedAfterResponse(GeocodeLocationJob::class);
        Http::assertNothingSent();
    }

    /**
     * Si quien guarda ya trae las coordenadas (seeders, backfill), no se pisan.
     */
    public function test_si_se_guarda_con_coordenadas_no_se_geocodifica(): void
    {
        Bus::fake();

        Location::factory()->create(['address' => 'RIVADAVIA 676', 'origin' => 'SAN GENARO', 'latitude' => -32.36, 'longitude' => -61.36]);

        Bus::assertNotDispatchedAfterResponse(GeocodeLocationJob::class);
    }

    public function test_con_la_geocodificacion_apagada_no_se_despacha(): void
    {
        config(['services.nominatim.geocode_on_save' => false]);
        Bus::fake();

        Location::factory()->create(['address' => 'RIVADAVIA 676', 'origin' => 'SAN GENARO']);

        Bus::assertNotDispatchedAfterResponse(GeocodeLocationJob::class);
    }

    /**
     * Con el circuito abierto por un 429 anterior, el job no llama ni loguea.
     */
    public function test_job_con_nominatim_en_pausa_no_llama_ni_loguea(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response('<html>429</html>', 429)]);
        app(NominatimService::class)->getCoordinates('SAN JUAN 1768', 'ROSARIO');
        Http::assertSentCount(1);
        $location = Location::factory()->create(['address' => 'RIVADAVIA 676', 'origin' => 'SAN GENARO']);
        Log::spy();

        GeocodeLocationJob::dispatchSync($location->id);

        Http::assertSentCount(1);
        $this->assertNull($location->fresh()->latitude);
        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('error');
    }

    /**
     * La locación se borró antes de que corra el job (p. ej. rollback del alta de cliente).
     */
    public function test_job_de_locacion_inexistente_no_hace_nada(): void
    {
        Http::fake();

        GeocodeLocationJob::dispatchSync(999999);

        Http::assertNothingSent();
    }

    /**
     * El job guarda en silencio: no vuelve a disparar el hook ni cambia otros campos.
     */
    public function test_job_guarda_solo_las_coordenadas(): void
    {
        $this->fakeNominatimFound();
        $location = Location::factory()->create(['address' => 'RIVADAVIA 676', 'origin' => 'SAN GENARO', 'phone' => '3401000000']);
        Bus::fake();

        (new GeocodeLocationJob($location->id))->handle(app(NominatimService::class));

        $fresh = $location->fresh();
        $this->assertEquals(-32.3667239, (float) $fresh->latitude);
        $this->assertSame('3401000000', $fresh->phone);
        Bus::assertNotDispatchedAfterResponse(GeocodeLocationJob::class);
    }
}
