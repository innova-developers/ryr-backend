<?php

namespace Tests\Feature;

use App\Shared\Enums\InvoiceType;
use App\Shared\Models\Branch;
use App\Shared\Models\Customer;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RC-497 (FACTURACION - RAZON).
 *
 * Antes, la razón social y el documento se derivaban del cliente dentro de
 * emitirFactura() y no había forma de verlos ni corregirlos: para cuando se
 * detectaba el error el CAE ya estaba pedido a ARCA y sólo quedaba anular.
 *
 * Ahora hay un preview que resuelve los datos sin tocar ARCA, y la emisión
 * acepta los valores corregidos.
 */
class InvoicePreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $branch = Branch::factory()->create();
        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'branch_id' => $branch->id,
        ]);
    }

    public function test_preview_resolves_company_razon_social(): void
    {
        $customer = Customer::factory()->create([
            'type' => 'company',
            'razon_social' => 'ENCOMIENDAS LA ESTRELLA SRL',
            'cuit' => '30711223344',
            'name' => 'Contacto Comercial',
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/admin/invoices/preview', [
            'customer_id' => $customer->id,
            'tipo_comprobante' => InvoiceType::FACTURA_B->value,
            'importe_total' => 12100,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.razon_social', 'ENCOMIENDAS LA ESTRELLA SRL')
            ->assertJsonPath('data.doc_tipo', 80)
            ->assertJsonPath('data.doc_numero', '30711223344');
    }

    public function test_preview_resolves_individual_from_name_and_last_name(): void
    {
        $customer = Customer::factory()->create([
            'type' => 'individual',
            'name' => 'Claudia',
            'last_name' => 'Paulino',
            'dni' => 33111333,
            'cuit' => null,
        ]);

        $this->actingAs($this->admin)->postJson('/api/admin/invoices/preview', [
            'customer_id' => $customer->id,
            'tipo_comprobante' => InvoiceType::FACTURA_B->value,
            'importe_total' => 1000,
        ])
            ->assertOk()
            ->assertJsonPath('data.razon_social', 'Claudia Paulino')
            ->assertJsonPath('data.doc_tipo', 96)
            ->assertJsonPath('data.doc_numero', '33111333');
    }

    public function test_preview_breaks_down_neto_and_iva(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin)->postJson('/api/admin/invoices/preview', [
            'customer_id' => $customer->id,
            'tipo_comprobante' => InvoiceType::FACTURA_B->value,
            'importe_total' => 121,
            'iva_rate' => 21,
        ])
            ->assertOk()
            ->assertJsonPath('data.importe_neto', 100)
            ->assertJsonPath('data.importe_iva', 21);
    }

    public function test_preview_does_not_create_an_invoice(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin)->postJson('/api/admin/invoices/preview', [
            'customer_id' => $customer->id,
            'tipo_comprobante' => InvoiceType::FACTURA_B->value,
            'importe_total' => 1000,
        ])->assertOk();

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_preview_requires_customer_and_amount(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/admin/invoices/preview', ['tipo_comprobante' => 6])
            ->assertStatus(422);
    }

    public function test_emission_uses_corrected_razon_social(): void
    {
        $customer = Customer::factory()->create([
            'type' => 'individual',
            'name' => 'Nombre',
            'last_name' => 'Mal Cargado',
            'dni' => 33111333,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/admin/invoices', [
            'customer_id' => $customer->id,
            'tipo_comprobante' => InvoiceType::FACTURA_B->value,
            'importe_total' => 1000,
            'razon_social' => 'RAZON CORREGIDA SRL',
            'doc_tipo' => 80,
            'doc_numero' => '30711223344',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('invoices', [
            'customer_id' => $customer->id,
            'razon_social' => 'RAZON CORREGIDA SRL',
            'doc_tipo' => 80,
            'doc_numero' => '30711223344',
        ]);
    }

    public function test_emission_falls_back_to_customer_when_no_override(): void
    {
        $customer = Customer::factory()->create([
            'type' => 'individual',
            'name' => 'Claudia',
            'last_name' => 'Paulino',
            'dni' => 33111333,
        ]);

        $this->actingAs($this->admin)->postJson('/api/admin/invoices', [
            'customer_id' => $customer->id,
            'tipo_comprobante' => InvoiceType::FACTURA_B->value,
            'importe_total' => 1000,
        ])->assertCreated();

        $this->assertDatabaseHas('invoices', [
            'customer_id' => $customer->id,
            'razon_social' => 'Claudia Paulino',
        ]);
    }

    public function test_emission_rejects_invalid_doc_tipo(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin)->postJson('/api/admin/invoices', [
            'customer_id' => $customer->id,
            'tipo_comprobante' => InvoiceType::FACTURA_B->value,
            'importe_total' => 1000,
            'doc_tipo' => 12,
        ])->assertStatus(422);
    }
}
