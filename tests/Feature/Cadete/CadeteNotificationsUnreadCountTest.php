<?php

namespace Tests\Feature\Cadete;

use App\Notification;
use App\Services\NotificationService;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * RC-548: badge de no leídas y listado de notificaciones de la app de cadetes.
 *
 * GET /cadete/notifications/unread-count contaba con count() sobre todas las no leídas
 * hidratadas con 3 relaciones: 4,9-9 s y 57-112 MB por llamada en prod (cadetes 15, 16 y
 * 17, con 5.293 a 10.769 no leídas), pedido cada 60 s por teléfono (31% del tráfico de la
 * app). Ahora cuenta en la base. Estos tests fijan que el número y la forma del JSON son
 * los mismos de antes, y que ya no se cargan modelos para contar.
 */
class CadeteNotificationsUnreadCountTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRACION_INDICE = 'database/migrations/2026_09_24_120548_add_user_id_created_at_index_to_notifications_table.php';

    private User $cadete;

    private User $otroCadete;

    private Commission $commission;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::factory()->create();

        $this->cadete = User::factory()->create([
            'role' => UserRole::CADETE,
            'branch_id' => $branch->id,
        ]);

        $this->otroCadete = User::factory()->create([
            'role' => UserRole::CADETE,
            'branch_id' => $branch->id,
        ]);

        $this->commission = Commission::factory()->create([
            'client_id' => Customer::factory()->create()->id,
            'destination_id' => Destination::factory()->create()->id,
            'branch_id' => $branch->id,
            'cadete_id' => $this->cadete->id,
            'origin_location_id' => Location::factory()->create()->id,
            'destination_location_id' => Location::factory()->create()->id,
            'status' => CommissionStatus::CADETE_ASIGNADO,
        ]);
    }

    /**
     * Caso real: el cadete 15 de prod tiene 31.299 notificaciones, 7.806 sin leer, y
     * comparte la tabla con otros cadetes. El badge tiene que contar sólo las suyas y
     * sólo las no leídas.
     */
    public function test_unread_count_cuenta_solo_las_no_leidas_del_cadete_autenticado(): void
    {
        $this->crearNotificaciones($this->cadete, 3, leidas: false);
        $this->crearNotificaciones($this->cadete, 2, leidas: true);
        $this->crearNotificaciones($this->otroCadete, 4, leidas: false);

        Sanctum::actingAs($this->cadete);

        $this->getJson('/api/cadete/notifications/unread-count')
            ->assertOk()
            ->assertExactJson(['success' => true, 'unread_count' => 3]);
    }

    /**
     * El conteo nuevo tiene que dar exactamente lo mismo que el viejo
     * (count(getUnreadNotifications())) para todas las combinaciones que hay en prod:
     * mezcla de leídas y no leídas (cadete 15), todas sin leer (cadete 16: 10.769 de
     * 10.769), todas leídas y ninguna notificación (cadete 11).
     */
    public function test_el_conteo_coincide_con_el_de_antes_en_todas_las_combinaciones(): void
    {
        $todasLeidas = User::factory()->create(['role' => UserRole::CADETE]);
        $sinNotificaciones = User::factory()->create(['role' => UserRole::CADETE]);

        $this->crearNotificaciones($this->cadete, 5, leidas: false);
        $this->crearNotificaciones($this->cadete, 3, leidas: true);
        $this->crearNotificaciones($this->otroCadete, 6, leidas: false);
        $this->crearNotificaciones($todasLeidas, 4, leidas: true);

        $service = app(NotificationService::class);

        $esperados = [
            $this->cadete->id => 5,
            $this->otroCadete->id => 6,
            $todasLeidas->id => 0,
            $sinNotificaciones->id => 0,
        ];

        foreach ($esperados as $userId => $esperado) {
            $antes = count($service->getUnreadNotifications($userId));
            $ahora = $service->countUnreadNotifications($userId);

            $this->assertSame($esperado, $antes, "conteo viejo del usuario {$userId}");
            $this->assertSame($antes, $ahora, "el conteo nuevo difiere del viejo para el usuario {$userId}");

            Sanctum::actingAs(User::find($userId));
            $this->assertSame(
                $antes,
                $this->getJson('/api/cadete/notifications/unread-count')->assertOk()->json('unread_count'),
                "el endpoint difiere del conteo viejo para el usuario {$userId}"
            );
        }
    }

    /**
     * La app parsea la respuesta con `json['unread_count'] as int`
     * (ryr_cadetes_app/lib/models/notification.dart): si llegara como string o con otra
     * clave, el badge rompe. La respuesta tiene que seguir siendo exactamente
     * {"success": true, "unread_count": <int>}, también en cero.
     */
    public function test_la_respuesta_mantiene_la_forma_exacta_y_el_tipo_entero(): void
    {
        Sanctum::actingAs($this->cadete);

        $vacia = $this->getJson('/api/cadete/notifications/unread-count')->assertOk();
        $vacia->assertExactJson(['success' => true, 'unread_count' => 0]);
        $this->assertSame(0, $vacia->json('unread_count'));

        $this->crearNotificaciones($this->cadete, 2, leidas: false);

        $conDatos = $this->getJson('/api/cadete/notifications/unread-count')->assertOk();
        $conDatos->assertExactJson(['success' => true, 'unread_count' => 2]);
        $this->assertSame(2, $conDatos->json('unread_count'));
        $this->assertSame(['success', 'unread_count'], array_keys($conDatos->json()));
    }

    /**
     * La mejora en sí: antes el endpoint hacía 5 queries e hidrataba 13.916 modelos para
     * el cadete 16 (notificaciones + comisiones + clientes + ubicaciones), 112 MB. Ahora
     * tiene que ser un único COUNT sobre notifications, sin cargar ningún modelo.
     */
    public function test_contar_no_hidrata_notificaciones_ni_sus_relaciones(): void
    {
        $this->crearNotificaciones($this->cadete, 25, leidas: false);
        $this->crearNotificaciones($this->cadete, 5, leidas: true);

        Sanctum::actingAs($this->cadete);

        $modelosCargados = [];
        Event::listen('eloquent.retrieved: *', function (string $evento) use (&$modelosCargados) {
            $modelosCargados[] = substr($evento, strlen('eloquent.retrieved: '));
        });

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/cadete/notifications/unread-count')
            ->assertOk()
            ->assertExactJson(['success' => true, 'unread_count' => 25]);

        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $this->assertSame([], $modelosCargados, 'contar no debería hidratar modelos');

        $sobreNotificaciones = array_values(array_filter($queries, fn ($q) => str_contains($q, 'notifications')));
        $this->assertCount(1, $sobreNotificaciones, 'debería ser una sola query a notifications');
        $this->assertStringContainsString('count(*)', $sobreNotificaciones[0]);

        foreach (['commissions', 'customers', 'locations'] as $tabla) {
            $this->assertEmpty(
                array_filter($queries, fn ($q) => str_contains($q, "\"{$tabla}\"") || str_contains($q, "`{$tabla}`")),
                "no debería consultar {$tabla} para contar"
            );
        }
    }

    /**
     * El badge tiene que seguir el ritmo de lo que hace el cadete en la app: al abrir una
     * notificación baja en uno y con "marcar todas" queda en cero.
     */
    public function test_el_conteo_refleja_marcar_una_y_marcar_todas_como_leidas(): void
    {
        $notificaciones = $this->crearNotificaciones($this->cadete, 3, leidas: false);
        $this->crearNotificaciones($this->otroCadete, 2, leidas: false);

        Sanctum::actingAs($this->cadete);

        $this->patchJson("/api/cadete/notifications/{$notificaciones[0]->id}/mark-as-read")->assertOk();
        $this->getJson('/api/cadete/notifications/unread-count')
            ->assertExactJson(['success' => true, 'unread_count' => 2]);

        $this->patchJson('/api/cadete/notifications/mark-all-as-read')->assertOk();
        $this->getJson('/api/cadete/notifications/unread-count')
            ->assertExactJson(['success' => true, 'unread_count' => 0]);

        // Las del otro cadete no se tocan.
        $this->assertSame(2, app(NotificationService::class)->countUnreadNotifications($this->otroCadete->id));
    }

    /**
     * El acceso no cambia: sin token es 401 y un administrador recibe 403 del
     * CadeteMiddleware, igual que antes.
     */
    public function test_unread_count_sigue_restringido_a_cadetes(): void
    {
        $this->getJson('/api/cadete/notifications/unread-count')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::ADMINISTRADOR]));
        $this->getJson('/api/cadete/notifications/unread-count')->assertForbidden();
    }

    /**
     * Listado de la app (limit 20 + offset, scroll infinito). En prod hay cientos de
     * notificaciones creadas en el mismo segundo (282 grupos sólo en el cadete 16): el
     * orden sigue siendo created_at desc y los empates salen por id desc, así que las
     * páginas no repiten ni saltean notificaciones. La forma de cada ítem no cambia.
     */
    public function test_el_listado_pagina_por_fecha_desc_sin_repetir_ni_saltear_empates(): void
    {
        $base = Carbon::parse('2026-09-15 13:44:00');

        $vieja = $this->crearNotificacion($this->cadete, $base->copy()->subHour());
        $empate1 = $this->crearNotificacion($this->cadete, $base);
        $empate2 = $this->crearNotificacion($this->cadete, $base);
        $empate3 = $this->crearNotificacion($this->cadete, $base);
        $nueva = $this->crearNotificacion($this->cadete, $base->copy()->addHour());
        $this->crearNotificacion($this->otroCadete, $base->copy()->addHours(2));

        Sanctum::actingAs($this->cadete);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $ids = [];
        foreach ([0, 2, 4] as $offset) {
            $respuesta = $this->getJson("/api/cadete/notifications?limit=2&offset={$offset}&unread_only=false")
                ->assertOk()
                ->assertJsonStructure([
                    'success',
                    'count',
                    'data' => [[
                        'id', 'user_id', 'commission_id', 'type', 'title', 'message', 'data',
                        'is_read', 'read_at', 'created_at', 'updated_at',
                        'commission' => ['id', 'client', 'origin_location', 'destination_location'],
                    ]],
                ]);

            $this->assertSame(count($respuesta->json('data')), $respuesta->json('count'));
            $ids = array_merge($ids, array_column($respuesta->json('data'), 'id'));
        }

        $this->assertSame(
            [$nueva->id, $empate3->id, $empate2->id, $empate1->id, $vieja->id],
            $ids
        );

        // SQLite ya devuelve los empates por id al recorrer el índice, así que el orden solo
        // no alcanza para detectar si se pierde el desempate: se fija también en el SQL,
        // que es lo que en MySQL evita depender del plan que elija el optimizador.
        $listado = array_values(array_filter(
            array_column(DB::getQueryLog(), 'query'),
            fn ($q) => str_contains($q, 'from "notifications"')
        ));
        DB::disableQueryLog();

        $this->assertCount(3, $listado);
        $this->assertStringContainsString('order by "created_at" desc, "id" desc', $listado[0]);
    }

    /**
     * La migración agrega (user_id, created_at) para el listado sin sacar el
     * (user_id, is_read) que usa el conteo, y es segura de correr dos veces (por si en
     * algún entorno el índice ya se creó a mano) y de revertir.
     */
    public function test_la_migracion_agrega_el_indice_y_es_idempotente_y_reversible(): void
    {
        $this->assertTrue(Schema::hasIndex('notifications', ['user_id', 'created_at']));
        $this->assertTrue(Schema::hasIndex('notifications', ['user_id', 'is_read']));

        $migracion = require base_path(self::MIGRACION_INDICE);

        $migracion->up();
        $this->assertCount(1, $this->indicesSobre(['user_id', 'created_at']));

        $migracion->down();
        $this->assertFalse(Schema::hasIndex('notifications', ['user_id', 'created_at']));
        $this->assertTrue(Schema::hasIndex('notifications', ['user_id', 'is_read']));

        $migracion->down();
        $migracion->up();
        $this->assertCount(1, $this->indicesSobre(['user_id', 'created_at']));
    }

    /**
     * @return array<int, Notification>
     */
    private function crearNotificaciones(User $user, int $cantidad, bool $leidas): array
    {
        $creadas = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $creadas[] = Notification::create([
                'user_id' => $user->id,
                'commission_id' => $this->commission->id,
                'type' => 'commission_status_change',
                'title' => 'Comisión entregada',
                'message' => "Comisión #{$this->commission->id}: Entregado",
                'data' => ['commission_id' => $this->commission->id],
                'is_read' => $leidas,
                'read_at' => $leidas ? now() : null,
            ]);
        }

        return $creadas;
    }

    private function crearNotificacion(User $user, Carbon $creadaEl): Notification
    {
        $notificacion = Notification::create([
            'user_id' => $user->id,
            'commission_id' => $this->commission->id,
            'type' => 'commission_status_change',
            'title' => 'Encomienda retirada',
            'message' => "Comisión #{$this->commission->id}: Retirada",
            'data' => ['commission_id' => $this->commission->id],
        ]);

        // created_at no es fillable: se fija a mano para armar empates en el mismo segundo.
        $notificacion->forceFill(['created_at' => $creadaEl, 'updated_at' => $creadaEl])->saveQuietly();

        return $notificacion;
    }

    /**
     * @param  array<int, string>  $columnas
     * @return array<int, array<string, mixed>>
     */
    private function indicesSobre(array $columnas): array
    {
        return array_values(array_filter(
            Schema::getIndexes('notifications'),
            fn (array $indice) => $indice['columns'] === $columnas
        ));
    }
}
