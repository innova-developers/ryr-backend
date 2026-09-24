<?php

namespace Tests\Feature\Destinations;

use App\Shared\Models\Destination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RC-550 — GET /destinations sin paginar.
 *
 * En prod el listado completo son 25.899 filas, 6 MB, 3,8 s y 71 MB por llamada (el 75% de
 * todos los bytes servidos) y el listado de comisiones y el panel de cobradores lo bajaban
 * al abrirse sólo para armar el <select> del filtro "Destino". Se agregó un modo liviano,
 * GET /destinations?search=..., y la respuesta por defecto tiene que seguir exactamente igual:
 * la usan la pantalla de Destinos y TarifasEspecialesModal (que manda ?per_page=1000).
 *
 * Las rutas GET de destinos son públicas (las usa el cotizador de la landing), así que
 * estos tests pegan sin sesión.
 */
class DestinationSearchTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVES_COMPLETAS = [
        'id', 'origin', 'destination', 'fixed_price', 'small_bulk_price', 'large_bulk_price',
        'created_at', 'updated_at', 'deleted_at',
    ];

    private function ruta(string $origin, string $destination): Destination
    {
        return Destination::factory()->create([
            'origin' => $origin,
            'destination' => $destination,
            'fixed_price' => 8500,
            'small_bulk_price' => 3000,
            'large_bulk_price' => 6000,
        ]);
    }

    /**
     * El contrato de siempre: array plano con todas las columnas de cada destino activo,
     * precios como número y sin los dados de baja. Es lo que pinta la pantalla de Destinos.
     */
    public function test_listado_por_defecto_mantiene_la_respuesta_completa(): void
    {
        $this->ruta('SAN GENARO', 'LAS ROSAS');
        $this->ruta('SAN GENARO', 'DIAZ');
        $this->ruta('ROSARIO', 'FUNES');
        $this->ruta('ROSARIO', 'PEREZ')->delete();

        $response = $this->getJson('/api/destinations');

        $response->assertOk()->assertJsonCount(3);
        $this->assertSame(json_encode(Destination::all()->toArray()), $response->getContent());

        $primero = $response->json(0);
        $this->assertSame(self::CLAVES_COMPLETAS, array_keys($primero));
        $this->assertSame('SAN GENARO', $primero['origin']);
        $this->assertSame('LAS ROSAS', $primero['destination']);
        $this->assertEquals(8500, $primero['fixed_price']);
        $this->assertNull($primero['deleted_at']);
        $this->assertNotContains('PEREZ', array_column($response->json(), 'destination'));
    }

    /**
     * TarifasEspecialesModal pide /destinations?per_page=1000 y el backend nunca paginó:
     * devuelve la lista entera. Tiene que seguir igual byte a byte que sin parámetros.
     */
    public function test_per_page_se_sigue_ignorando_como_hoy(): void
    {
        $this->ruta('SAN GENARO', 'LAS ROSAS');
        $this->ruta('SAN GENARO', 'DIAZ');
        $this->ruta('ROSARIO', 'FUNES');

        $porDefecto = $this->getJson('/api/destinations')->assertOk()->getContent();
        $conPerPage = $this->getJson('/api/destinations?per_page=1')->assertOk()->getContent();

        $this->assertSame($porDefecto, $conPerPage);
        $this->assertCount(3, json_decode($conPerPage, true));
    }

    /**
     * El modo liviano devuelve sólo lo que muestra el select: id, origin y destination.
     */
    public function test_search_devuelve_solo_id_origen_y_destino(): void
    {
        $ruta = $this->ruta('ROSARIO', 'FUNES');

        $response = $this->getJson('/api/destinations?search=funes');

        $response->assertOk()->assertExactJson([
            ['id' => $ruta->id, 'origin' => 'ROSARIO', 'destination' => 'FUNES'],
        ]);
    }

    /**
     * Cada palabra tiene que estar en el origen o en el destino: "rosario funes" trae la
     * ruta en los dos sentidos y deja afuera ROSARIO - PEREZ. Con la etiqueta tal cual la
     * muestra el select ("ROSARIO - FUNES") encuentra lo mismo.
     */
    public function test_search_exige_cada_palabra_en_origen_o_destino(): void
    {
        $ida = $this->ruta('ROSARIO', 'FUNES');
        $vuelta = $this->ruta('FUNES', 'ROSARIO');
        $this->ruta('ROSARIO', 'PEREZ');
        $this->ruta('SAN GENARO', 'FUNES');

        $ids = array_column($this->getJson('/api/destinations?search=rosario%20funes')->assertOk()->json(), 'id');
        sort($ids);
        $this->assertSame([$ida->id, $vuelta->id], $ids);

        $idsConGuion = array_column($this->getJson('/api/destinations?search=ROSARIO%20-%20FUNES')->assertOk()->json(), 'id');
        sort($idsConGuion);
        $this->assertSame([$ida->id, $vuelta->id], $idsConGuion);
    }

    /**
     * En ryr_qa "rosario" coincide con 4.457 rutas y sólo 509 salen de ROSARIO; con el tope
     * de 50 y orden alfabético aparecían primero "BARRIO FISHERTON ROSARIO" y similares.
     * Primero van las que empiezan con la palabra en el origen, después en el destino.
     */
    public function test_search_prioriza_origen_y_despues_destino_que_empiezan_con_la_palabra(): void
    {
        $contiene = $this->ruta('BARRIO FISHERTON ROSARIO', 'FUNES');
        $destinoEmpieza = $this->ruta('ALVEAR', 'ROSARIO');
        $origenEmpiezaB = $this->ruta('ROSARIO', 'PEREZ');
        $origenEmpiezaA = $this->ruta('ROSARIO', 'ALCORTA');

        $ids = array_column($this->getJson('/api/destinations?search=rosario')->assertOk()->json(), 'id');

        $this->assertSame([$origenEmpiezaA->id, $origenEmpiezaB->id, $destinoEmpieza->id, $contiene->id], $ids);
    }

    /**
     * Los destinos dados de baja no se ofrecen, igual que en el listado completo.
     */
    public function test_search_excluye_destinos_dados_de_baja(): void
    {
        $activo = $this->ruta('SAN GENARO', 'PEREZ');
        $this->ruta('SAN GENARO', 'PEREZ NORTE')->delete();

        $ids = array_column($this->getJson('/api/destinations?search=perez')->assertOk()->json(), 'id');

        $this->assertSame([$activo->id], $ids);
    }

    /**
     * En prod hay 65 pares origen-destino repetidos con distinto id. El <select> viejo los
     * mostraba a todos y el filtro de comisiones es por id: se tienen que seguir ofreciendo.
     */
    public function test_search_devuelve_las_rutas_repetidas_con_su_id(): void
    {
        $una = $this->ruta('SAN GENARO', 'PEREZ');
        $otra = $this->ruta('SAN GENARO', 'PEREZ');

        $ids = array_column($this->getJson('/api/destinations?search=san%20genaro%20perez')->assertOk()->json(), 'id');

        $this->assertSame([$una->id, $otra->id], $ids);
    }

    /**
     * Tope de filas: 50 por defecto, ?limit acotado a 100, y un limit inválido cae al default.
     */
    public function test_search_respeta_el_limite_por_defecto_y_el_tope(): void
    {
        foreach (range(1, 120) as $i) {
            $this->ruta('ROSARIO', sprintf('LOCALIDAD %03d', $i));
        }

        $this->getJson('/api/destinations?search=rosario')->assertOk()->assertJsonCount(50);
        $this->getJson('/api/destinations?search=rosario&limit=10')->assertOk()->assertJsonCount(10);
        $this->getJson('/api/destinations?search=rosario&limit=500')->assertOk()->assertJsonCount(100);
        $this->getJson('/api/destinations?search=rosario&limit=0')->assertOk()->assertJsonCount(50);
        $this->getJson('/api/destinations?search=rosario&limit=abc')->assertOk()->assertJsonCount(50);
    }

    /**
     * ?search= vacío no puede terminar bajando la lista entera: devuelve el modo liviano
     * con el tope por defecto.
     */
    public function test_search_vacio_no_devuelve_el_listado_completo(): void
    {
        foreach (range(1, 60) as $i) {
            $this->ruta('ROSARIO', sprintf('LOCALIDAD %03d', $i));
        }

        $response = $this->getJson('/api/destinations?search=');

        $response->assertOk()->assertJsonCount(50);
        $this->assertSame(['id', 'origin', 'destination'], array_keys($response->json(0)));
    }

    /**
     * Sin coincidencias responde un array vacío, y un search mal formado (?search[]=x)
     * no rompe: se trata como vacío.
     */
    public function test_search_sin_coincidencias_y_parametro_mal_formado(): void
    {
        $this->ruta('ROSARIO', 'FUNES');

        $this->getJson('/api/destinations?search=zzzz')->assertOk()->assertExactJson([]);
        $this->getJson('/api/destinations?search[]=rosario')->assertOk()->assertJsonCount(1);
    }

    /**
     * Lo que se escribe se busca literal. Sobre ryr_qa, sin escapar, "%" y "_" devolvían
     * 50 rutas cualesquiera ("- - ---", "- - 8.30 A 12.30 / 14.30 A 18.30") y "r_s" traía
     * "ROSAIO - -": los comodines de LIKE pasaban tal cual. Ahora sólo encuentran rutas que
     * tengan ese carácter, y "!" (el carácter de escape) también se busca como texto.
     */
    public function test_search_toma_los_comodines_de_like_como_texto(): void
    {
        $this->ruta('ROSARIO', 'FUNES');
        $this->ruta('ROSAIO', 'PEREZ');
        $guionBajo = $this->ruta('KM_5', 'ROSARIO');
        $this->ruta('KMX5', 'ROSARIO');
        $porcentaje = $this->ruta('ROSARIO', 'ZONA 100%');
        $exclamacion = $this->ruta('SAN GENARO!', 'DIAZ');

        $ids = fn (string $search) => array_column(
            $this->getJson('/api/destinations?search=' . rawurlencode($search))->assertOk()->json(),
            'id'
        );

        $this->assertSame([$porcentaje->id], $ids('%'));
        $this->assertSame([$porcentaje->id], $ids('100%'));
        $this->assertSame([], $ids('%%'));
        $this->assertSame([$guionBajo->id], $ids('_'));
        $this->assertSame([$guionBajo->id], $ids('km_5'));
        $this->assertSame([], $ids('r_s'));
        $this->assertSame([$exclamacion->id], $ids('!'));
    }

    /**
     * La mejora: una sola query, acotada, sin hidratar las 25.899 filas.
     */
    public function test_search_hace_una_sola_query_acotada(): void
    {
        foreach (range(1, 80) as $i) {
            $this->ruta('ROSARIO', sprintf('LOCALIDAD %03d', $i));
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->getJson('/api/destinations?search=rosario%20localidad');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk()->assertJsonCount(50);
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('limit 50', strtolower($queries[0]['query']));
        $this->assertStringNotContainsString('*', $queries[0]['query']);
    }
}
