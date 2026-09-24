<?php

namespace Tests\Feature\Commissions;

use App\Contexts\Commissions\Application\CreateCommissionUseCase;
use App\Contexts\Commissions\Application\DTOs\CreateCommissionDTO;
use App\Contexts\Commissions\Application\UpdateCommissionStatusUseCase;
use App\Mail\FeedbackSurveyMail;
use App\Notification;
use App\Services\EnviosExternos;
use App\Services\FcmNotificationService;
use App\Services\NotificationService;
use App\Services\WhatsAppService;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\FcmToken;
use App\Shared\Models\FeedbackSurvey;
use App\Shared\Models\Location;
use App\Shared\Models\User;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Collection;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * RC-551 — WhatsApp, mail y push salen después del commit y después de responder.
 *
 * Caso real (informe RC-535, sección 7): el 23/09/2026 a las 10:08:31 un cadete marcó
 * ENTREGADO; el WhatsApp de la encuesta salió a las 10:08:34 y el push a las 10:08:37. El
 * cadete esperó 6 s con la transacción abierta, y la parte de base es menor a 0,1 s. Sobre
 * 1.535 entregas: p50 3 s, p90 4 s, máximo 23 s. El alta de comisión mandaba el WhatsApp de
 * seguimiento dentro de su transacción y un push por cadete de la sucursal, en secuencia.
 *
 * Estos tests fijan:
 *  - que en el momento en que el kernel tiene la respuesta (RequestHandled; después viene
 *    Response::send() con litespeed_finish_request) todavía no se llamó a ningún proveedor;
 *  - que después salen los mismos mensajes, a los mismos destinatarios y en el mismo orden;
 *  - que salen con la transacción del cambio ya confirmada, y que si se revierte no sale nada;
 *  - que un envío que explota después de responder no rompe la respuesta ni los demás envíos.
 */
class EnviosExternosDespuesDeResponderTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_GREENAPI = 'token-de-prueba';

    private Branch $branch;

    private User $admin;

    private User $cadete;

    private Customer $cliente;

    private Destination $destination;

    private Location $origen;

    private Location $destino;

    /** @var array<int, array{canal: string, nivel: int}> orden en que se llamó a los proveedores */
    public array $secuencia = [];

    /** @var array{whatsapp: int, mail: int, fcm: int}|null lo enviado cuando el kernel ya tenía la respuesta */
    private ?array $alResponder = null;

    private int $nivelBase;

    private object $fcm;

    protected function setUp(): void
    {
        parent::setUp();

        // La transacción de RefreshDatabase: con la del caso de uso ya confirmada, los
        // proveedores tienen que ver este nivel y no uno más.
        $this->nivelBase = DB::transactionLevel();

        Mail::fake();
        config([
            'services.whatsapp.base_url' => 'https://api.green-api.test',
            'services.whatsapp.instance_id' => '1101000001',
            'services.whatsapp.token' => self::TOKEN_GREENAPI,
            'app.frontend_url' => 'https://ryrcomisiones.com',
        ]);

        Http::fake(function (Request $request) {
            if (! str_contains($request->url(), 'api.green-api.test')) {
                return Http::response([], 200);
            }

            $this->secuencia[] = ['canal' => 'whatsapp', 'nivel' => DB::transactionLevel()];

            return Http::response(['idMessage' => '3EB0FCBDD51DBDAF203800'], 200);
        });

        $this->fcm = $this->fakeFcm();
        $this->app->instance(FcmNotificationService::class, $this->fcm);

        Event::listen(RequestHandled::class, function () {
            $this->alResponder = [
                'whatsapp' => count($this->whatsapps()),
                'mail' => Mail::sent(FeedbackSurveyMail::class)->count(),
                'fcm' => count($this->fcm->llamadas),
            ];
        });

        $this->branch = Branch::factory()->create(['name' => 'Sucursal Rosario']);
        $this->admin = User::factory()->create(['role' => UserRole::ADMINISTRADOR, 'branch_id' => $this->branch->id]);
        $this->cadete = $this->cadeteConToken('Cadete que entrega');
        $this->cliente = Customer::factory()->create([
            'name' => 'Laura', 'last_name' => 'Pérez', 'mobile' => '3415004958', 'phone' => null,
            'email' => 'laura@example.com', 'branch_id' => $this->branch->id,
        ]);
        $this->destination = Destination::factory()->create(['origin' => 'ROSARIO', 'destination' => 'SAN GENARO']);
        $this->origen = Location::factory()->create(['name' => 'Depósito Rosario']);
        $this->destino = Location::factory()->create(['name' => 'Oficina San Genaro']);
    }

    // --- ENTREGADO -------------------------------------------------------------------

    public function test_entregado_responde_antes_de_mandar_la_encuesta_y_el_push_y_despues_manda_lo_mismo_que_antes(): void
    {
        $comision = $this->comision(CommissionStatus::EN_PROCESO_ENTREGA, $this->cadete->id);
        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/commissions/{$comision->id}/status", ['status' => 'ENTREGADO'])
            ->assertOk()
            ->assertExactJson([
                'message' => 'Estado de la comisión actualizado correctamente',
                'commission' => [
                    'id' => $comision->id,
                    'status' => 'ENTREGADO',
                    'branch' => ['id' => $this->branch->id, 'name' => 'Sucursal Rosario'],
                ],
            ]);

        $this->assertSame(['whatsapp' => 0, 'mail' => 0, 'fcm' => 0], $this->alResponder,
            'Con la respuesta lista no se tiene que haber llamado a ningún proveedor');

        // La encuesta y el registro de la notificación se escriben en la transacción.
        $encuesta = FeedbackSurvey::where('commission_id', $comision->id)->firstOrFail();
        $notificacion = Notification::where('commission_id', $comision->id)->firstOrFail();

        // WhatsApp de la encuesta: mismo número y mismo texto de siempre.
        $whatsapps = $this->whatsapps();
        $this->assertCount(1, $whatsapps);
        $this->assertSame('5493415004958@c.us', $whatsapps[0]['chatId']);
        $this->assertSame(
            "🚚 *RYR Comisiones - Tu opinión nos importa*\n\nHola Laura Pérez,\n\n"
            ."Tu envío #{$comision->id} fue entregado.\n¿Cómo fue tu experiencia?\n\n"
            ."📝 Dejanos tu opinión acá:\nhttps://ryrcomisiones.com/feedback/{$encuesta->token}\n\n"
            .'¡Gracias por confiar en RYR! 🙏',
            $whatsapps[0]['message']
        );

        // Mail de la encuesta al cliente.
        Mail::assertSentCount(1);
        Mail::assertSent(FeedbackSurveyMail::class, fn (FeedbackSurveyMail $m) => $m->hasTo('laura@example.com'));

        // Push al cadete con lo mismo que quedó guardado en la notificación.
        $this->assertCount(1, $this->fcm->llamadas);
        $push = $this->fcm->llamadas[0];
        $this->assertSame($this->cadete->id, $push['user_id']);
        $this->assertSame('Comisión entregada', $push['payload']['title']);
        $this->assertSame("Comisión #{$comision->id}: Entregado", $push['payload']['body']);
        $this->assertEquals($notificacion->data, $push['payload']['data']);
        $this->assertSame($notificacion->title, $push['payload']['title']);
        $this->assertSame($notificacion->message, $push['payload']['body']);

        // Mismo orden que antes: primero la encuesta, después el push.
        $this->assertSame(['whatsapp', 'fcm'], array_column($this->secuencia, 'canal'));
    }

    public function test_los_envios_del_entregado_salen_con_la_transaccion_del_cambio_ya_confirmada(): void
    {
        $comision = $this->comision(CommissionStatus::EN_PROCESO_ENTREGA, $this->cadete->id);
        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/commissions/{$comision->id}/status", ['status' => 'ENTREGADO'])->assertOk();

        // Antes GreenAPI y FCM se llamaban un nivel más adentro: con la transacción abierta.
        $this->assertCount(2, $this->secuencia);
        foreach ($this->secuencia as $envio) {
            $this->assertSame($this->nivelBase, $envio['nivel'], "{$envio['canal']} salió con la transacción abierta");
        }
    }

    public function test_entregado_desde_la_app_de_cadetes_tambien_responde_antes_de_los_envios(): void
    {
        // PUT /cadete/deliveries/{id} pasa por el mismo caso de uso (CadeteController::
        // updateShipmentStatus); lo que hace después (firma, PAGO_VALIDACION) es sólo base.
        $comision = $this->comision(CommissionStatus::EN_PROCESO_ENTREGA, $this->cadete->id);
        Sanctum::actingAs($this->cadete);

        $respuesta = $this->putJson("/api/cadete/deliveries/{$comision->id}", [
            'status' => 'Entregado',
            'receiver_name' => 'Portería',
        ])->assertOk();

        $comision->refresh();
        $respuesta->assertExactJson([
            'success' => true,
            'message' => 'Estado del envío actualizado correctamente',
            'shipment' => [
                'id' => $comision->id,
                'status' => $comision->status->value,
                'status_label' => $comision->status->getCadeteStatus(),
                'updated_at' => $comision->updated_at->format('Y-m-d H:i:s'),
            ],
        ]);
        $this->assertSame(CommissionStatus::PAGO_VALIDACION, $comision->status);

        $this->assertSame(['whatsapp' => 0, 'mail' => 0, 'fcm' => 0], $this->alResponder);
        $this->assertCount(1, $this->whatsapps());
        Mail::assertSentCount(1);
        $this->assertSame([$this->cadete->id], array_column($this->fcm->llamadas, 'user_id'));
        $this->assertDatabaseHas('delivery_signatures', ['commission_id' => $comision->id, 'receiver_name' => 'Portería']);
    }

    // --- Alta y BUSCANDO_CADETE ------------------------------------------------------

    public function test_el_alta_responde_antes_del_whatsapp_de_seguimiento_y_del_push_a_los_cadetes(): void
    {
        $otroCadete = $this->cadeteConToken('Otro cadete de la sucursal');
        $this->cadeteConToken('Cadete de otra sucursal', Branch::factory()->create()->id);
        $sinToken = User::factory()->create(['role' => UserRole::CADETE, 'branch_id' => $this->branch->id]);
        FcmToken::create(['user_id' => $sinToken->id, 'fcm_token' => 'viejo', 'platform' => 'android', 'is_active' => false]);
        Sanctum::actingAs($this->admin);

        $respuesta = $this->postJson('/api/commissions', $this->datosAlta())->assertCreated();
        $id = $respuesta->json('id');
        $this->assertSame(CommissionStatus::BUSCANDO_CADETE->value, $respuesta->json('status'));

        $this->assertSame(['whatsapp' => 0, 'mail' => 0, 'fcm' => 0], $this->alResponder);

        $whatsapps = $this->whatsapps();
        $this->assertCount(1, $whatsapps, 'El alta manda un solo WhatsApp: el de seguimiento');
        $this->assertSame('5493415004958@c.us', $whatsapps[0]['chatId']);
        $this->assertSame(
            "🚚 *RYR Comisiones*\n\nHola Laura Pérez,\n\n"
            ."🏁 Tu comisión fue cargada a nuestro sistema con éxito!.\n\n"
            ."📋 *Envío #{$id}*\n\n"
            ."📝 *Mensaje:*\nDejar en portería\n\n"
            ."🔍 Puedes hacer seguimiento de tu envío desde nuestra web:\nhttps://ryrcomisiones.com/tracking/{$id}\n\n"
            ."También puedes acceder a tu panel de cliente para ver todos tus envíos y gestiones.\n\n"
            .'Gracias por confiar en RYR Comisiones! 🚛',
            $whatsapps[0]['message']
        );

        // Push sólo a los cadetes de la sucursal con token activo, con el texto de siempre.
        $this->assertEqualsCanonicalizing([$this->cadete->id, $otroCadete->id], array_column($this->fcm->llamadas, 'user_id'));
        foreach ($this->fcm->llamadas as $push) {
            $this->assertSame('Nueva comisión disponible', $push['payload']['title']);
            $this->assertSame("Nueva comisión #{$id} disponible: Depósito Rosario → Oficina San Genaro", $push['payload']['body']);
            $this->assertSame('new_commission_available', $push['payload']['data']['type']);
            $this->assertSame($id, $push['payload']['data']['commission_id']);
            $this->assertSame('BUSCANDO_CADETE', $push['payload']['data']['status']);
        }

        // Mismo orden que antes: el WhatsApp de seguimiento y después los push.
        $this->assertSame(['whatsapp', 'fcm', 'fcm'], array_column($this->secuencia, 'canal'));
        foreach ($this->secuencia as $envio) {
            $this->assertSame($this->nivelBase, $envio['nivel']);
        }
    }

    public function test_volver_a_buscando_cadete_avisa_a_los_cadetes_de_la_sucursal_despues_de_responder(): void
    {
        $otroCadete = $this->cadeteConToken('Otro cadete de la sucursal');
        $comision = $this->comision(CommissionStatus::SOLICITUD_RECIBIDA, null);
        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/commissions/{$comision->id}/status", ['status' => 'BUSCANDO_CADETE'])
            ->assertOk()
            ->assertJsonPath('commission.status', 'BUSCANDO_CADETE');

        $this->assertSame(['whatsapp' => 0, 'mail' => 0, 'fcm' => 0], $this->alResponder);
        $this->assertEqualsCanonicalizing([$this->cadete->id, $otroCadete->id], array_column($this->fcm->llamadas, 'user_id'));
        $this->assertSame(
            "Nueva comisión #{$comision->id} disponible: Depósito Rosario → Oficina San Genaro",
            $this->fcm->llamadas[0]['payload']['body']
        );
        $this->assertCount(0, $this->whatsapps(), 'RC-239: los cambios de estado no le mandan WhatsApp al cliente');
    }

    public function test_un_cambio_de_estado_sin_envios_externos_no_deja_nada_pendiente(): void
    {
        $comision = $this->comision(CommissionStatus::EN_TRANSITO_DESTINO, $this->cadete->id);
        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/commissions/{$comision->id}/status", ['status' => 'EN_PROCESO_ENTREGA'])->assertOk();

        // Se registra la notificación del cadete, pero EN_PROCESO_ENTREGA no lleva push.
        $this->assertDatabaseHas('notifications', ['commission_id' => $comision->id, 'user_id' => $this->cadete->id]);
        $this->assertSame([], $this->secuencia);
        $this->assertCount(0, $this->fcm->llamadas);
        Mail::assertNothingSent();
    }

    // --- Si la transacción se revierte, no sale nada ---------------------------------

    public function test_si_la_transaccion_del_entregado_falla_despues_de_crear_la_encuesta_no_se_manda_nada(): void
    {
        // La encuesta ya dejó su envío pendiente cuando el paso siguiente del caso de uso
        // falla: antes el WhatsApp y el mail ya habían salido y la encuesta se revertía.
        $this->mock(NotificationService::class, function ($mock) {
            $mock->shouldReceive('createCommissionStatusNotification')
                ->once()
                ->andThrow(new \RuntimeException('Falla simulada después de crear la encuesta'));
        });
        $comision = $this->comision(CommissionStatus::EN_PROCESO_ENTREGA, $this->cadete->id);
        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/commissions/{$comision->id}/status", ['status' => 'ENTREGADO'])->assertStatus(500);

        $this->assertDatabaseMissing('feedback_surveys', ['commission_id' => $comision->id]);
        $this->assertSame(CommissionStatus::EN_PROCESO_ENTREGA, $comision->fresh()->status);
        $this->assertCount(0, $this->whatsapps());
        Mail::assertNothingSent();
        $this->assertCount(0, $this->fcm->llamadas);
    }

    public function test_si_una_transaccion_que_envuelve_el_entregado_se_revierte_no_se_manda_nada(): void
    {
        $comision = $this->comision(CommissionStatus::EN_PROCESO_ENTREGA, $this->cadete->id);
        Sanctum::actingAs($this->admin);

        try {
            DB::transaction(function () use ($comision) {
                app(UpdateCommissionStatusUseCase::class)($comision->id, CommissionStatus::ENTREGADO);
                throw new \RuntimeException('Falla del que llamó, después del cambio de estado');
            });
            $this->fail('La transacción tenía que fallar');
        } catch (\RuntimeException) {
        }

        // Aunque el request termine, no queda nada pendiente para mandar.
        app(DeferredCallbackCollection::class)->invoke();

        $this->assertDatabaseMissing('feedback_surveys', ['commission_id' => $comision->id]);
        $this->assertSame([], $this->secuencia);
        Mail::assertNothingSent();
        $this->assertCount(0, $this->fcm->llamadas);
    }

    public function test_si_una_transaccion_que_envuelve_el_alta_se_revierte_no_se_manda_el_whatsapp_ni_el_push(): void
    {
        Sanctum::actingAs($this->admin);
        $alta = app(CreateCommissionUseCase::class);

        try {
            DB::transaction(function () use ($alta) {
                $alta(CreateCommissionDTO::fromArray($this->datosAlta()));
                throw new \RuntimeException('Falla del que llamó, después del alta');
            });
            $this->fail('La transacción tenía que fallar');
        } catch (\RuntimeException) {
        }

        app(DeferredCallbackCollection::class)->invoke();

        $this->assertDatabaseMissing('commissions', ['client_id' => $this->cliente->id]);
        $this->assertSame([], $this->secuencia);
        $this->assertCount(0, $this->fcm->llamadas);
    }

    // --- Un envío que falla después de responder no rompe nada -----------------------

    public function test_un_push_que_explota_despues_de_responder_no_rompe_la_respuesta_ni_la_encuesta(): void
    {
        // Un \Error no lo atrapa el catch (\Exception) de NotificationService: antes subía
        // hasta la transacción, revertía el ENTREGADO y el cadete veía un 500.
        $this->fcm->falla = new \Error('FCM: conexión reseteada');
        $logs = $this->capturarLogs();
        $comision = $this->comision(CommissionStatus::EN_PROCESO_ENTREGA, $this->cadete->id);
        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/commissions/{$comision->id}/status", ['status' => 'ENTREGADO'])
            ->assertOk()
            ->assertJsonPath('commission.status', 'ENTREGADO');

        $this->assertSame(CommissionStatus::ENTREGADO, $comision->fresh()->status);
        $this->assertCount(1, $this->whatsapps(), 'La encuesta sale igual');
        Mail::assertSentCount(1);

        $falla = $logs->errores->firstWhere('message', 'Falló un envío externo después de responder: push FCM de cambio de estado');
        $this->assertNotNull($falla, 'El fallo del push tiene que quedar en el log');
        $this->assertSame($comision->id, $falla['context']['commission_id']);
        $this->assertSame(\Error::class, $falla['context']['exception']);
        $this->assertSame('FCM: conexión reseteada', $falla['context']['error']);
    }

    public function test_un_whatsapp_de_alta_que_explota_se_loguea_sin_el_token_y_el_push_sale_igual(): void
    {
        $this->app->instance(WhatsAppService::class, new class extends WhatsAppService
        {
            public function sendCommissionCreatedNotification(string $phone, int $commissionId, string $customerName, ?object $commission = null): bool
            {
                // Como un error de cURL: el mensaje trae la URL con el token de la instancia.
                throw new \Error('cURL error 28 en https://api.green-api.test/waInstance1101000001/sendMessage/token-de-prueba');
            }
        });
        $logs = $this->capturarLogs();
        Sanctum::actingAs($this->admin);

        $id = $this->postJson('/api/commissions', $this->datosAlta())->assertCreated()->json('id');

        $this->assertDatabaseHas('commissions', ['id' => $id, 'client_id' => $this->cliente->id]);
        $this->assertSame([$this->cadete->id], array_column($this->fcm->llamadas, 'user_id'), 'El push a los cadetes sale igual');

        $falla = $logs->errores->firstWhere('message', 'Falló un envío externo después de responder: WhatsApp de alta de comisión');
        $this->assertNotNull($falla);
        $this->assertSame($id, $falla['context']['commission_id']);
        $this->assertStringContainsString('/sendMessage/***', $falla['context']['error']);
        foreach ($logs->todo as $linea) {
            $this->assertStringNotContainsString(self::TOKEN_GREENAPI, $linea);
        }
    }

    // --- EnviosExternos por sí solo --------------------------------------------------

    public function test_sin_transaccion_el_envio_queda_para_la_terminacion_del_request(): void
    {
        $enviados = 0;

        EnviosExternos::despuesDeResponder('prueba', function () use (&$enviados) {
            $enviados++;
        });

        $this->assertSame(0, $enviados, 'No se manda en el momento');
        app(DeferredCallbackCollection::class)->invoke();
        $this->assertSame(1, $enviados);
    }

    // --- Helpers ---------------------------------------------------------------------

    /**
     * FCM simulado: registra a quién se le mandó qué y con qué nivel de transacción.
     */
    private function fakeFcm(): object
    {
        $test = $this;

        return new class($test) extends FcmNotificationService
        {
            public array $llamadas = [];

            public ?\Throwable $falla = null;

            public function __construct(private readonly EnviosExternosDespuesDeResponderTest $test) {}

            public function sendPushToUser(int $userId, array $payload): array
            {
                $this->test->secuencia[] = ['canal' => 'fcm', 'nivel' => DB::transactionLevel()];

                if ($this->falla) {
                    throw $this->falla;
                }

                $this->llamadas[] = ['user_id' => $userId, 'payload' => $payload];

                return ['success' => true, 'sent' => 1, 'failed' => 0, 'invalid_tokens' => []];
            }
        };
    }

    private function cadeteConToken(string $nombre, ?int $branchId = null): User
    {
        $cadete = User::factory()->create([
            'name' => $nombre,
            'role' => UserRole::CADETE,
            'branch_id' => $branchId ?? $this->branch->id,
        ]);
        FcmToken::create(['user_id' => $cadete->id, 'fcm_token' => "token-fcm-{$cadete->id}", 'platform' => 'android', 'is_active' => true]);

        return $cadete;
    }

    private function comision(CommissionStatus $estado, ?int $cadeteId): Commission
    {
        return Commission::factory()->create([
            'client_id' => $this->cliente->id,
            'destination_id' => $this->destination->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'origin_location_id' => $this->origen->id,
            'destination_location_id' => $this->destino->id,
            'status' => $estado,
            'cadete_id' => $cadeteId,
            'total' => 12000,
        ]);
    }

    private function datosAlta(): array
    {
        return [
            'client_id' => $this->cliente->id,
            'date' => '2026-09-23',
            'origin' => 'ROSARIO',
            'destination' => 'SAN GENARO',
            'status' => CommissionStatus::BUSCANDO_CADETE->value,
            'origin_location_id' => $this->origen->id,
            'destination_location_id' => $this->destino->id,
            'total' => 12000,
            'notes' => 'Dejar en portería',
        ];
    }

    /**
     * @return array<int, array{chatId: string, message: string}>
     */
    private function whatsapps(): array
    {
        return Http::recorded(fn (Request $r) => str_contains($r->url(), 'api.green-api.test'))
            ->map(fn ($par) => ['chatId' => $par[0]['chatId'], 'message' => $par[0]['message']])
            ->values()
            ->all();
    }

    private function capturarLogs(): object
    {
        $logs = new class
        {
            public Collection $errores;

            public array $todo = [];
        };
        $logs->errores = collect();

        Event::listen(MessageLogged::class, function (MessageLogged $e) use ($logs) {
            $logs->todo[] = $e->message.' '.json_encode($e->context);
            if ($e->level === 'error') {
                $logs->errores->push(['message' => $e->message, 'context' => $e->context]);
            }
        });

        return $logs;
    }
}
