<?php

namespace Tests\Feature\Cadete;

use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use App\Shared\Models\Transport;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * RC-549: GET /cadete/deliveries, /cadete/deliveries-history y /cadete/shipments ya no
 * geocodifican al vuelo.
 *
 * En prod llamaban a Nominatim por cada ubicación sin coordenadas de la página (el 70% de las
 * 13.382): ~12.000 llamadas por día hábil, 99,5% fallidas, +0,5-1,5 s por request, y dos
 * líneas de log por ubicación. Medido sobre la copia de prod: deliveries del cadete 17
 * 945 ms → 18 ms, shipments 6,4 s → 8 ms, con el JSON idéntico.
 *
 * Nominatim responde 429 a todo en estos tests: si el código lo llamara, se vería.
 */
class CadeteDeliveriesCoordinatesTest extends TestCase
{
    use RefreshDatabase;

    private User $cadete;
    private Branch $branch;
    private Customer $client;
    private Destination $destination;
    private Location $withCoordinates;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('<html>429 Too many requests</html>', 429)]);

        $this->branch = Branch::factory()->create();
        $this->cadete = User::factory()->create([
            'role' => UserRole::CADETE,
            'branch_id' => $this->branch->id,
        ]);
        Transport::factory()->create(['cadete_id' => $this->cadete->id]);
        $this->client = Customer::factory()->create(['name' => 'Juan', 'last_name' => 'Pérez']);
        $this->destination = Destination::factory()->create();

        $this->withCoordinates = Location::factory()->create([
            'name' => 'DEPOSITO RYR',
            'address' => 'RIVADAVIA 676',
            'origin' => 'SAN GENARO',
            'latitude' => -32.3667239,
            'longitude' => -61.3608211,
        ]);
    }

    private function locationWithoutCoordinates(string $address, string $origin = 'ROSARIO'): Location
    {
        return Location::factory()->create([
            'address' => $address,
            'origin' => $origin,
            'latitude' => null,
            'longitude' => null,
        ]);
    }

    private function delivery(Location $origin, Location $destination, array $overrides = []): Commission
    {
        return Commission::factory()->create(array_merge([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $origin->id,
            'destination_location_id' => $destination->id,
            'status' => CommissionStatus::EN_PROCESO_ENTREGA,
            'cadete_id' => $this->cadete->id,
            'date' => now(),
        ], $overrides));
    }

    /**
     * El caso de prod: página con varias ubicaciones sin coordenadas. Antes eran hasta 2
     * llamadas a Nominatim por ubicación; ahora ninguna, y las coordenadas vienen null como
     * ya venían (el 429 devolvía null).
     */
    public function test_deliveries_no_llama_a_nominatim_aunque_falten_coordenadas(): void
    {
        foreach (['SANTA FE 624 (deposito)', 'BV OROÑO 1234', 'CORTADA NEUQUEN 552'] as $address) {
            $this->delivery($this->withCoordinates, $this->locationWithoutCoordinates($address));
        }

        $response = $this->actingAs($this->cadete)->getJson('/api/cadete/deliveries');

        $response->assertOk();
        $deliveries = $response->json('data.deliveries');
        $this->assertCount(3, $deliveries);
        foreach ($deliveries as $delivery) {
            $this->assertNull($delivery['delivery_latitude']);
            $this->assertNull($delivery['delivery_longitude']);
        }
        Http::assertNothingSent();
    }

    /**
     * Las coordenadas guardadas en la locación salen tal cual, con el mismo tipo que antes
     * (string con 8 decimales por el cast decimal:8 del modelo).
     */
    public function test_deliveries_devuelve_las_coordenadas_guardadas_igual_que_antes(): void
    {
        $this->delivery($this->withCoordinates, $this->locationWithoutCoordinates('SAN JUAN 1768'));

        $delivery = $this->actingAs($this->cadete)->getJson('/api/cadete/deliveries')->json('data.deliveries.0');

        $this->assertSame('-32.36672390', $delivery['pickup_latitude']);
        $this->assertSame('-61.36082110', $delivery['pickup_longitude']);
        Http::assertNothingSent();
    }

    /**
     * El 0,5% que el geocoding al vuelo sí resolvía quedó en la cache de éxitos: se sigue
     * devolviendo (como float, igual que antes) y se guarda en la locación, sin salir a la red.
     */
    public function test_deliveries_usa_la_cache_de_exitos_y_la_guarda_en_la_locacion(): void
    {
        $destination = $this->locationWithoutCoordinates('SAN JUAN 1768');
        Cache::put('geocode_nominatim_' . md5('SAN JUAN 1768, ROSARIO, Argentina'), ['latitude' => -32.9468, 'longitude' => -60.6393], now()->addDays(30));
        $this->delivery($this->withCoordinates, $destination);

        $delivery = $this->actingAs($this->cadete)->getJson('/api/cadete/deliveries')->json('data.deliveries.0');

        $this->assertSame(-32.9468, $delivery['delivery_latitude']);
        $this->assertSame(-60.6393, $delivery['delivery_longitude']);
        $this->assertEquals(-32.9468, (float) $destination->fresh()->latitude);
        $this->assertEquals(-60.6393, (float) $destination->fresh()->longitude);
        Http::assertNothingSent();
    }

    /**
     * El historial usa la misma función: tampoco llama.
     */
    public function test_historial_no_llama_a_nominatim(): void
    {
        foreach (range(1, 4) as $i) {
            $this->delivery($this->withCoordinates, $this->locationWithoutCoordinates("SAN MARTIN {$i}00"), [
                'status' => CommissionStatus::ENTREGADO,
            ]);
        }

        $response = $this->actingAs($this->cadete)->getJson('/api/cadete/deliveries-history');

        $response->assertOk();
        $this->assertCount(4, $response->json('data.deliveries'));
        Http::assertNothingSent();
    }

    /**
     * shipments no selecciona latitude/longitude de la locación, así que antes SIEMPRE iba a
     * Nominatim (medido: 42 llamadas y 6,4 s para 20 envíos). Ahora sale de la cache o null.
     */
    public function test_shipments_no_llama_a_nominatim(): void
    {
        $cached = $this->locationWithoutCoordinates('SAN JUAN 1768');
        Cache::put('geocode_nominatim_' . md5('SAN JUAN 1768, ROSARIO, Argentina'), ['latitude' => -32.9468, 'longitude' => -60.6393], now()->addDays(30));
        $this->delivery($this->withCoordinates, $cached);
        $this->delivery($this->withCoordinates, $this->locationWithoutCoordinates('CORTADA NEUQUEN 552'));

        $response = $this->actingAs($this->cadete)->getJson('/api/cadete/shipments');

        $response->assertOk();
        $shipments = collect($response->json('shipments'))->keyBy('destination.id');
        $this->assertSame(-32.9468, $shipments[$cached->id]['destination']['latitude']);
        $this->assertCount(2, $shipments);
        Http::assertNothingSent();
    }

    /**
     * Caso que encontró la revisión en ryr_qa (cadete 38, locación 535 en 3 envíos de la misma
     * página, resuelta desde la cache de éxitos). Antes, la primera aparición venía como float
     * (lo que guarda la cache) y las siguientes como string con 8 decimales: el update() dejaba
     * las coordenadas en el modelo, que es el mismo para todas las comisiones de la página, y
     * salían por el cast decimal:8. shipments no selecciona latitude/longitude, y sin ese update
     * las repetidas pasaban a float. Tiene que seguir igual.
     */
    public function test_shipments_locacion_de_cache_repetida_devuelve_lo_mismo_que_antes(): void
    {
        $cached = $this->locationWithoutCoordinates('SAN JUAN 1768');
        Cache::put('geocode_nominatim_' . md5('SAN JUAN 1768, ROSARIO, Argentina'), ['latitude' => -32.9468, 'longitude' => -60.6393], now()->addDays(30));
        foreach (range(1, 3) as $i) {
            $this->delivery($this->withCoordinates, $cached, ['created_at' => now()->subMinutes($i)]);
        }

        $shipments = collect($this->actingAs($this->cadete)->getJson('/api/cadete/shipments')->json('shipments'));

        $this->assertSame([-32.9468, '-32.94680000', '-32.94680000'], $shipments->pluck('destination.latitude')->all());
        $this->assertSame([-60.6393, '-60.63930000', '-60.63930000'], $shipments->pluck('destination.longitude')->all());
        // Se guardan en la locación como antes, para cuando venza la cache.
        $this->assertEquals(-32.9468, (float) $cached->fresh()->latitude);
        Http::assertNothingSent();
    }

    /**
     * Lo mismo en deliveries, que sí trae las columnas (vienen null): primera aparición float,
     * las siguientes string con 8 decimales, como con el update() + refresh() de antes.
     */
    public function test_deliveries_locacion_de_cache_repetida_devuelve_lo_mismo_que_antes(): void
    {
        $cached = $this->locationWithoutCoordinates('SAN JUAN 1768');
        Cache::put('geocode_nominatim_' . md5('SAN JUAN 1768, ROSARIO, Argentina'), ['latitude' => -32.9468, 'longitude' => -60.6393], now()->addDays(30));
        foreach (range(1, 3) as $i) {
            $this->delivery($this->withCoordinates, $cached, ['created_at' => now()->subMinutes($i)]);
        }

        $deliveries = collect($this->actingAs($this->cadete)->getJson('/api/cadete/deliveries')->json('data.deliveries'));

        $this->assertSame([-32.9468, '-32.94680000', '-32.94680000'], $deliveries->pluck('delivery_latitude')->all());
        $this->assertSame([-60.6393, '-60.63930000', '-60.63930000'], $deliveries->pluck('delivery_longitude')->all());
        Http::assertNothingSent();
    }

    /**
     * Antes: por cada ubicación sin coordenadas, un error con el HTML del 429 y un warning
     * "No se pudieron calcular coordenadas". Ahora el GET no escribe nada en el log.
     */
    public function test_deliveries_no_escribe_en_el_log_por_cada_ubicacion(): void
    {
        foreach (range(1, 5) as $i) {
            $this->delivery($this->withCoordinates, $this->locationWithoutCoordinates("BV OROÑO {$i}00"));
        }
        Log::spy();

        $this->actingAs($this->cadete)->getJson('/api/cadete/deliveries')->assertOk();

        Log::shouldNotHaveReceived('error');
        Log::shouldNotHaveReceived('warning');
    }

    /**
     * La cantidad de queries no depende de cuántas ubicaciones no tienen coordenadas.
     */
    public function test_queries_no_crecen_con_las_ubicaciones_sin_coordenadas(): void
    {
        $this->delivery($this->withCoordinates, $this->withCoordinates);
        $baseline = $this->countQueries(fn () => $this->actingAs($this->cadete)->getJson('/api/cadete/deliveries')->assertOk());

        foreach (range(1, 8) as $i) {
            $this->delivery($this->locationWithoutCoordinates("SAN LUIS {$i}25"), $this->locationWithoutCoordinates("SARMIENTO {$i}10"));
        }
        $withMissing = $this->countQueries(fn () => $this->actingAs($this->cadete)->getJson('/api/cadete/deliveries')->assertOk());

        $this->assertSame($baseline, $withMissing);
        Http::assertNothingSent();
    }

    /**
     * Contrato: cada entrega trae exactamente las mismas claves que antes del cambio.
     */
    public function test_contrato_de_deliveries_no_cambia(): void
    {
        $this->delivery($this->withCoordinates, $this->locationWithoutCoordinates('SAN JUAN 1768'));

        $delivery = $this->actingAs($this->cadete)->getJson('/api/cadete/deliveries')->json('data.deliveries.0');

        $this->assertSame([
            'id', 'tracking_number', 'customer_name', 'customer_address', 'customer_phone',
            'pickup_address', 'pickup_phone', 'pickup_latitude', 'pickup_longitude',
            'delivery_address', 'delivery_phone', 'delivery_latitude', 'delivery_longitude',
            'status', 'status_label', 'estimated_pickup_time', 'estimated_delivery_time',
            'commission_amount', 'created_at', 'updated_at', 'notes', 'items_count', 'weight_kg',
            'dimensions', 'signature_data',
        ], array_keys($delivery));
    }

    /**
     * Contrato de shipments: origen y destino con las mismas claves (incluidas lat/long).
     */
    public function test_contrato_de_shipments_no_cambia(): void
    {
        $this->delivery($this->withCoordinates, $this->locationWithoutCoordinates('SAN JUAN 1768'));

        $shipment = $this->actingAs($this->cadete)->getJson('/api/cadete/shipments')->json('shipments.0');

        $this->assertSame(['id', 'name', 'address', 'phone', 'latitude', 'longitude'], array_keys($shipment['origin']));
        $this->assertSame(['id', 'name', 'address', 'phone', 'latitude', 'longitude'], array_keys($shipment['destination']));
        $this->assertNull($shipment['destination']['latitude']);
    }

    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
