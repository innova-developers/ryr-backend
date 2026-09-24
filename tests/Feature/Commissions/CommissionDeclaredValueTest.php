<?php

namespace Tests\Feature\Commissions;

use App\Shared\Enums\CommissionItemSize;
use App\Shared\Enums\CommissionItemType;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\IvaStatus;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\CurrentAccount;
use App\Shared\Models\Customer;
use App\Shared\Models\CustomerRate;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RC-531 (TARIFA ESPECIAL: VALOR DECLARADO).
 *
 * Matías, 21/09/2026: VETARO tiene tarifa especial Base $0, Bulto chico $0, Bulto
 * grande $0 y 3% sobre valor declarado, con IVA siempre. Al editar la comisión #55711
 * no había dónde cargar el valor declarado ($4.022.872,71): puesto en el precio
 * unitario, el sistema lo cobraba al 100% como precio del bulto, y escrito en las notas
 * no lo usaba nadie. Lo esperado es 3% = $120.686,18 + IVA 21% $25.344,10 = $146.030,28.
 *
 * La columna del porcentaje existía desde RC-484 y el resolver ya sabía sumarlo, pero
 * la comisión no guardaba el valor declarado y el cálculo lo recibía siempre en 0.
 *
 * El modal mostraba además los $11.000 de base de la tabla general, que VETARO no paga:
 * mostrador bajaba el precio del bulto para que la pantalla diera bien y 6 comisiones
 * quedaron $66.003,66 abajo. Por eso también se cubre el Pool de Cobranza, que abre el
 * mismo modal con otro payload y con usuarios cobradores.
 */
class CommissionDeclaredValueTest extends TestCase
{
    use RefreshDatabase;

    private const VALOR_DECLARADO_55711 = 4022872.71;

    private User $user;

    private Customer $vetaro;

    private Destination $rosarioSanGenaro;

    private Location $originLocation;

    private Location $destinationLocation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'administrador']);
        Branch::factory()->create();

        // IVA "siempre", como lo describe la card.
        $this->vetaro = Customer::factory()->create([
            'name' => 'VETARO',
            'last_name' => '(emanuel)',
            'iva_status' => IvaStatus::ALWAYS->value,
            'auto_calculate_iva' => true,
        ]);

        // Los precios reales del destino #499 de producción: la tabla general cobra
        // base y bultos, que VETARO tiene que NO pagar.
        $this->rosarioSanGenaro = Destination::factory()->create([
            'origin' => 'ROSARIO',
            'destination' => 'SAN GENARO',
            'fixed_price' => 11000,
            'small_bulk_price' => 3000,
            'large_bulk_price' => 6000,
        ]);

        // La tarifa #15 de producción, tal cual.
        CustomerRate::create([
            'customer_id' => $this->vetaro->id,
            'destination_id' => null,
            'fixed_price' => 0,
            'small_bulk_price' => 0,
            'large_bulk_price' => 0,
            'agreement_price' => null,
            'declared_value_percentage' => 3,
        ]);

        $this->originLocation = Location::factory()->create();
        $this->destinationLocation = Location::factory()->create();

        $this->actingAs($this->user);
    }

    private function payload(Customer $customer, float $unitPrice, float $total, array $extra = []): array
    {
        return array_merge([
            'client_id' => $customer->id,
            'date' => '2026-09-18',
            'origin' => $this->rosarioSanGenaro->origin,
            'destination' => $this->rosarioSanGenaro->destination,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'items' => [
                [
                    'type' => CommissionItemType::ORDINARIA->value,
                    'size' => CommissionItemSize::SMALL->value,
                    'quantity' => 1,
                    'unit_price' => $unitPrice,
                    'subtotal' => $unitPrice,
                ],
            ],
            'total' => $total,
            'notes' => 'RETIRAR PEDIDO PARA ANGEL BIANCHI (son 3 tambores + 4 baldes )',
        ], $extra);
    }

    private function crear(array $payload): Commission
    {
        $response = $this->postJson('/api/commissions', $payload);
        $response->assertStatus(201);

        return Commission::findOrFail($response->json('id'));
    }

    private function facturar(Commission $commission): void
    {
        $this->patchJson("/api/commissions/{$commission->id}/status", [
            'status' => CommissionStatus::PAGO_VALIDACION->value,
        ])->assertStatus(200);
    }

    private function debitoDe(Commission $commission): ?CurrentAccount
    {
        return CurrentAccount::where('reference', "COM-{$commission->id}")->first();
    }

    // --- El caso reportado ---

    public function test_editar_la_55711_con_valor_declarado_da_146030_28_con_iva(): void
    {
        // La comisión existía con el valor cargado en el precio unitario, que es lo
        // que se hizo en producción antes de reportarlo.
        $commission = $this->crear($this->payload($this->vetaro, self::VALOR_DECLARADO_55711, self::VALOR_DECLARADO_55711));

        // El modal ahora manda el bulto con el precio de la tarifa (0) y el valor
        // declarado en su propio campo.
        $this->putJson("/api/commissions/{$commission->id}", $this->payload($this->vetaro, 0, 0, [
            'declared_value' => self::VALOR_DECLARADO_55711,
        ]))->assertStatus(200)
            ->assertJsonPath('declared_value', '4022872.71');

        $commission->refresh();

        $this->assertEqualsWithDelta(146030.28, (float) $commission->total, 0.001, 'valor declarado × 3% + IVA 21%');
        $this->assertEqualsWithDelta(25344.10, (float) $commission->iva_amount, 0.001);
        $this->assertTrue((bool) $commission->iva_applied);
        $this->assertEqualsWithDelta(self::VALOR_DECLARADO_55711, (float) $commission->declared_value, 0.001);
    }

    public function test_alta_con_valor_declarado_da_146030_28_aunque_el_formulario_mande_otro_total(): void
    {
        // El formulario cotizaba con la tabla general: $11.000 de base + el valor
        // declarado como bulto = $4.033.872,71, que es lo que muestra la captura.
        // Con valor declarado y tarifa con porcentaje, el total lo calcula el backend.
        $commission = $this->crear($this->payload($this->vetaro, 0, 4033872.71, [
            'declared_value' => self::VALOR_DECLARADO_55711,
        ]));

        $this->assertEqualsWithDelta(146030.28, (float) $commission->total, 0.001);
        $this->assertEqualsWithDelta(25344.10, (float) $commission->iva_amount, 0.001);
        $this->assertEqualsWithDelta(self::VALOR_DECLARADO_55711, (float) $commission->declared_value, 0.001);
    }

    public function test_comision_rapida_sin_items_con_valor_declarado_queda_cotizada(): void
    {
        // La comisión rápida se da de alta sin ítems y con total 0.
        $payload = $this->payload($this->vetaro, 0, 0, ['declared_value' => self::VALOR_DECLARADO_55711]);
        $payload['items'] = [];

        $commission = $this->crear($payload);

        $this->assertEqualsWithDelta(146030.28, (float) $commission->total, 0.001);
    }

    // --- Edición y cuenta corriente ---

    public function test_editar_el_valor_declarado_recalcula_el_total_y_el_debito_de_cuenta_corriente(): void
    {
        // $1.000.000 × 3% = $30.000 + IVA = $36.300
        $commission = $this->crear($this->payload($this->vetaro, 0, 0, ['declared_value' => 1000000]));
        $this->assertEqualsWithDelta(36300, (float) $commission->total, 0.001);

        $this->facturar($commission);
        $this->assertEqualsWithDelta(36300, (float) $this->debitoDe($commission)->amount, 0.001);

        $this->putJson("/api/commissions/{$commission->id}", $this->payload($this->vetaro, 0, 0, [
            'status' => CommissionStatus::PAGO_VALIDACION->value,
            'declared_value' => self::VALOR_DECLARADO_55711,
        ]))->assertStatus(200);

        $commission->refresh();
        $debito = $this->debitoDe($commission);

        $this->assertEqualsWithDelta(146030.28, (float) $commission->total, 0.001);
        $this->assertNotNull($debito);
        $this->assertEqualsWithDelta(146030.28, (float) $debito->amount, 0.001, 'el débito tiene que seguir al total con IVA');
    }

    public function test_si_la_edicion_no_manda_el_valor_declarado_se_conserva(): void
    {
        // La app de cadetes edita los ítems sin conocer el valor declarado: si eso lo
        // borrara, la comisión volvería a $0 apenas el cadete carga los bultos.
        $commission = $this->crear($this->payload($this->vetaro, 0, 0, ['declared_value' => self::VALOR_DECLARADO_55711]));

        $payload = $this->payload($this->vetaro, 0, 0);
        $payload['items'][0]['quantity'] = 7;
        $this->putJson("/api/commissions/{$commission->id}", $payload)->assertStatus(200);

        $commission->refresh();

        $this->assertEqualsWithDelta(self::VALOR_DECLARADO_55711, (float) $commission->declared_value, 0.001);
        $this->assertEqualsWithDelta(146030.28, (float) $commission->total, 0.001);
    }

    public function test_mandar_el_valor_declarado_vacio_lo_borra_y_deja_de_cobrar_el_porcentaje(): void
    {
        $commission = $this->crear($this->payload($this->vetaro, 0, 0, ['declared_value' => self::VALOR_DECLARADO_55711]));

        $this->putJson("/api/commissions/{$commission->id}", $this->payload($this->vetaro, 0, 0, [
            'declared_value' => null,
        ]))->assertStatus(200);

        $commission->refresh();

        $this->assertNull($commission->declared_value);
        $this->assertEqualsWithDelta(0, (float) $commission->total, 0.001);
    }

    public function test_el_put_de_la_app_de_cadetes_conserva_el_valor_declarado_y_el_porcentaje(): void
    {
        // Body exacto de fast_commission_service.dart::updateCommission: sólo los ítems
        // (con el precio en 0, lo completa el backend) y total 0. No manda recorrido, ni
        // cliente, ni valor declarado.
        $commission = $this->crear($this->payload($this->vetaro, 0, 0, ['declared_value' => self::VALOR_DECLARADO_55711]));
        $this->facturar($commission);

        $cadete = User::factory()->create(['role' => 'cadete']);
        $this->actingAs($cadete)->putJson("/api/commissions/{$commission->id}", [
            'items' => [
                ['type' => 'ORDINARIA', 'size' => 'CHICO', 'quantity' => 3, 'unit_price' => 0, 'subtotal' => 0],
            ],
            'total' => 0,
        ])->assertStatus(200);

        $commission->refresh();

        $this->assertEqualsWithDelta(self::VALOR_DECLARADO_55711, (float) $commission->declared_value, 0.001);
        $this->assertEqualsWithDelta(146030.28, (float) $commission->total, 0.001, 'el bulto de VETARO vale $0 y el 3% sigue');
        $this->assertEqualsWithDelta(146030.28, (float) $this->debitoDe($commission)->amount, 0.001);
        $this->assertSame(3, (int) $commission->items()->first()->quantity);
    }

    public function test_valor_declarado_en_cero_no_cobra_porcentaje(): void
    {
        // El formulario manda 0 si alguien escribe "0": no es un valor declarado.
        $commission = $this->crear($this->payload($this->vetaro, 0, 0, ['declared_value' => 0]));
        $this->assertEqualsWithDelta(0, (float) $commission->total, 0.001);

        $this->putJson("/api/commissions/{$commission->id}", $this->payload($this->vetaro, 0, 0, ['declared_value' => 0]))
            ->assertStatus(200);

        $commission->refresh();
        $this->assertEqualsWithDelta(0, (float) $commission->total, 0.001);
        $this->assertEqualsWithDelta(0, (float) $commission->iva_amount, 0.001);
    }

    public function test_en_la_55711_el_precio_manual_del_bulto_se_suma_al_porcentaje(): void
    {
        // La #55711 tal cual está en producción: VETARO en IVA automático y el bulto con
        // el precio cargado a mano ($135.026,65 = valor declarado × 3% × 1,21 − $11.000).
        // El backend respeta un precio unitario mayor a 0, así que si sólo se agrega el
        // valor declarado se cobran las dos cosas: 135.026,65 + 120.686,18. Para corregir
        // las comisiones viejas de VETARO hay que poner el bulto en $0 (el modal avisa y
        // ofrece "Usar precio de tarifa").
        $this->vetaro->update(['iva_status' => IvaStatus::AUTO->value]);
        $commission = $this->crear($this->payload($this->vetaro, 135026.65, 135026.65));
        $this->assertEqualsWithDelta(135026.65, (float) $commission->total, 0.001);

        $this->putJson("/api/commissions/{$commission->id}", $this->payload($this->vetaro, 135026.65, 135026.65, [
            'declared_value' => self::VALOR_DECLARADO_55711,
        ]))->assertStatus(200);
        $this->assertEqualsWithDelta(255712.83, (float) $commission->fresh()->total, 0.001, 'precio manual + 3%');

        $this->putJson("/api/commissions/{$commission->id}", $this->payload($this->vetaro, 0, 0, [
            'declared_value' => self::VALOR_DECLARADO_55711,
        ]))->assertStatus(200);
        $this->assertEqualsWithDelta(120686.18, (float) $commission->fresh()->total, 0.001, 'bulto en $0: sólo el 3%, sin IVA porque VETARO está en automático');
    }

    // --- Semántica de la tarifa ---

    public function test_sin_valor_declarado_el_porcentaje_no_suma_nada(): void
    {
        $cliente = $this->clienteConTarifa(['fixed_price' => 1000, 'small_bulk_price' => 500, 'declared_value_percentage' => 3]);

        $commission = $this->crear($this->payload($cliente, 500, 1500));
        $this->putJson("/api/commissions/{$commission->id}", $this->payload($cliente, 500, 1500))->assertStatus(200);

        // 1000 base + 500 bulto, sin IVA (cliente automático sin método de pago).
        $this->assertEqualsWithDelta(1500, (float) $commission->fresh()->total, 0.001);
        $this->assertNull($commission->fresh()->declared_value);
    }

    public function test_el_porcentaje_se_suma_a_la_base_y_los_bultos_de_la_tarifa(): void
    {
        $cliente = $this->clienteConTarifa(['fixed_price' => 1000, 'small_bulk_price' => 500, 'declared_value_percentage' => 3]);

        $commission = $this->crear($this->payload($cliente, 500, 1500, ['declared_value' => 100000]));

        // 1000 base + 500 bulto + 3% de 100.000
        $this->assertEqualsWithDelta(4500, (float) $commission->total, 0.001);

        $this->putJson("/api/commissions/{$commission->id}", $this->payload($cliente, 500, 1500, ['declared_value' => 200000]))
            ->assertStatus(200);

        $this->assertEqualsWithDelta(7500, (float) $commission->fresh()->total, 0.001);
    }

    public function test_cliente_sin_tarifa_especial_ignora_el_valor_declarado(): void
    {
        $cliente = Customer::factory()->create(['iva_status' => IvaStatus::AUTO->value]);

        // Tabla general: 11.000 de base + 3.000 del bulto chico.
        $commission = $this->crear($this->payload($cliente, 3000, 14000, ['declared_value' => self::VALOR_DECLARADO_55711]));
        $this->assertEqualsWithDelta(14000, (float) $commission->total, 0.001);

        $this->putJson("/api/commissions/{$commission->id}", $this->payload($cliente, 3000, 14000, ['declared_value' => self::VALOR_DECLARADO_55711]))
            ->assertStatus(200);

        $commission->refresh();
        $this->assertEqualsWithDelta(14000, (float) $commission->total, 0.001);
        // Se guarda igual: es un dato de la encomienda aunque no se cobre.
        $this->assertEqualsWithDelta(self::VALOR_DECLARADO_55711, (float) $commission->declared_value, 0.001);
    }

    public function test_tarifa_especial_sin_porcentaje_ignora_el_valor_declarado(): void
    {
        $cliente = $this->clienteConTarifa(['fixed_price' => 7000, 'small_bulk_price' => 500]);

        $commission = $this->crear($this->payload($cliente, 500, 7500, ['declared_value' => self::VALOR_DECLARADO_55711]));
        $this->assertEqualsWithDelta(7500, (float) $commission->total, 0.001);

        $this->putJson("/api/commissions/{$commission->id}", $this->payload($cliente, 500, 7500, ['declared_value' => self::VALOR_DECLARADO_55711]))
            ->assertStatus(200);
        $this->assertEqualsWithDelta(7500, (float) $commission->fresh()->total, 0.001);
    }

    public function test_el_precio_de_acuerdo_sigue_cerrando_el_total(): void
    {
        $cliente = $this->clienteConTarifa(['agreement_price' => 4200]);

        // Ni la base, ni los bultos, ni un valor declarado sin porcentaje mueven el acuerdo.
        $commission = $this->crear($this->payload($cliente, 99999, 99999, ['declared_value' => self::VALOR_DECLARADO_55711]));
        $this->putJson("/api/commissions/{$commission->id}", $this->payload($cliente, 99999, 99999, ['declared_value' => self::VALOR_DECLARADO_55711]))
            ->assertStatus(200);

        $this->assertEqualsWithDelta(4200, (float) $commission->fresh()->total, 0.001);
    }

    public function test_con_acuerdo_y_porcentaje_el_acuerdo_cierra_el_total_y_el_porcentaje_no_corre(): void
    {
        // Decisión documentada en CustomerRateResolver::totalFor(): el acuerdo cierra el
        // total, como dice la pantalla de tarifas. Antes de RC-531 el valor declarado
        // llegaba siempre en 0, así que en la práctica ya era así; esta card no lo cambia.
        // Hoy ninguna tarifa de producción tiene las dos cosas.
        $cliente = $this->clienteConTarifa(['agreement_price' => 4200, 'declared_value_percentage' => 3]);

        // El formulario cotiza con la tarifa del cliente: el acuerdo.
        $commission = $this->crear($this->payload($cliente, 0, 4200, ['declared_value' => 100000]));
        $this->assertEqualsWithDelta(4200, (float) $commission->total, 0.001, 'el alta no suma el 3% encima del acuerdo');

        $this->putJson("/api/commissions/{$commission->id}", $this->payload($cliente, 0, 4200, ['declared_value' => 100000]))
            ->assertStatus(200);

        $commission->refresh();
        $this->assertEqualsWithDelta(4200, (float) $commission->total, 0.001, 'la edición tampoco');
        // El valor declarado se guarda igual, como dato de la encomienda.
        $this->assertEqualsWithDelta(100000, (float) $commission->declared_value, 0.001);
    }

    // --- Regresión y contrato ---

    public function test_sin_valor_declarado_el_alta_respeta_el_total_del_formulario(): void
    {
        $cliente = Customer::factory()->create(['iva_status' => IvaStatus::AUTO->value]);

        $commission = $this->crear($this->payload($cliente, 3000, 14000));

        $this->assertEqualsWithDelta(14000, (float) $commission->total, 0.001);
        $this->assertNull($commission->declared_value);
    }

    public function test_valor_declarado_negativo_se_rechaza(): void
    {
        $this->postJson('/api/commissions', $this->payload($this->vetaro, 0, 0, ['declared_value' => -1]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('declared_value');
    }

    public function test_la_ficha_y_el_listado_devuelven_el_valor_declarado(): void
    {
        // El modal de edición lo precarga desde acá; si no viniera, al guardar
        // se perdería el valor.
        $commission = $this->crear($this->payload($this->vetaro, 0, 0, ['declared_value' => self::VALOR_DECLARADO_55711]));

        $this->getJson("/api/commissions/{$commission->id}")
            ->assertOk()
            ->assertJsonPath('data.declared_value', '4022872.71');

        $listado = collect($this->getJson('/api/commissions?per_page=50')->assertOk()->json('data'));
        $this->assertSame('4022872.71', $listado->firstWhere('id', $commission->id)['declared_value'] ?? null);
    }

    // --- Pool de Cobranza (el modal se abre con otro payload) ---

    public function test_el_pool_de_cobranza_devuelve_cliente_y_valor_declarado_de_cada_comision(): void
    {
        // Sin el cliente el modal no podía pedir la tarifa especial y cotizaba con la
        // tabla general ($11.000 de base que VETARO no paga); sin el valor declarado
        // lo mostraba vacío. Ahí están las 6 comisiones de VETARO a corregir.
        $commission = $this->crear($this->payload($this->vetaro, 0, 0, ['declared_value' => self::VALOR_DECLARADO_55711]));
        $this->facturar($commission);

        $ficha = $this->getJson("/api/admin/collection-pool/{$this->vetaro->id}")->assertOk()->json('data');
        $enFicha = collect($ficha['commissions'])->firstWhere('id', $commission->id);

        $this->assertNotNull($enFicha);
        $this->assertSame($this->vetaro->id, $enFicha['client']['id']);
        $this->assertSame('VETARO (emanuel)', $enFicha['client']['name']);
        $this->assertSame('4022872.71', $enFicha['declared_value']);

        // El listado (/admin/collection-pool) arma el mismo payload. Desde RC-541 su consulta
        // ya no usa HAVING sin GROUP BY y corre en SQLite. Se ordena por nombre porque el
        // orden por saldo usa FIELD(), que es de MySQL.
        $listado = collect($this->getJson('/api/admin/collection-pool?per_page=9999&sort_by=name')->assertOk()->json('data'));
        $enListado = collect($listado->firstWhere('id', $this->vetaro->id)['commissions'] ?? [])->firstWhere('id', $commission->id);
        $this->assertSame($enFicha, $enListado);
    }

    public function test_un_cobrador_puede_ver_la_cotizacion_de_la_tarifa_del_cliente(): void
    {
        // Los cobradores editan comisiones desde el Pool; con 403 el modal volvía a la
        // tabla general y mostraba un total distinto del que guarda el backend.
        $cobrador = User::factory()->create(['role' => 'cobrador']);

        $this->actingAs($cobrador)
            ->getJson("/api/admin/customers/{$this->vetaro->id}/rates/preview?destination_id={$this->rosarioSanGenaro->id}")
            ->assertOk()
            ->assertJsonPath('data.has_special_rate', true)
            ->assertJsonPath('data.fixed_price', 0)
            ->assertJsonPath('data.declared_value_percentage', 3);
    }

    public function test_el_cobrador_sigue_sin_poder_tocar_las_tarifas_y_un_cliente_no_ve_la_cotizacion(): void
    {
        // Sólo se abrió la lectura de la cotización; alta, edición y baja siguen
        // siendo de administración.
        $cobrador = User::factory()->create(['role' => 'cobrador']);
        $this->actingAs($cobrador)
            ->postJson("/api/admin/customers/{$this->vetaro->id}/rates", ['declared_value_percentage' => 50])
            ->assertStatus(403);
        $this->actingAs($cobrador)
            ->getJson("/api/admin/customers/{$this->vetaro->id}/rates")
            ->assertStatus(403);

        $cliente = User::factory()->create(['role' => 'cliente']);
        $this->actingAs($cliente)
            ->getJson("/api/admin/customers/{$this->vetaro->id}/rates/preview?destination_id={$this->rosarioSanGenaro->id}")
            ->assertStatus(403);
    }

    private function clienteConTarifa(array $tarifa): Customer
    {
        $cliente = Customer::factory()->create(['iva_status' => IvaStatus::AUTO->value]);

        CustomerRate::create(array_merge(['customer_id' => $cliente->id], $tarifa));

        return $cliente;
    }
}
