<?php

namespace Tests\Feature;

use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\CommissionType;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\CommissionItem;
use App\Shared\Models\CurrentAccount;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RC-541 (dentro de RC-535, "la app se tilda").
 *
 * GET /admin/collection-pool hacía 862 queries con per_page=15 y 2.338 con per_page=9999
 * (0,5-1,6 s en prod; en la copia local, 863 y 2.338). Dos causas:
 *
 * 1. La subquery de saldo se llamaba current_balance, igual que el accessor
 *    Customer::getCurrentBalanceAttribute(), que la pisa y hace su propia query. El orden
 *    por saldo, los filtros de monto y el total de las estadísticas leían ese accessor:
 *    una query por deudor, varias veces.
 * 2. Por cada cliente de la página se hacía una suma de pendientes y una carga de
 *    comisiones con 5 eager loads.
 *
 * Ahora el alias es pool_balance y pendientes y comisiones se cargan en lote: 14 queries
 * fijas. Estos tests fijan eso, y fijan el contrato del JSON que usan el Pool de Cobranza
 * y su PDF (se verificó además contra la copia de prod: 89 respuestas idénticas byte a
 * byte antes y después del cambio).
 */
class CollectionPoolTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Branch $otraSucursal;

    private Destination $destination;

    private User $admin;

    private User $cobrador;

    private User $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        // El orden por saldo usa FIELD(id, ...), que es de MySQL. SQLite no la trae: se
        // registra con la misma semántica (posición desde 1, o 0 si no está en la lista).
        DB::connection()->getPdo()->sqliteCreateFunction('FIELD', function ($valor, ...$lista) {
            $posicion = array_search($valor, $lista);

            return $posicion === false ? 0 : $posicion + 1;
        });

        $this->branch = Branch::factory()->create(['name' => 'San Genaro']);
        $this->otraSucursal = Branch::factory()->create(['name' => 'Rosario']);
        $this->destination = Destination::factory()->create();
        $this->admin = User::factory()->create(['role' => 'administrador', 'branch_id' => null]);
        $this->cobrador = User::factory()->create(['role' => 'cobrador', 'branch_id' => $this->branch->id]);
        // Un solo usuario para los movimientos: la factory crea uno por fila si no se pasa.
        $this->cliente = User::factory()->create(['role' => 'cliente']);
    }

    // --- Cantidad de queries ---

    /**
     * Caso real: en prod el listado completo (per_page=9999) hacía 2.338 queries porque
     * cada deudor sumaba queries propias. Con 3 o con 30 deudores tiene que hacer las mismas.
     */
    public function test_el_listado_hace_las_mismas_queries_con_3_que_con_30_deudores(): void
    {
        $this->deudoresConComisiones(3);
        $conTres = $this->contarQueries('/api/admin/collection-pool?per_page=9999');

        $this->deudoresConComisiones(27);
        $conTreinta = $this->contarQueries('/api/admin/collection-pool?per_page=9999');

        $this->assertSame(30, $conTreinta['total']);
        $this->assertSame($conTres['queries'], $conTreinta['queries'], 'la cantidad de queries no puede depender de los deudores');
        // 14 en MySQL; en SQLite Schema::hasColumn() hace 2 queries en vez de 1 (y se llama 2 veces).
        $this->assertLessThanOrEqual(16, $conTreinta['queries']);
    }

    /**
     * Caso real: per_page=15 era lo que más se pedía y hacía 862 queries en prod. La página
     * tiene que costar lo mismo con 15 clientes que con 2, y lo mismo en cualquier orden.
     */
    public function test_una_pagina_de_15_cuesta_lo_mismo_que_una_de_2_en_cualquier_orden(): void
    {
        $this->deudoresConComisiones(20);

        $referencia = $this->contarQueries('/api/admin/collection-pool?per_page=2')['queries'];

        foreach (['balance', 'name', 'city', 'assigned'] as $orden) {
            foreach (['asc', 'desc'] as $sentido) {
                $medicion = $this->contarQueries("/api/admin/collection-pool?per_page=15&sort_by={$orden}&sort_direction={$sentido}");
                $this->assertSame($referencia, $medicion['queries'], "sort_by={$orden} {$sentido}");
            }
        }

        $conMontos = $this->contarQueries('/api/admin/collection-pool?per_page=15&min_debt_amount=1&max_debt_amount=999999');
        $this->assertSame($referencia, $conMontos['queries'], 'los filtros de monto también leían el accessor');
    }

    /**
     * El accessor current_balance de Customer se queda como está (lo usan otros
     * endpoints), pero el pool no lo tiene que disparar: su query es un
     * "select * from current_accounts ... limit 1" por cliente.
     */
    public function test_el_listado_y_la_ficha_no_disparan_el_accessor_de_saldo(): void
    {
        $deudores = $this->deudoresConComisiones(5);

        foreach (['/api/admin/collection-pool?per_page=9999', "/api/admin/collection-pool/{$deudores[0]->id}"] as $url) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->admin, 'sanctum')->getJson($url)->assertOk();
            $consultas = collect(DB::getQueryLog())->pluck('query');
            DB::disableQueryLog();

            $delAccessor = $consultas->filter(fn ($sql) => str_starts_with($sql, 'select * from "current_accounts"'));
            $this->assertCount(0, $delAccessor, "{$url} volvió a leer el accessor current_balance");
        }
    }

    // --- Estadísticas y saldo ---

    /**
     * Las estadísticas salen de todos los deudores. Se arman los casos borde del saldo:
     * sólo cuentan movimientos OK y vivos (RC-522), un cliente dado de baja no aparece, y
     * saldo cero o positivo no es deuda.
     */
    public function test_las_estadisticas_suman_solo_la_deuda_real(): void
    {
        $asignadoA = $this->deudor(-1000, ['internal_user_id' => $this->cobrador->id]);
        $this->deudor(-2500.50);
        $this->deudor(-300, ['internal_user_id' => $this->cobrador->id]);
        $this->deudor(500);   // saldo a favor
        $this->deudor(0);     // saldo cero
        Customer::factory()->create(['branch_id' => $this->branch->id, 'user_id' => $this->cliente->id]); // sin movimientos

        // RC-522: el último movimiento OK está dado de baja; manda el anterior (-400).
        $conBaja = $this->deudor(-400);
        $this->movimiento($conBaja, -9999, now()->addDay())->delete();

        // El último movimiento está PENDIENTE: sólo cuenta el último OK (-100).
        $conPendiente = $this->deudor(-100, ['internal_user_id' => $this->cobrador->id]);
        $this->movimiento($conPendiente, -7000, now()->addDay(), 'PENDIENTE');

        // Un cliente dado de baja no está en el pool aunque deba.
        $this->deudor(-5000)->delete();

        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/collection-pool?per_page=9999')
            ->assertOk();

        $this->assertEquals([
            'total_customers_with_debt' => 5,
            'assigned_customers' => 3,
            'unassigned_customers' => 2,
            'total_debt_amount' => 4300.5,
            'assigned_percentage' => 60,
        ], $respuesta->json('statistics'));

        $saldos = collect($respuesta->json('data'))->pluck('current_balance', 'id');
        $this->assertEquals(-400, $saldos[$conBaja->id]);
        $this->assertEquals(-100, $saldos[$conPendiente->id]);
        $this->assertEquals(1000, collect($respuesta->json('data'))->firstWhere('id', $asignadoA->id)['debt_amount']);

        // Las estadísticas son de todo el pool: los filtros sólo cambian el listado.
        $filtrado = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/collection-pool?assigned=false&min_debt_amount=1000')
            ->assertOk();
        $this->assertSame(1, $filtrado->json('meta.total'));
        $this->assertEquals($respuesta->json('statistics'), $filtrado->json('statistics'));
    }

    /**
     * El orden por saldo se arma en memoria y se pasa a FIELD(). Con saldos iguales (en
     * la copia de prod hay 25 clientes que deben exactamente $14.000) desempata el id,
     * igual que antes, y el orden no cambia al paginar.
     */
    public function test_el_orden_por_saldo_desempata_por_id_y_se_mantiene_al_paginar(): void
    {
        $a = $this->deudor(-500);
        $b = $this->deudor(-2000);
        $c = $this->deudor(-500);
        $d = $this->deudor(-100);

        $this->assertSame([$b->id, $a->id, $c->id, $d->id], $this->idsPaginados('balance', 'asc'));
        $this->assertSame([$d->id, $a->id, $c->id, $b->id], $this->idsPaginados('balance', 'desc'));
        // sort_by desconocido: cae en el orden por saldo.
        $this->assertSame([$b->id, $a->id, $c->id, $d->id], $this->idsPaginados('otro', 'asc'));
    }

    public function test_los_filtros_de_monto_comparan_contra_la_deuda_en_positivo(): void
    {
        $a = $this->deudor(-500);
        $b = $this->deudor(-2000);
        $c = $this->deudor(-499.99);

        $ids = fn (string $query) => collect($this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/collection-pool?per_page=50&{$query}")
            ->assertOk()
            ->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertSame([$a->id, $b->id], $ids('min_debt_amount=500'));
        $this->assertSame([$a->id, $c->id], $ids('max_debt_amount=500'));
        $this->assertSame([$a->id], $ids('min_debt_amount=500&max_debt_amount=500'));
        $this->assertSame([], $ids('min_debt_amount=999999'));
    }

    // --- Contrato del payload ---

    /**
     * Pendientes y comisiones ahora se piden para toda la página junta: cada cliente
     * tiene que seguir recibiendo sólo lo suyo, con los mismos filtros y el mismo orden.
     */
    public function test_cada_cliente_recibe_solo_sus_pendientes_y_comisiones(): void
    {
        $vetaro = $this->deudor(-30000, ['name' => 'VETARO', 'last_name' => 'emanuel', 'internal_user_id' => $this->cobrador->id]);
        $otro = $this->deudor(-1000);

        // Pendientes: sólo créditos PENDIENTE vivos.
        $this->movimiento($vetaro, 0, now()->subDays(3), 'PENDIENTE', 'credit', 1500);
        $this->movimiento($vetaro, 0, now()->subDays(3), 'PENDIENTE', 'credit', 2500.25);
        $this->movimiento($vetaro, 0, now()->subDays(3), 'PENDIENTE', 'debit', 999);        // débito: no
        $this->movimiento($vetaro, 0, now()->subDays(9), 'OK', 'credit', 777);              // confirmado: no (y no es el último OK)
        $this->movimiento($vetaro, 0, now()->subDays(3), 'PENDIENTE', 'credit', 5000)->delete(); // dado de baja: no
        $this->movimiento($otro, 0, now()->subDays(3), 'PENDIENTE', 'credit', 50);

        // Comisiones: PAGO_VALIDACION con total > 0, de la más nueva a la más vieja.
        $vieja = $this->comision($vetaro, ['date' => now()->subDays(10)->toDateString(), 'type' => CommissionType::EXTRAORDINARIA->value]);
        $nueva = $this->comision($vetaro, ['date' => now()->subDays(1)->toDateString(), 'declared_value' => 4022872.71, 'notes' => 'bultos frágiles']);
        $this->comision($vetaro, ['status' => CommissionStatus::ENTREGADO->value]);
        $this->comision($vetaro, ['total' => 0]);
        $delOtro = $this->comision($otro);

        $data = collect($this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/collection-pool?per_page=15')
            ->assertOk()
            ->json('data'))->keyBy('id');

        $enVetaro = $data[$vetaro->id];
        $this->assertEquals(4000.25, $enVetaro['pending']);
        $this->assertEquals(50, $data[$otro->id]['pending']);
        $this->assertSame([$nueva->id, $vieja->id], array_column($enVetaro['commissions'], 'id'));
        $this->assertSame(2, $enVetaro['commissions_count']);
        $this->assertSame([$delOtro->id], array_column($data[$otro->id]['commissions'], 'id'));

        $comision = $enVetaro['commissions'][0];
        $this->assertSame($vetaro->id, $comision['client_id']);
        // RC-531: el modal de edición se abre con el cliente y el valor declarado.
        $this->assertSame(['id' => $vetaro->id, 'name' => 'VETARO emanuel', 'dni' => $vetaro->dni], $comision['client']);
        $this->assertSame('4022872.71', $comision['declared_value']);
        $this->assertSame('bultos frágiles', $comision['notes']);
        $this->assertCount(2, $comision['items']);
        $this->assertSame(['id' => $this->branch->id, 'name' => 'San Genaro'], $comision['branch']);
        $this->assertSame('EXTRAORDINARIA', $enVetaro['commissions'][1]['type']);
        $this->assertSame(['id' => $this->cobrador->id, 'name' => $this->cobrador->name, 'email' => $this->cobrador->email], $enVetaro['internal_user']);
        $this->assertTrue($enVetaro['is_assigned']);
        $this->assertFalse($data[$otro->id]['is_assigned']);
        $this->assertNull($data[$otro->id]['internal_user']);
    }

    /**
     * La forma del JSON que lee PoolCobranza.jsx (tabla, pestañas y PDF).
     */
    public function test_la_respuesta_mantiene_su_forma(): void
    {
        $this->deudoresConComisiones(2);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/collection-pool?per_page=15')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Clientes con deuda obtenidos correctamente')
            ->assertJsonStructure([
                'success', 'message',
                'data' => [[
                    'id', 'dni', 'name', 'last_name', 'full_name', 'email', 'mobile', 'phone',
                    'address', 'city', 'branch' => ['id', 'name'], 'internal_user_id',
                    'is_assigned', 'internal_user', 'current_balance', 'debt_amount', 'pending',
                    'commissions' => [[
                        'id', 'client_id', 'date', 'status', 'status_label', 'type', 'type_label',
                        'total', 'payment_method', 'payment_method_label', 'origin', 'destination',
                        'origin_location_id', 'destination_location_id',
                        'origin_location' => ['id', 'name', 'address', 'city', 'phone'],
                        'destination_location' => ['id', 'name', 'address', 'city', 'phone'],
                        'items' => [['id', 'type', 'size', 'quantity', 'detail', 'unit_price', 'subtotal']],
                        'branch' => ['id', 'name'], 'notes', 'client' => ['id', 'name', 'dni'],
                        'declared_value', 'created_at', 'updated_at',
                    ]],
                    'commissions_count',
                ]],
                'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
                'statistics' => [
                    'total_customers_with_debt', 'assigned_customers', 'unassigned_customers',
                    'total_debt_amount', 'assigned_percentage',
                ],
            ]);
    }

    /**
     * show() dice mantener "la misma estructura que index": la ficha de cada deudor tiene
     * que ser exactamente el ítem del listado (el PDF toma el encabezado de la ficha).
     */
    public function test_la_ficha_devuelve_lo_mismo_que_el_listado(): void
    {
        $deudores = $this->deudoresConComisiones(3);
        $this->movimiento($deudores[1], 0, now()->subDays(2), 'PENDIENTE', 'credit', 1234.5);

        $listado = collect($this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/collection-pool?per_page=15')
            ->assertOk()
            ->json('data'))->keyBy('id');

        foreach ($deudores as $deudor) {
            $ficha = $this->actingAs($this->admin, 'sanctum')
                ->getJson("/api/admin/collection-pool/{$deudor->id}")
                ->assertOk()
                ->assertJsonPath('message', 'Cliente obtenido correctamente')
                ->json('data');

            $this->assertEquals($listado[$deudor->id], $ficha, "la ficha de {$deudor->id} difiere del listado");
        }
    }

    public function test_la_ficha_de_un_cliente_sin_deuda_y_los_404(): void
    {
        $sinMovimientos = Customer::factory()->create(['branch_id' => $this->branch->id, 'user_id' => $this->cliente->id]);
        $aFavor = $this->deudor(800);
        $dadoDeBaja = $this->deudor(-100);
        $dadoDeBaja->delete();

        foreach ([$sinMovimientos, $aFavor] as $cliente) {
            $ficha = $this->actingAs($this->admin, 'sanctum')
                ->getJson("/api/admin/collection-pool/{$cliente->id}")
                ->assertOk()
                ->json('data');
            $this->assertSame($cliente->id, $ficha['id']);
            $this->assertEquals($cliente->id === $aFavor->id ? 800 : 0, $ficha['current_balance']);
            $this->assertSame([], $ficha['commissions']);
            $this->assertSame(0, $ficha['commissions_count']);
        }

        $this->actingAs($this->admin, 'sanctum')->getJson("/api/admin/collection-pool/{$dadoDeBaja->id}")
            ->assertStatus(404)->assertJsonPath('message', 'Cliente no encontrado');
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/collection-pool/999999')
            ->assertStatus(404);
    }

    /**
     * La ficha tampoco depende de cuántas comisiones tenga el cliente.
     */
    public function test_la_ficha_hace_las_mismas_queries_con_1_o_con_12_comisiones(): void
    {
        // Los dos asignados: sin asignar, Laravel se ahorra la query de internalUser.
        $uno = $this->deudor(-100, ['internal_user_id' => $this->cobrador->id]);
        $doce = $this->deudor(-200, ['internal_user_id' => $this->cobrador->id]);
        $this->comision($uno);
        foreach (range(1, 12) as $i) {
            $this->comision($doce, ['date' => now()->subDays($i)->toDateString()]);
        }

        $conUna = $this->contarQueries("/api/admin/collection-pool/{$uno->id}")['queries'];
        $conDoce = $this->contarQueries("/api/admin/collection-pool/{$doce->id}")['queries'];

        $this->assertSame($conUna, $conDoce);
        // 12 en MySQL (antes 15); en SQLite Schema::hasColumn() suma 2.
        $this->assertLessThanOrEqual(14, $conDoce);
    }

    // --- Permisos, sucursal y bordes ---

    public function test_el_cobrador_ve_solo_los_deudores_de_su_sucursal(): void
    {
        $propio = $this->deudor(-100);
        $ajeno = $this->deudor(-900, ['branch_id' => $this->otraSucursal->id]);

        $respuesta = $this->actingAs($this->cobrador, 'sanctum')
            ->getJson("/api/admin/collection-pool?per_page=15&branch_id={$this->otraSucursal->id}")
            ->assertOk();

        $this->assertSame([$propio->id], collect($respuesta->json('data'))->pluck('id')->all());
        // Las estadísticas son las del pool entero, como antes.
        $this->assertSame(2, $respuesta->json('statistics.total_customers_with_debt'));

        $comoAdmin = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/collection-pool?per_page=15&branch_id={$this->otraSucursal->id}")
            ->assertOk();
        $this->assertSame([$ajeno->id], collect($comoAdmin->json('data'))->pluck('id')->all());
    }

    public function test_sin_deudores_o_fuera_de_rango_devuelve_listas_vacias(): void
    {
        $this->deudor(500);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/collection-pool?per_page=15')
            ->assertOk()
            ->assertJsonPath('message', 'No se encontraron clientes con deuda')
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);

        $this->deudor(-100);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/collection-pool?per_page=15&page=5')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('statistics.total_customers_with_debt', 1);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/collection-pool?per_page=15&search=zzzzqqq')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    public function test_un_cadete_no_accede_al_pool(): void
    {
        $cadete = User::factory()->create(['role' => 'cadete', 'branch_id' => $this->branch->id]);
        $deudor = $this->deudor(-100);

        $this->actingAs($cadete, 'sanctum')->getJson('/api/admin/collection-pool')->assertStatus(403);
        $this->actingAs($cadete, 'sanctum')->getJson("/api/admin/collection-pool/{$deudor->id}")->assertStatus(403);
    }

    // --- Helpers ---

    /**
     * Crea un cliente de la sucursal cuyo último movimiento OK deja el saldo indicado.
     */
    private function deudor(float $saldo, array $atributos = []): Customer
    {
        $cliente = Customer::factory()->create(array_merge([
            'branch_id' => $this->branch->id,
            'user_id' => $this->cliente->id,
        ], $atributos));

        $this->movimiento($cliente, $saldo, now()->subDays(5));

        return $cliente;
    }

    private function movimiento(
        Customer $cliente,
        float $saldo,
        $fecha,
        string $estado = 'OK',
        string $tipo = 'debit',
        float $monto = 100,
    ): CurrentAccount {
        return CurrentAccount::factory()->create([
            'customer_id' => $cliente->id,
            'type' => $tipo,
            'amount' => $monto,
            'balance' => $saldo,
            'status' => $estado,
            'transaction_date' => $fecha,
            'user_id' => $this->cliente->id,
        ]);
    }

    private function comision(Customer $cliente, array $atributos = []): Commission
    {
        $comision = Commission::factory()->create(array_merge([
            'client_id' => $cliente->id,
            'destination_id' => $this->destination->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'status' => CommissionStatus::PAGO_VALIDACION->value,
            'type' => CommissionType::ORDINARIA->value,
            'total' => 14000,
        ], $atributos));

        CommissionItem::factory()->count(2)->create(['commission_id' => $comision->id]);

        return $comision;
    }

    /**
     * Deudores con saldos distintos, la mitad asignados, cada uno con comisiones con
     * ítems y un crédito pendiente: todo lo que antes costaba queries por cliente.
     *
     * @return array<int, Customer>
     */
    private function deudoresConComisiones(int $cantidad, int $comisiones = 2): array
    {
        $deudores = [];
        $base = Customer::count();
        for ($i = 1; $i <= $cantidad; $i++) {
            $n = $base + $i;
            $deudor = $this->deudor(-1000 * $n, [
                'city' => $n % 2 ? 'SAN GENARO' : 'ROSARIO',
                'internal_user_id' => $n % 2 ? $this->cobrador->id : null,
            ]);
            foreach (range(1, $comisiones) as $j) {
                $this->comision($deudor, ['date' => now()->subDays($j)->toDateString()]);
            }
            $this->movimiento($deudor, 0, now()->subDay(), 'PENDIENTE', 'credit', 250);
            $deudores[] = $deudor;
        }

        return $deudores;
    }

    /**
     * @return array{queries: int, total: int|null}
     */
    private function contarQueries(string $url): array
    {
        $this->actingAs($this->admin, 'sanctum');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $respuesta = $this->getJson($url)->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return ['queries' => $queries, 'total' => $respuesta->json('meta.total')];
    }

    private function idsPaginados(string $orden, string $sentido): array
    {
        $ids = [];
        foreach ([1, 2] as $pagina) {
            $ids = array_merge($ids, collect($this->actingAs($this->admin, 'sanctum')
                ->getJson("/api/admin/collection-pool?per_page=2&page={$pagina}&sort_by={$orden}&sort_direction={$sentido}")
                ->assertOk()
                ->json('data'))->pluck('id')->all());
        }

        return $ids;
    }
}
