<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RC-552 — el admin se sirve desde www.ryrcomisiones.com y le pega a
 * ryrcomisiones.com/back/public/api (sin www), así que toda llamada es cross-origin y,
 * como lleva Authorization, el navegador manda antes un preflight OPTIONS.
 *
 * Con max_age=0 ese preflight no se guardaba nunca: en el access log de producción del
 * 31/08 al 23/09 hubo 78.136 OPTIONS, el 48% del tráfico del admin, cada uno con un boot
 * de Laravel y un RTT extra antes de la llamada real. Con Access-Control-Max-Age el
 * navegador lo reutiliza por URL+método (Chrome hasta 2 h). Medido con Chrome headless
 * contra el backend local: 5 vueltas por 4 endpoints pasaron de 20 OPTIONS a 4.
 *
 * El resto de la política no cambia: mismos orígenes, métodos, headers y credenciales.
 */
class CorsPreflightCacheTest extends TestCase
{
    use RefreshDatabase;

    private const ORIGEN_ADMIN = 'https://www.ryrcomisiones.com';

    private function preflight(string $uri, string $metodo = 'GET', string $origen = self::ORIGEN_ADMIN)
    {
        return $this->withHeaders([
            'Origin' => $origen,
            'Access-Control-Request-Method' => $metodo,
            'Access-Control-Request-Headers' => 'authorization,content-type',
        ])->options($uri);
    }

    public function test_el_preflight_del_admin_se_puede_cachear_un_dia(): void
    {
        $respuesta = $this->preflight('/api/commissions');

        $respuesta->assertStatus(204);
        $respuesta->assertHeader('Access-Control-Max-Age', '86400');
    }

    /**
     * Las escrituras también llevan preflight propio (la cache es por URL y método):
     * en el período hubo 2.643 POST, 2.019 PUT, 2.019 DELETE y 940 PATCH precedidos de uno.
     */
    public function test_el_max_age_aplica_a_todos_los_metodos_que_usa_el_admin(): void
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $metodo) {
            $respuesta = $this->preflight('/api/commissions/55711', $metodo);

            $respuesta->assertStatus(204);
            $respuesta->assertHeader('Access-Control-Max-Age', '86400');
            $respuesta->assertHeader('Access-Control-Allow-Methods', $metodo);
        }
    }

    /**
     * El preflight lo contesta HandleCors antes del ruteo: una ruta con auth:sanctum
     * (el pool de cobranzas, 4.342 OPTIONS en el período) no puede devolver 401 al
     * OPTIONS, porque el navegador ni siquiera mandaría la llamada real con el token.
     */
    public function test_el_preflight_de_una_ruta_protegida_no_pide_sesion(): void
    {
        $respuesta = $this->preflight('/api/admin/collection-pool?page=1&per_page=15');

        $respuesta->assertStatus(204);
        $respuesta->assertHeader('Access-Control-Max-Age', '86400');
        $respuesta->assertHeader('Access-Control-Allow-Origin', self::ORIGEN_ADMIN);
    }

    public function test_el_preflight_mantiene_origen_credenciales_y_headers_permitidos(): void
    {
        $respuesta = $this->preflight('/api/admin/collection-pool', 'PUT');

        $respuesta->assertStatus(204);
        $respuesta->assertHeader('Access-Control-Allow-Origin', self::ORIGEN_ADMIN);
        $respuesta->assertHeader('Access-Control-Allow-Credentials', 'true');
        $respuesta->assertHeader('Access-Control-Allow-Methods', 'PUT');
        $respuesta->assertHeader('Access-Control-Allow-Headers', 'authorization,content-type');
    }

    /**
     * Los orígenes no se tocaron: el front local de desarrollo sigue habilitado y, como
     * la lista incluye '*', cualquier origen recibe su propio Origin de vuelta.
     */
    public function test_los_origenes_permitidos_no_cambian(): void
    {
        foreach (['http://localhost:5173', 'https://ryrcomisiones.com', 'https://otro.example'] as $origen) {
            $this->preflight('/api/commissions', 'GET', $origen)
                ->assertStatus(204)
                ->assertHeader('Access-Control-Allow-Origin', $origen)
                ->assertHeader('Access-Control-Max-Age', '86400');
        }
    }

    /**
     * Max-Age es sólo del preflight: la respuesta real conserva los headers CORS de
     * siempre y no lleva Max-Age (/origins es público, lo usa el cotizador de la landing).
     */
    public function test_la_respuesta_real_no_cambia_y_no_lleva_max_age(): void
    {
        $respuesta = $this->withHeaders(['Origin' => self::ORIGEN_ADMIN])->getJson('/api/origins');

        $respuesta->assertOk();
        $respuesta->assertHeader('Access-Control-Allow-Origin', self::ORIGEN_ADMIN);
        $respuesta->assertHeader('Access-Control-Allow-Credentials', 'true');
        $respuesta->assertHeaderMissing('Access-Control-Max-Age');
    }

    /**
     * Borde: una request sin Origin (la app de cadetes en Dart, curl, el cron) no es
     * CORS y no recibe ningún header de preflight.
     */
    public function test_sin_origin_no_hay_headers_de_preflight(): void
    {
        $respuesta = $this->getJson('/api/origins');

        $respuesta->assertOk();
        $respuesta->assertHeaderMissing('Access-Control-Max-Age');
        $respuesta->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
