<?php

namespace Tests\Feature;

use App\Services\CustomerRateResolver;
use App\Shared\Models\Branch;
use App\Shared\Models\Customer;
use App\Shared\Models\CustomerRate;
use App\Shared\Models\CustomerRateTier;
use App\Shared\Models\Destination;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RC-484 (TARIFAS ESPECIALES).
 *
 * Antes los precios vivían sólo en destinations: la clasificación individual/empresa
 * del cliente era identificatoria y los dos pagaban la misma tabla.
 *
 * Ahora cada cliente puede tener tarifa propia, por destino o para todos, con precio
 * base, bulto chico, bulto grande, escalones por cantidad, precio de acuerdo cerrado
 * y porcentaje sobre valor declarado. Lo que no se define cae a la tabla general.
 */
class CustomerRateTest extends TestCase
{
    use RefreshDatabase;

    private CustomerRateResolver $resolver;

    private Customer $customer;

    private Destination $destination;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(CustomerRateResolver::class);
        $this->customer = Customer::factory()->create();
        $this->destination = Destination::factory()->create([
            'fixed_price' => 1000,
            'small_bulk_price' => 500,
            'large_bulk_price' => 900,
        ]);
        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'branch_id' => Branch::factory()->create()->id,
        ]);
    }

    // --- Resolución y fallback ---

    public function test_falls_back_to_the_general_table_without_special_rate(): void
    {
        $r = $this->resolver->resolve($this->customer->id, $this->destination);

        $this->assertSame('general', $r['source']);
        $this->assertEquals(1000, $r['fixed_price']);
        $this->assertEquals(500, $r['small_bulk_price']);
        $this->assertEquals(900, $r['large_bulk_price']);
    }

    public function test_customer_rate_overrides_the_general_table(): void
    {
        CustomerRate::create([
            'customer_id' => $this->customer->id,
            'small_bulk_price' => 300,
            'large_bulk_price' => 600,
            'fixed_price' => 0,
        ]);

        $r = $this->resolver->resolve($this->customer->id, $this->destination);

        $this->assertSame('cliente_general', $r['source']);
        $this->assertEquals(300, $r['small_bulk_price']);
        $this->assertEquals(0, $r['fixed_price']);
    }

    public function test_undefined_prices_fall_back_individually(): void
    {
        // Sólo se pacta el bulto grande: el resto sigue saliendo de la tabla general.
        CustomerRate::create([
            'customer_id' => $this->customer->id,
            'large_bulk_price' => 700,
        ]);

        $r = $this->resolver->resolve($this->customer->id, $this->destination);

        $this->assertEquals(700, $r['large_bulk_price']);
        $this->assertEquals(500, $r['small_bulk_price']);
        $this->assertEquals(1000, $r['fixed_price']);
    }

    public function test_destination_specific_rate_beats_the_general_customer_rate(): void
    {
        CustomerRate::create([
            'customer_id' => $this->customer->id,
            'small_bulk_price' => 300,
        ]);
        CustomerRate::create([
            'customer_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'small_bulk_price' => 150,
        ]);

        $r = $this->resolver->resolve($this->customer->id, $this->destination);

        $this->assertSame('cliente_destino', $r['source']);
        $this->assertEquals(150, $r['small_bulk_price']);
    }

    public function test_inactive_rate_is_ignored(): void
    {
        CustomerRate::create([
            'customer_id' => $this->customer->id,
            'small_bulk_price' => 300,
            'is_active' => false,
        ]);

        $this->assertSame('general', $this->resolver->resolve($this->customer->id, $this->destination)['source']);
    }

    public function test_another_customers_rate_does_not_leak(): void
    {
        $otro = Customer::factory()->create();
        CustomerRate::create(['customer_id' => $otro->id, 'small_bulk_price' => 1]);

        $this->assertEquals(500, $this->resolver->resolve($this->customer->id, $this->destination)['small_bulk_price']);
    }

    // --- Escalones por cantidad/volumen ---

    public function test_volume_tier_applies_from_its_minimum(): void
    {
        $rate = CustomerRate::create(['customer_id' => $this->customer->id, 'small_bulk_price' => 500]);
        CustomerRateTier::create([
            'customer_rate_id' => $rate->id, 'size' => 'CHICO', 'min_quantity' => 10, 'unit_price' => 400,
        ]);

        $r = $this->resolver->resolve($this->customer->id, $this->destination);

        $this->assertEquals(500, $this->resolver->unitPriceFor($r, 'CHICO', 9));
        $this->assertEquals(400, $this->resolver->unitPriceFor($r, 'CHICO', 10));
    }

    public function test_highest_reached_tier_wins(): void
    {
        $rate = CustomerRate::create(['customer_id' => $this->customer->id, 'small_bulk_price' => 500]);
        foreach ([[10, 400], [50, 300], [100, 250]] as [$min, $price]) {
            CustomerRateTier::create([
                'customer_rate_id' => $rate->id, 'size' => 'CHICO', 'min_quantity' => $min, 'unit_price' => $price,
            ]);
        }

        $r = $this->resolver->resolve($this->customer->id, $this->destination);

        $this->assertEquals(400, $this->resolver->unitPriceFor($r, 'CHICO', 20));
        $this->assertEquals(300, $this->resolver->unitPriceFor($r, 'CHICO', 60));
        $this->assertEquals(250, $this->resolver->unitPriceFor($r, 'CHICO', 500));
    }

    public function test_tier_of_one_size_does_not_affect_the_other(): void
    {
        $rate = CustomerRate::create([
            'customer_id' => $this->customer->id, 'small_bulk_price' => 500, 'large_bulk_price' => 900,
        ]);
        CustomerRateTier::create([
            'customer_rate_id' => $rate->id, 'size' => 'CHICO', 'min_quantity' => 5, 'unit_price' => 100,
        ]);

        $r = $this->resolver->resolve($this->customer->id, $this->destination);

        $this->assertEquals(100, $this->resolver->unitPriceFor($r, 'CHICO', 10));
        $this->assertEquals(900, $this->resolver->unitPriceFor($r, 'GRANDE', 10));
    }

    // --- Total: acuerdo y valor declarado ---

    public function test_total_is_base_plus_items(): void
    {
        $r = $this->resolver->resolve($this->customer->id, $this->destination);

        $total = $this->resolver->totalFor($r, [['subtotal' => 2500]]);

        $this->assertEquals(3500, $total); // 1000 base + 2500
    }

    public function test_agreement_price_closes_the_commission(): void
    {
        CustomerRate::create(['customer_id' => $this->customer->id, 'agreement_price' => 4200]);

        $r = $this->resolver->resolve($this->customer->id, $this->destination);

        // Ni la base ni los bultos se suman: el acuerdo es el precio final.
        $this->assertEquals(4200, $this->resolver->totalFor($r, [['subtotal' => 99999]]));
    }

    public function test_agreement_price_in_zero_is_not_an_agreement(): void
    {
        // RC-515: la pantalla guardaba 0 cuando el campo quedaba vacío y, como el
        // acuerdo cierra el total, la comisión salía en $0. En producción salieron
        // 11 comisiones facturadas en cero por esto.
        CustomerRate::create([
            'customer_id' => $this->customer->id,
            'fixed_price' => 1000,
            'small_bulk_price' => 2500,
            'agreement_price' => 0,
        ]);

        $r = $this->resolver->resolve($this->customer->id, $this->destination);

        $this->assertNull($r['agreement_price']);
        $this->assertEquals(3500, $this->resolver->totalFor($r, [['subtotal' => 2500]]));
    }

    public function test_zero_declared_value_percentage_is_not_a_percentage(): void
    {
        CustomerRate::create([
            'customer_id' => $this->customer->id,
            'declared_value_percentage' => 0,
        ]);

        $r = $this->resolver->resolve($this->customer->id, $this->destination);

        $this->assertNull($r['declared_value_percentage']);
        $this->assertEquals(3500, $this->resolver->totalFor($r, [['subtotal' => 2500]], 50000));
    }

    public function test_creating_a_rate_with_zero_agreement_stores_null(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson("/api/admin/customers/{$this->customer->id}/rates", [
            'fixed_price' => 1000,
            'small_bulk_price' => 2500,
            'large_bulk_price' => 2500,
            'agreement_price' => 0,
            'declared_value_percentage' => 0,
        ])->assertStatus(201);

        $rate = CustomerRate::where('customer_id', $this->customer->id)->firstOrFail();

        $this->assertNull($rate->agreement_price);
        $this->assertNull($rate->declared_value_percentage);
    }

    public function test_declared_value_percentage_is_added(): void
    {
        CustomerRate::create([
            'customer_id' => $this->customer->id,
            'declared_value_percentage' => 2,
        ]);

        $r = $this->resolver->resolve($this->customer->id, $this->destination);

        // 1000 base + 500 items + 2% de 100000
        $this->assertEquals(3500, $this->resolver->totalFor($r, [['subtotal' => 500]], 100000));
    }

    public function test_declared_value_ignored_when_no_percentage(): void
    {
        $r = $this->resolver->resolve($this->customer->id, $this->destination);

        $this->assertEquals(1500, $this->resolver->totalFor($r, [['subtotal' => 500]], 100000));
    }

    // --- API ---

    public function test_admin_can_create_and_list_a_rate(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/admin/customers/{$this->customer->id}/rates", [
                'small_bulk_price' => 250,
                'tiers' => [['size' => 'CHICO', 'min_quantity' => 10, 'unit_price' => 200]],
            ])
            ->assertCreated();

        $this->actingAs($this->admin)
            ->getJson("/api/admin/customers/{$this->customer->id}/rates")
            ->assertOk()
            ->assertJsonPath('data.0.small_bulk_price', 250)
            ->assertJsonPath('data.0.tiers.0.min_quantity', 10);
    }

    public function test_duplicate_rate_for_same_destination_is_rejected(): void
    {
        CustomerRate::create(['customer_id' => $this->customer->id, 'destination_id' => $this->destination->id]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/customers/{$this->customer->id}/rates", [
                'destination_id' => $this->destination->id,
            ])
            ->assertStatus(422);
    }

    public function test_percentage_over_one_hundred_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/admin/customers/{$this->customer->id}/rates", [
                'declared_value_percentage' => 150,
            ])
            ->assertStatus(422);
    }

    public function test_preview_reports_the_source_of_the_prices(): void
    {
        CustomerRate::create(['customer_id' => $this->customer->id, 'small_bulk_price' => 250]);

        $this->actingAs($this->admin)
            ->getJson("/api/admin/customers/{$this->customer->id}/rates/preview?destination_id={$this->destination->id}")
            ->assertOk()
            ->assertJsonPath('data.source', 'cliente_general')
            ->assertJsonPath('data.has_special_rate', true)
            ->assertJsonPath('data.small_bulk_price', 250)
            ->assertJsonPath('data.large_bulk_price', 900);
    }

    public function test_preview_without_rate_reports_general(): void
    {
        $this->actingAs($this->admin)
            ->getJson("/api/admin/customers/{$this->customer->id}/rates/preview?destination_id={$this->destination->id}")
            ->assertOk()
            ->assertJsonPath('data.source', 'general')
            ->assertJsonPath('data.has_special_rate', false);
    }

    public function test_rate_can_be_deleted(): void
    {
        $rate = CustomerRate::create(['customer_id' => $this->customer->id, 'small_bulk_price' => 250]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/admin/customers/{$this->customer->id}/rates/{$rate->id}")
            ->assertOk();

        $this->assertSame('general', $this->resolver->resolve($this->customer->id, $this->destination)['source']);
    }
}
