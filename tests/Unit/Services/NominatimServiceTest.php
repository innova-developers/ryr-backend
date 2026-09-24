<?php

namespace Tests\Unit\Services;

use App\Services\NominatimService;
use Carbon\CarbonInterval;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * RC-549: NominatimService cachea también los fracasos, corta todo con un 429 y respeta la
 * política de Nominatim (1 req/s, User-Agent propio).
 *
 * En prod se hacían ~12.000 llamadas por día hábil, 99,5% fallidas (78% HTTP 429, 20% sin
 * resultados), y cada 429 dejaba en laravel.log el HTML entero de la página de error.
 * Nunca se sale a la red: Http::preventStrayRequests() más Http::fake().
 */
class NominatimServiceTest extends TestCase
{
    /** Cuerpo real del 429 de Nominatim (Varnish), copiado de laravel.log. */
    private const HTML_429 = "\n<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\"\n \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">\n<html>\n  <head>\n    <title>429 Too many requests</title>\n  </head>\n  <body>\n    <h1>Error 429 Too many requests</h1>\n    <p>Too many requests</p>\n    <h3>Error 54113</h3>\n    <p>Details: cache-scl2220043-SCL 1790222710 1106038538</p>\n    <hr>\n    <p>Varnish cache server</p>\n  </body>\n</html>\n";

    private NominatimService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
        Http::preventStrayRequests();
        $this->service = new NominatimService();
    }

    /**
     * Un éxito se guarda 30 días: la misma dirección no vuelve a salir a la red.
     */
    public function test_exito_se_cachea_y_no_se_vuelve_a_llamar(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([['lat' => '-33.0305', 'lon' => '-61.0368']])]);

        $first = $this->service->getCoordinates('RIVADAVIA 676', 'SAN GENARO');
        $second = $this->service->getCoordinates('RIVADAVIA 676', 'SAN GENARO');

        $this->assertSame(['latitude' => -33.0305, 'longitude' => -61.0368], $first);
        $this->assertSame($first, $second);
        Http::assertSentCount(1);
    }

    /**
     * Caso real: "PEDRO GARCIA 2450 fincas El Rosedal" fue a Nominatim 1.259 veces en 2 días
     * porque el "sin resultados" no se cacheaba. Ahora se pregunta una vez cada 7 días.
     */
    public function test_direccion_sin_resultados_se_cachea_7_dias(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([])]);

        foreach (range(1, 50) as $refresco) {
            $this->assertNull($this->service->getCoordinates('PEDRO GARCIA 2450 fincas El Rosedal', 'ROSARIO'));
        }
        Http::assertSentCount(1);

        $this->travel(6)->days();
        $this->assertNull($this->service->getCoordinates('PEDRO GARCIA 2450 fincas El Rosedal', 'ROSARIO'));
        Http::assertSentCount(1);

        $this->travel(2)->days();
        $this->service->getCoordinates('PEDRO GARCIA 2450 fincas El Rosedal', 'ROSARIO');
        Http::assertSentCount(2);
    }

    /**
     * Con dirección completa y simplificada distintas se prueban las dos (como antes), y cada
     * una queda cacheada como "sin resultado" por separado.
     */
    public function test_sin_resultados_prueba_la_version_simplificada_una_sola_vez(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([])]);

        $this->service->getCoordinates('BV OROÑO 1234', 'ROSARIO');
        $this->service->getCoordinates('BV OROÑO 1234', 'ROSARIO');

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => $r['q'] === 'Boulevard OROÑO 1234, ROSARIO, Argentina');
        Http::assertSent(fn (Request $r) => $r['q'] === 'OROÑO 1234, ROSARIO, Argentina');
    }

    /**
     * El 429 abre el circuito 1 hora para TODAS las direcciones: ni siquiera se intenta la
     * versión simplificada (antes eran 2 llamadas por ubicación, las dos con 429).
     */
    public function test_429_pausa_una_hora_todas_las_llamadas(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response(self::HTML_429, 429, ['Content-Type' => 'text/html'])]);

        $this->assertNull($this->service->getCoordinates('BV OROÑO 1234', 'ROSARIO'));
        Http::assertSentCount(1);
        $this->assertTrue($this->service->isCircuitOpen());

        foreach (['SAN JUAN 1768', 'RIVADAVIA 676', 'SANTA FE 624'] as $address) {
            $this->assertNull($this->service->getCoordinates($address, 'ROSARIO'));
        }
        $this->travel(59)->minutes();
        $this->assertNull($this->service->getCoordinates('SAN LUIS 1525', 'SAN GENARO'));
        Http::assertSentCount(1);

        $this->travel(2)->minutes();
        $this->assertFalse($this->service->isCircuitOpen());
        $this->service->getCoordinates('SAN LUIS 1525', 'SAN GENARO');
        Http::assertSentCount(2);
    }

    /**
     * El 429 no se cachea como "sin resultado": pasada la pausa, esa dirección se vuelve a
     * intentar (no es que no exista).
     */
    public function test_429_no_marca_la_direccion_como_inexistente(): void
    {
        Http::fakeSequence('nominatim.openstreetmap.org/*')
            ->push(self::HTML_429, 429)
            ->push([['lat' => '-32.9468', 'lon' => '-60.6393']]);

        $this->assertNull($this->service->getCoordinates('SAN JUAN 1768', 'ROSARIO'));
        $this->travel(61)->minutes();

        $this->assertSame(
            ['latitude' => -32.9468, 'longitude' => -60.6393],
            $this->service->getCoordinates('SAN JUAN 1768', 'ROSARIO')
        );
    }

    /**
     * El log del 429 es una sola línea sin HTML, y durante la pausa no se loguea nada más
     * (antes: ~540 bytes de HTML por llamada; el geocoding era el 85% del log).
     */
    public function test_429_loguea_una_sola_linea_sin_html(): void
    {
        Log::spy();
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response(self::HTML_429, 429, ['Content-Type' => 'text/html'])]);

        foreach (range(1, 20) as $i) {
            $this->service->getCoordinates("SAN MARTIN {$i}00", 'SAN GENARO');
        }

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) {
            $logged = $message . json_encode($context);

            return str_contains($message, 'HTTP 429')
                && ! str_contains($logged, '<html')
                && ! str_contains($logged, 'Varnish')
                && $context['address'] === 'SAN MARTIN 100, SAN GENARO, Argentina'
                && $context['paused_minutes'] === 60
                && strlen($logged) < 300;
        });
        Log::shouldNotHaveReceived('error');
        Log::shouldNotHaveReceived('info');
    }

    /**
     * Timeout o caída: pausa corta de 5 minutos. Antes cada timeout eran 10 s de request
     * bloqueado (18 casos en septiembre) y se reintentaba en el siguiente.
     */
    public function test_timeout_pausa_5_minutos(): void
    {
        Log::spy();
        Http::fake(['nominatim.openstreetmap.org/*' => Http::failedConnection('cURL error 28: Operation timed out after 5001 milliseconds')]);

        $this->assertNull($this->service->getCoordinates('SAN JUAN 1768', 'ROSARIO'));
        $this->assertNull($this->service->getCoordinates('RIVADAVIA 676', 'SAN GENARO'));
        Http::assertSentCount(1);

        $this->travel(4)->minutes();
        $this->assertTrue($this->service->isCircuitOpen());

        $this->travel(2)->minutes();
        $this->assertFalse($this->service->isCircuitOpen());

        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context) => str_contains($message, 'no respondió') && $context['paused_minutes'] === 5
        );
    }

    /**
     * Un 5xx también pausa 5 minutos; un 400 no pausa (es un problema de esa consulta).
     */
    public function test_5xx_pausa_y_400_no(): void
    {
        Http::fakeSequence('nominatim.openstreetmap.org/*')
            ->push('Bad Request', 400)
            ->push('<html>Service Unavailable</html>', 503);

        $this->assertNull($this->service->getCoordinates('SAN JUAN 1768', 'ROSARIO'));
        $this->assertFalse($this->service->isCircuitOpen());

        $this->assertNull($this->service->getCoordinates('RIVADAVIA 676', 'SAN GENARO'));
        $this->assertTrue($this->service->isCircuitOpen());
        $this->assertSame(5, (int) round(now()->diffInMinutes($this->service->circuitOpenUntil())));
    }

    /**
     * Política de Nominatim: como máximo 1 request por segundo. Entre dos llamadas reales se
     * espera lo que falte para completar el segundo; lo que sale de la cache no espera.
     */
    public function test_respeta_un_request_por_segundo(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([['lat' => '-33.0305', 'lon' => '-61.0368']])]);

        $this->service->getCoordinates('RIVADAVIA 676', 'SAN GENARO');
        Sleep::assertNeverSlept();

        $this->service->getCoordinates('SAN JUAN 1768', 'ROSARIO');
        $this->service->getCoordinates('SANTA FE 624', 'SAN GENARO');
        $this->service->getCoordinates('RIVADAVIA 676', 'SAN GENARO'); // cache: no espera

        Http::assertSentCount(3);
        Sleep::assertSleptTimes(2);
        Sleep::assertSlept(
            fn (CarbonInterval $duration) => $duration->totalMilliseconds > 900 && $duration->totalMilliseconds <= 1000,
            2
        );
        $this->assertSame(3, $this->service->requestCount());
    }

    /**
     * User-Agent propio con contacto (la política no acepta UAs genéricos) y la consulta
     * restringida a Argentina, igual que antes.
     */
    public function test_usa_user_agent_propio_con_contacto(): void
    {
        config([
            'app.name' => 'RyR Comisiones',
            'app.url' => 'https://ryrcomisiones.com',
            'mail.from.address' => 'notificaciones@ryrcomisiones.com',
        ]);
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([['lat' => '-33.0305', 'lon' => '-61.0368']])]);

        $this->service->getCoordinates('RIVADAVIA 676', 'SAN GENARO');

        Http::assertSent(fn (Request $r) => $r->header('User-Agent') === ['RyRComisiones/1.0 geocoding (https://ryrcomisiones.com; notificaciones@ryrcomisiones.com)']
            && $r['q'] === 'RIVADAVIA 676, SAN GENARO, Argentina'
            && $r['countrycodes'] === 'ar'
            && $r['format'] === 'json');
    }

    public function test_user_agent_configurable(): void
    {
        config(['services.nominatim.user_agent' => 'RyR-Geocoder/2.0 (admin@ryrcomisiones.com)']);
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([])]);

        $this->service->getCoordinates('RIVADAVIA 676', 'SAN GENARO');

        Http::assertSent(fn (Request $r) => $r->header('User-Agent') === ['RyR-Geocoder/2.0 (admin@ryrcomisiones.com)']);
    }

    /**
     * getCachedCoordinates es lo que usa GET /cadete/deliveries: lee la cache de éxitos (con
     * la misma clave de antes, para no perder lo que ya hay en prod) y nunca sale a la red.
     */
    public function test_cached_coordinates_lee_la_cache_de_siempre_y_nunca_llama(): void
    {
        Http::fake();
        Cache::put('geocode_nominatim_' . md5('RIVADAVIA 676, SAN GENARO, Argentina'), ['latitude' => -33.0305, 'longitude' => -61.0368], now()->addDays(30));
        // Una dirección con prefijo que sólo se resolvió en su versión simplificada.
        Cache::put('geocode_nominatim_' . md5('OROÑO 1234, ROSARIO, Argentina'), ['latitude' => -32.95, 'longitude' => -60.65], now()->addDays(30));

        $this->assertSame(['latitude' => -33.0305, 'longitude' => -61.0368], $this->service->getCachedCoordinates('RIVADAVIA 676', 'SAN GENARO'));
        $this->assertSame(['latitude' => -32.95, 'longitude' => -60.65], $this->service->getCachedCoordinates('BV OROÑO 1234', 'ROSARIO'));
        $this->assertNull($this->service->getCachedCoordinates('SAN JUAN 1768', 'ROSARIO'));

        Http::assertNothingSent();
        $this->assertSame(0, $this->service->requestCount());
    }

    /**
     * isKnownMiss (lo usa el backfill para no gastar --limit en direcciones ya descartadas) es
     * true sólo si TODAS las variantes de la dirección están como "sin resultado". Con una
     * variante sin preguntar todavía, o un 429 en el medio, no lo es: esa dirección se intenta.
     */
    public function test_is_known_miss_solo_cuando_todas_las_variantes_ya_dieron_vacio(): void
    {
        Http::fakeSequence('nominatim.openstreetmap.org/*')
            ->push([])                // Boulevard OROÑO 1234: vacío
            ->push(self::HTML_429, 429) // OROÑO 1234: 429, no se sabe
            ->push([])                // PEDRO GARCIA 2450: vacío (una sola variante)
            ->push([])                // Boulevard OROÑO 1234 no se repite (cache); OROÑO 1234: vacío
            ->push([['lat' => '-32.9468', 'lon' => '-60.6393']]);

        $this->assertFalse($this->service->isKnownMiss('BV OROÑO 1234', 'ROSARIO'));
        $this->service->getCoordinates('BV OROÑO 1234', 'ROSARIO');
        $this->assertFalse($this->service->isKnownMiss('BV OROÑO 1234', 'ROSARIO'), 'la simplificada quedó cortada por el 429');

        $this->travel(61)->minutes();
        $this->service->getCoordinates('PEDRO GARCIA 2450', 'ROSARIO');
        $this->assertTrue($this->service->isKnownMiss('PEDRO GARCIA 2450', 'ROSARIO'));

        $this->service->getCoordinates('BV OROÑO 1234', 'ROSARIO');
        $this->assertTrue($this->service->isKnownMiss('BV OROÑO 1234', 'ROSARIO'));
        Http::assertSentCount(4);

        $this->service->getCoordinates('SAN JUAN 1768', 'ROSARIO');
        $this->assertFalse($this->service->isKnownMiss('SAN JUAN 1768', 'ROSARIO'), 'con coordenadas no es un "sin resultado"');

        $this->travel(8)->days();
        $this->assertFalse($this->service->isKnownMiss('PEDRO GARCIA 2450', 'ROSARIO'), 'a los 7 días se vuelve a intentar');
    }

    /**
     * El motivo de la pausa queda guardado para informarlo (el comando decía siempre "429").
     */
    public function test_circuit_reason_distingue_429_de_timeout(): void
    {
        Http::fakeSequence('nominatim.openstreetmap.org/*')
            ->pushFailedConnection('cURL error 28: Operation timed out')
            ->push(self::HTML_429, 429);
        $this->assertNull($this->service->circuitReason());

        $this->service->getCoordinates('SAN JUAN 1768', 'ROSARIO');
        $this->assertSame('no respondió', $this->service->circuitReason());

        $this->travel(6)->minutes();
        $this->assertNull($this->service->circuitReason());

        $this->service->getCoordinates('RIVADAVIA 676', 'SAN GENARO');
        $this->assertSame('respondió HTTP 429', $this->service->circuitReason());
    }
}
