<?php

namespace Tests\Feature\Commissions;

use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * RC-533 — "WhatsApp de seguimiento: feedback funciona, seguimiento automático no."
 *
 * El seguimiento es el único WhatsApp que se manda al crear la comisión, con el link
 * a /tracking/{id} del front (RC-239: un solo mensaje para no spamear al cliente; los
 * cambios de estado no mandan WhatsApp). La encuesta de feedback sale al entregar.
 *
 * En producción, entre el 15 y el 23/09/2026 el seguimiento se intentó en las 456 altas
 * y GreenAPI aceptó 447. De los 9 rechazos, 7 fueron por el chatId: clientes con el 0 de
 * larga distancia adelante ("03401448230", comisión 55901 del 22/09) o con dos números
 * en el mismo campo ("03401448659  448756", comisión 55893; "3401412750 // 3401405638",
 * comisión 55633). Los otros 2 fueron un número mal tipeado y un timeout.
 *
 * Estos tests fijan el contrato del seguimiento contra un GreenAPI simulado que valida
 * el chatId como el real, y el camino que no tiene que cambiar (RC-239 y la encuesta).
 * Que GreenAPI acepte el chatId no garantiza que el mensaje llegue: a un fijo también le
 * contesta 200. De esos 7, sólo el de la 55633 está en un bloque de celulares; los
 * clientes 163 y 1819 tienen números del bloque 3401-448, que otros clientes etiquetan
 * como fijo, y necesitan un celular en la ficha.
 */
class CommissionTrackingWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Destination $destination;
    private Location $originLocation;
    private Location $destinationLocation;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        config([
            'services.whatsapp.base_url' => 'https://api.green-api.test',
            'services.whatsapp.instance_id' => '1101000001',
            'services.whatsapp.token' => 'token-de-prueba',
            'app.frontend_url' => 'https://ryrcomisiones.com',
        ]);

        $this->branch = Branch::factory()->create();
        $this->admin = User::factory()->create(['role' => UserRole::ADMINISTRADOR, 'branch_id' => null]);
        Sanctum::actingAs($this->admin);

        $this->destination = Destination::factory()->create();
        $this->originLocation = Location::factory()->create();
        $this->destinationLocation = Location::factory()->create();
    }

    public function test_el_alta_de_una_comision_le_manda_al_cliente_el_whatsapp_de_seguimiento_con_el_link_al_front(): void
    {
        $this->fakeGreenApi();
        $cliente = $this->cliente(['mobile' => '3415004958', 'name' => 'Laura', 'last_name' => 'Pérez']);

        $id = $this->crearComision($cliente, ['notes' => 'Dejar en portería'])->json('id');

        $mensajes = $this->mensajesAGreenApi();
        $this->assertCount(1, $mensajes, 'El alta tiene que mandar exactamente un WhatsApp: el de seguimiento');

        [$request, $response] = $mensajes->first();
        $this->assertSame('https://api.green-api.test/waInstance1101000001/sendMessage/token-de-prueba', $request->url());
        $this->assertSame('5493415004958@c.us', $request['chatId']);
        $this->assertSame(200, $response->status());

        $texto = $request['message'];
        $this->assertStringContainsString('Hola Laura Pérez', $texto);
        $this->assertStringContainsString('Tu comisión fue cargada a nuestro sistema', $texto);
        $this->assertStringContainsString("*Envío #{$id}*", $texto);
        $this->assertStringContainsString("https://ryrcomisiones.com/tracking/{$id}", $texto);
        $this->assertStringContainsString('Dejar en portería', $texto);
    }

    public function test_cliente_con_cero_adelante_greenapi_acepta_el_chat_id_que_rechazo_en_la_comision_55901(): void
    {
        $this->fakeGreenApi();
        // Cliente 163 de producción: sólo teléfono, con el 0 de larga distancia. Está en el
        // bloque 3401-448 que otros clientes etiquetan como fijo: esto prueba el formato,
        // no que le llegue.
        $cliente = $this->cliente(['mobile' => null, 'phone' => '03401448230']);

        $this->crearComision($cliente);

        $mensajes = $this->mensajesAGreenApi();
        $this->assertCount(1, $mensajes);
        [$request, $response] = $mensajes->first();
        // Antes salía 54903401448230@c.us y GreenAPI respondía 400 "invalid phone number".
        $this->assertSame('5493401448230@c.us', $request['chatId']);
        $this->assertSame(200, $response->status());
    }

    public function test_cliente_con_dos_numeros_en_el_campo_greenapi_acepta_el_chat_id_que_rechazo_en_la_comision_55893(): void
    {
        $this->fakeGreenApi();
        // Cliente 1819 de producción: 4 seguimientos rechazados del 15 al 23/09/2026
        // (comisiones 55562, 55830, 55893 y 55925). También es un fijo del bloque 3401-448.
        $cliente = $this->cliente(['mobile' => '03401448659  448756']);

        $this->crearComision($cliente);

        $mensajes = $this->mensajesAGreenApi();
        $this->assertCount(1, $mensajes);
        [$request, $response] = $mensajes->first();
        // Antes salía 54903401448659448756@c.us: los dos números pegados.
        $this->assertSame('5493401448659@c.us', $request['chatId']);
        $this->assertSame(200, $response->status());
    }

    public function test_cliente_con_dos_celulares_el_seguimiento_va_al_primero_como_debio_pasar_con_la_comision_55633(): void
    {
        $this->fakeGreenApi();
        // Cliente 954 de producción: GreenAPI rechazó el seguimiento de la 55633 el
        // 16/09/2026 porque salía 54934014127503401405638@c.us.
        $cliente = $this->cliente(['mobile' => '3401412750 // 3401405638']);

        $id = $this->crearComision($cliente)->json('id');

        $mensajes = $this->mensajesAGreenApi();
        $this->assertCount(1, $mensajes);
        [$request, $response] = $mensajes->first();
        $this->assertSame('5493401412750@c.us', $request['chatId']);
        $this->assertSame(200, $response->status());
        $this->assertStringContainsString("https://ryrcomisiones.com/tracking/{$id}", $request['message']);
    }

    public function test_cliente_con_fijo_y_celular_el_seguimiento_va_al_celular(): void
    {
        $this->fakeGreenApi();
        // Cliente 662 de producción: el fijo está primero, pero al fijo no le llega nada.
        $cliente = $this->cliente(['mobile' => '03401-498809(fijo) 3401-414063 (cel)']);

        $this->crearComision($cliente);

        $mensajes = $this->mensajesAGreenApi();
        $this->assertCount(1, $mensajes);
        $this->assertSame('5493401414063@c.us', $mensajes->first()[0]['chatId']);
    }

    public function test_cliente_sin_telefono_se_crea_la_comision_sin_llamar_a_greenapi(): void
    {
        $this->fakeGreenApi();
        $cliente = $this->cliente(['mobile' => null, 'phone' => null]);

        $this->crearComision($cliente)->assertCreated();

        $this->assertCount(0, $this->mensajesAGreenApi());
        $this->assertDatabaseHas('commissions', ['client_id' => $cliente->id]);
    }

    public function test_si_greenapi_falla_la_comision_se_crea_igual(): void
    {
        $this->fakeGreenApi(estadoForzado: 500);
        $cliente = $this->cliente(['mobile' => '3415004958']);

        $this->crearComision($cliente)->assertCreated();

        $this->assertCount(1, $this->mensajesAGreenApi());
        $this->assertDatabaseHas('commissions', ['client_id' => $cliente->id]);
    }

    public function test_los_cambios_de_estado_intermedios_no_le_mandan_whatsapp_al_cliente(): void
    {
        // RC-239: el seguimiento es un único mensaje en el alta; el cliente ve el avance
        // en el link. Si esto cambia tiene que ser una decisión de producto explícita.
        $this->fakeGreenApi();
        $comision = $this->comisionExistente($this->cliente(['mobile' => '3415004958']));

        foreach ([CommissionStatus::CADETE_ASIGNADO, CommissionStatus::ENCOMIENDA_RETIRADA, CommissionStatus::EN_TRANSITO_DESTINO] as $estado) {
            $this->patchJson("/api/commissions/{$comision->id}/status", ['status' => $estado->value])->assertOk();
        }

        $this->assertCount(0, $this->mensajesAGreenApi());
    }

    public function test_al_entregar_sale_un_solo_whatsapp_y_es_la_encuesta_al_mismo_numero_que_el_seguimiento(): void
    {
        $this->fakeGreenApi();
        $comision = $this->comisionExistente($this->cliente(['mobile' => '03401448230']));

        $this->patchJson("/api/commissions/{$comision->id}/status", [
            'status' => CommissionStatus::ENTREGADO->value,
        ])->assertOk();

        $mensajes = $this->mensajesAGreenApi();
        $this->assertCount(1, $mensajes, 'Al entregar sale sólo la encuesta, sin un segundo mensaje de estado');

        [$request, $response] = $mensajes->first();
        $this->assertSame('5493401448230@c.us', $request['chatId']);
        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('Tu opinión nos importa', $request['message']);
        $this->assertStringContainsString('https://ryrcomisiones.com/feedback/', $request['message']);
    }

    /**
     * GreenAPI simulado: igual que el real, rechaza con 400 un chatId que no es un
     * celular argentino completo (549 + 10 dígitos).
     */
    private function fakeGreenApi(?int $estadoForzado = null): void
    {
        Http::fake(function (Request $request) use ($estadoForzado) {
            if (! str_contains($request->url(), 'api.green-api.test')) {
                return Http::response([], 200);
            }

            if ($estadoForzado) {
                return Http::response(['message' => 'Internal Server Error'], $estadoForzado);
            }

            if (! preg_match('/^549\d{10}@c\.us$/', (string) ($request['chatId'] ?? ''))) {
                return Http::response([
                    'statusCode' => 400,
                    'message' => "Validation failed. Details: 'chatId': invalid phone number",
                ], 400);
            }

            return Http::response(['idMessage' => '3EB0FCBDD51DBDAF203800'], 200);
        });
    }

    /**
     * @return Collection<int, array{0: Request, 1: \Illuminate\Http\Client\Response}>
     */
    private function mensajesAGreenApi(): Collection
    {
        return Http::recorded(fn (Request $request) => str_contains($request->url(), 'api.green-api.test'));
    }

    private function cliente(array $datos): Customer
    {
        return Customer::factory()->create(array_merge([
            'email' => '',
            'branch_id' => $this->branch->id,
        ], $datos));
    }

    private function crearComision(Customer $cliente, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/commissions', array_merge([
            'client_id' => $cliente->id,
            'date' => '2026-09-22',
            'origin' => $this->destination->origin,
            'destination' => $this->destination->destination,
            'status' => CommissionStatus::BUSCANDO_CADETE->value,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'total' => 12000,
        ], $extra))->assertCreated();
    }

    private function comisionExistente(Customer $cliente): Commission
    {
        return Commission::factory()->create([
            'client_id' => $cliente->id,
            'destination_id' => $this->destination->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::BUSCANDO_CADETE,
        ]);
    }
}
