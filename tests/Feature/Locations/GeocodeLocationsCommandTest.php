<?php

namespace Tests\Feature\Locations;

use App\Shared\Enums\UserRole;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use App\Services\NominatimService;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * RC-549: `php artisan locations:geocode` completa las coordenadas que faltan (9.224 de 13.245
 * locaciones en la copia de prod del 23/09) respetando la política de Nominatim.
 */
class GeocodeLocationsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
        Http::preventStrayRequests();
    }

    private function withoutCoordinates(string $address, string $origin = 'ROSARIO'): Location
    {
        return Location::factory()->create(['address' => $address, 'origin' => $origin, 'latitude' => null, 'longitude' => null]);
    }

    private function fakeFound(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([['lat' => '-32.9468', 'lon' => '-60.6393']])]);
    }

    public function test_dry_run_no_llama_ni_escribe(): void
    {
        Http::fake();
        $pending = [$this->withoutCoordinates('SAN JUAN 1768'), $this->withoutCoordinates('RIVADAVIA 676', 'SAN GENARO')];
        Location::factory()->create(['latitude' => -32.36, 'longitude' => -61.36]);

        $this->artisan('locations:geocode', ['--dry-run' => true])
            ->expectsOutputToContain('Locaciones sin coordenadas: 2.')
            ->expectsOutputToContain('No se llamó a Nominatim ni se escribió nada')
            ->assertExitCode(0);

        Http::assertNothingSent();
        foreach ($pending as $location) {
            $this->assertNull($location->fresh()->latitude);
        }
    }

    public function test_limit_procesa_solo_esa_cantidad(): void
    {
        $this->fakeFound();
        foreach (['SAN JUAN 1768', 'SANTA FE 624', 'SARMIENTO 910', 'MITRE 455', 'CORDOBA 1200'] as $address) {
            $this->withoutCoordinates($address);
        }

        $this->artisan('locations:geocode', ['--limit' => 2])
            ->expectsOutputToContain('Consultadas 2: 2 con coordenadas, 0 sin resultado. Guardadas desde cache: 0. Salteadas por sin resultado conocido: 0. Requests a Nominatim: 2.')
            ->assertExitCode(0);

        Http::assertSentCount(2);
        $this->assertSame(2, Location::whereNotNull('latitude')->count());
        $this->assertSame(3, Location::whereNull('latitude')->count());
    }

    /**
     * 1 request por segundo: entre llamadas reales el comando espera (Sleep).
     */
    public function test_respeta_un_request_por_segundo(): void
    {
        $this->fakeFound();
        foreach (['SAN JUAN 1768', 'SANTA FE 624', 'SARMIENTO 910'] as $address) {
            $this->withoutCoordinates($address);
        }

        $this->artisan('locations:geocode')->assertExitCode(0);

        Http::assertSentCount(3);
        Sleep::assertSleptTimes(2);
        Http::assertSent(fn (Request $r) => str_contains($r->header('User-Agent')[0], 'geocoding'));
    }

    /**
     * Primero van las locaciones de las comisiones más recientes: son las que ven los cadetes.
     */
    public function test_prioriza_las_locaciones_de_las_comisiones_mas_recientes(): void
    {
        $this->fakeFound();
        $sinUso = $this->withoutCoordinates('SAN JUAN 1768');
        $vieja = $this->withoutCoordinates('SANTA FE 624');
        $reciente = $this->withoutCoordinates('SARMIENTO 910');
        $this->commissionTo($vieja);
        $this->commissionTo($reciente);

        $this->artisan('locations:geocode', ['--limit' => 1])->assertExitCode(0);

        $this->assertNotNull($reciente->fresh()->latitude);
        $this->assertNull($vieja->fresh()->latitude);
        $this->assertNull($sinUso->fresh()->latitude);
    }

    /**
     * Con un 429 se corta en el acto (el servicio pausa 1 h) y devuelve error para que se
     * note; las locaciones que faltan quedan para la próxima corrida.
     */
    public function test_se_corta_con_el_429(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response('<html>429</html>', 429)]);
        foreach (['SAN JUAN 1768', 'SANTA FE 624', 'SARMIENTO 910', 'MITRE 455'] as $address) {
            $this->withoutCoordinates($address);
        }

        $this->artisan('locations:geocode')
            ->expectsOutputToContain('Consultadas 0')
            ->expectsOutputToContain('(respondió HTTP 429). Se cortó el proceso')
            ->assertExitCode(1);

        Http::assertSentCount(1);
        $this->assertSame(4, Location::whereNull('latitude')->count());
    }

    public function test_con_nominatim_en_pausa_ni_arranca(): void
    {
        $this->freezeTime();
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response('<html>429</html>', 429)]);
        app(\App\Services\NominatimService::class)->getCoordinates('MITRE 455', 'ROSARIO');
        $this->withoutCoordinates('SAN JUAN 1768');

        $this->artisan('locations:geocode')
            ->expectsOutputToContain('Nominatim está en pausa hasta las ' . now()->addHour()->format('H:i') . ' (respondió HTTP 429). Volvé a correrlo después.')
            ->assertExitCode(1);

        Http::assertSentCount(1);
    }

    /**
     * La pausa también puede venir de un timeout o un 5xx (5 minutos, no 1 hora). Antes el
     * comando decía siempre "respondió 429"; ahora informa el motivo real.
     */
    public function test_la_pausa_por_timeout_no_se_informa_como_429(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            throw new ConnectionException('cURL error 28: Operation timed out after 5001 milliseconds');
        });
        foreach (['SAN JUAN 1768', 'SANTA FE 624'] as $address) {
            $this->withoutCoordinates($address);
        }

        $this->artisan('locations:geocode')
            ->expectsOutputToContain('(no respondió). Se cortó el proceso')
            ->doesntExpectOutputToContain('429')
            ->assertExitCode(1);

        // Http::assertSentCount no ve los requests que terminan en excepción: se cuentan a mano.
        $this->assertSame(1, $calls);
    }

    /**
     * Direcciones repetidas (muchas locaciones comparten dirección) salen una sola vez a la red:
     * la primera se consulta y las otras se guardan desde la cache.
     */
    public function test_direcciones_repetidas_se_consultan_una_vez(): void
    {
        $this->fakeFound();
        foreach (range(1, 3) as $i) {
            $this->withoutCoordinates('SAN JUAN 1768');
        }

        $this->artisan('locations:geocode')
            ->expectsOutputToContain('Consultadas 1: 1 con coordenadas, 0 sin resultado. Guardadas desde cache: 2.')
            ->assertExitCode(0);

        Http::assertSentCount(1);
        $this->assertSame(0, Location::whereNull('latitude')->count());
    }

    /**
     * Las que ya tienen resultado en cache se guardan sin llamar y no gastan --limit: con
     * --limit=1 se completan las dos de la cache y además se consulta una nueva.
     */
    public function test_las_que_estan_en_cache_no_gastan_el_limit(): void
    {
        $this->fakeFound();
        app(NominatimService::class)->getCoordinates('SAN JUAN 1768', 'ROSARIO');
        // Sin comisiones el orden es por id descendente: la nueva queda última.
        $nueva = $this->withoutCoordinates('SARMIENTO 910');
        $this->withoutCoordinates('SAN JUAN 1768');
        $this->withoutCoordinates('SAN JUAN 1768');

        $this->artisan('locations:geocode', ['--limit' => 1])
            ->expectsOutputToContain('Consultadas 1: 1 con coordenadas, 0 sin resultado. Guardadas desde cache: 2.')
            ->assertExitCode(0);

        Http::assertSentCount(2);
        $this->assertNotNull($nueva->fresh()->latitude);
        $this->assertSame(0, Location::whereNull('latitude')->count());
    }

    /**
     * Sin resultado: se cuenta y no se vuelve a preguntar en la próxima corrida (cache 7 días);
     * en esa corrida figura como salteada, no como consultada.
     */
    public function test_sin_resultado_no_se_repregunta_en_la_siguiente_corrida(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([])]);
        $this->withoutCoordinates('PEDRO GARCIA 2450 fincas El Rosedal');

        $this->artisan('locations:geocode')->expectsOutputToContain('Consultadas 1: 0 con coordenadas, 1 sin resultado')->assertExitCode(0);
        $this->artisan('locations:geocode')
            ->expectsOutputToContain('Consultadas 0: 0 con coordenadas, 0 sin resultado. Guardadas desde cache: 0. Salteadas por sin resultado conocido: 1. Requests a Nominatim: 0.')
            ->assertExitCode(0);

        Http::assertSentCount(1);
    }

    /**
     * Caso de la revisión: las locaciones sin resultado quedaban primeras en el orden y gastaban
     * --limit en cada corrida sin llamar a nadie (tres corridas de --limit=2: 2 requests en total
     * y 0 de 3 completadas). En prod ~97% de las respuestas con datos vienen vacías (20% contra
     * 0,5% con coordenadas), así que "--limit=300 y después más tandas" se trababa por 7 días.
     * Ahora las conocidas sin resultado se saltean sin contar y cada corrida avanza.
     */
    public function test_corridas_sucesivas_con_limit_avanzan_mas_alla_de_las_sin_resultado(): void
    {
        Http::fake(fn (Request $r) => str_contains($r['q'], 'PEDRO GARCIA') || str_contains($r['q'], 'LOTE 14')
            ? Http::response([])
            : Http::response([['lat' => '-32.9468', 'lon' => '-60.6393']]));
        // Sin comisiones el orden es por id descendente: las dos sin resultado quedan primeras.
        foreach (['SAN JUAN 1768', 'SANTA FE 624', 'SARMIENTO 910', 'PEDRO GARCIA 2450', 'LOTE 14 FINCAS'] as $address) {
            $this->withoutCoordinates($address);
        }

        $this->artisan('locations:geocode', ['--limit' => 2])
            ->expectsOutputToContain('Consultadas 2: 0 con coordenadas, 2 sin resultado. Guardadas desde cache: 0. Salteadas por sin resultado conocido: 0. Requests a Nominatim: 2.')
            ->assertExitCode(0);
        $this->artisan('locations:geocode', ['--limit' => 2])
            ->expectsOutputToContain('Consultadas 2: 2 con coordenadas, 0 sin resultado. Guardadas desde cache: 0. Salteadas por sin resultado conocido: 2. Requests a Nominatim: 2.')
            ->assertExitCode(0);
        $this->artisan('locations:geocode', ['--limit' => 2])
            ->expectsOutputToContain('Consultadas 1: 1 con coordenadas, 0 sin resultado. Guardadas desde cache: 0. Salteadas por sin resultado conocido: 2. Requests a Nominatim: 1.')
            ->assertExitCode(0);

        Http::assertSentCount(5);
        $this->assertSame(3, Location::whereNotNull('latitude')->count());
        $this->assertSame(2, Location::whereNull('latitude')->count());
    }

    /**
     * El --dry-run muestra qué haría con cada una: consultar, guardar desde cache o saltear por
     * sin resultado conocido (éstas, listadas con -v). No llama ni escribe.
     */
    public function test_dry_run_muestra_la_accion_de_cada_locacion(): void
    {
        Http::fake(fn (Request $r) => str_contains($r['q'], 'PEDRO GARCIA')
            ? Http::response([])
            : Http::response([['lat' => '-32.9468', 'lon' => '-60.6393']]));
        $nominatim = app(NominatimService::class);
        $nominatim->getCoordinates('SAN JUAN 1768', 'ROSARIO');       // queda en la cache de éxitos
        $nominatim->getCoordinates('PEDRO GARCIA 2450', 'ROSARIO');   // queda como sin resultado
        // Orden por id descendente: sin resultado conocido, en cache, y la nueva al final.
        $this->withoutCoordinates('SARMIENTO 910');
        $this->withoutCoordinates('SAN JUAN 1768');
        $this->withoutCoordinates('PEDRO GARCIA 2450');

        $this->artisan('locations:geocode', ['--dry-run' => true, '--limit' => 1])
            ->expectsOutputToContain('consultar a Nominatim')
            ->expectsOutputToContain('guardar desde cache')
            ->doesntExpectOutputToContain('saltear: sin resultado conocido')
            ->expectsOutputToContain('se consultarían 1 y se guardarían 1 desde cache')
            ->expectsOutputToContain('Se saltearían 1 con dirección sin resultado conocido (Nominatim ya respondió vacío; se reintentan cuando vence la cache de 7 días). Con -v se listan.')
            ->assertExitCode(0);

        $this->artisan('locations:geocode', ['--dry-run' => true, '-v' => true])
            ->expectsOutputToContain('saltear: sin resultado conocido')
            ->assertExitCode(0);

        Http::assertSentCount(2);
        $this->assertSame(3, Location::whereNull('latitude')->count());
    }

    public function test_no_toca_las_que_ya_tienen_coordenadas(): void
    {
        Http::fake();
        $location = Location::factory()->create(['latitude' => -32.36, 'longitude' => -61.36]);

        $this->artisan('locations:geocode')
            ->expectsOutputToContain('Locaciones sin coordenadas: 0.')
            ->assertExitCode(0);

        Http::assertNothingSent();
        $this->assertEquals(-32.36, (float) $location->fresh()->latitude);
    }

    public function test_limit_invalido(): void
    {
        $this->artisan('locations:geocode', ['--limit' => 'muchas'])->assertExitCode(2);
    }

    private function commissionTo(Location $destination): Commission
    {
        $branch = Branch::factory()->create();

        return Commission::factory()->create([
            'client_id' => Customer::factory()->create()->id,
            'branch_id' => $branch->id,
            'destination_id' => Destination::factory()->create()->id,
            'user_id' => User::factory()->create(['role' => UserRole::ADMINISTRADOR, 'branch_id' => $branch->id])->id,
            'origin_location_id' => Location::factory()->create(['latitude' => -32.36, 'longitude' => -61.36])->id,
            'destination_location_id' => $destination->id,
        ]);
    }
}
