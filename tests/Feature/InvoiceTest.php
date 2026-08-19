<?php

namespace Tests\Feature;

use App\Services\WhatsAppService;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\InvoiceType;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\CurrentAccount;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Franchise;
use App\Shared\Models\Invoice;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $franchiseAdmin;

    private User $cadete;

    private Branch $branch;

    private Franchise $franchise;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $waMock = $this->createMock(WhatsAppService::class);
        $waMock->method('sendMessage')->willReturn(true);
        $this->app->instance(WhatsAppService::class, $waMock);

        config(['afip.mock_arca' => true]);

        $this->branch = Branch::factory()->create();
        $this->franchise = Franchise::factory()->create();
        $this->customer = Customer::factory()->create([
            'franchise_id' => $this->franchise->id,
        ]);

        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'branch_id' => $this->branch->id,
        ]);
        $this->franchiseAdmin = User::factory()->create([
            'role' => 'admin_franquicia',
            'franchise_id' => $this->franchise->id,
            'branch_id' => $this->branch->id,
        ]);
        $this->cadete = User::factory()->create([
            'role' => 'cadete',
            'branch_id' => $this->branch->id,
        ]);
    }

    // --- Emission Tests ---

    public function test_admin_can_emit_factura_b()
    {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/invoices', [
            'customer_id' => $this->customer->id,
            'tipo_comprobante' => InvoiceType::FACTURA_B->value,
            'importe_total' => 1210,
        ]);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('cae'));
        $this->assertNotNull($response->json('cae_vencimiento'));
        $this->assertEquals(InvoiceType::FACTURA_B->value, $response->json('tipo_comprobante'));
        $this->assertEquals('emitida', $response->json('status'));
        $this->assertEquals($this->customer->id, $response->json('customer_id'));
    }

    public function test_admin_can_emit_factura_a()
    {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/invoices', [
            'customer_id' => $this->customer->id,
            'tipo_comprobante' => InvoiceType::FACTURA_A->value,
            'importe_total' => 2420,
            'iva_rate' => 21,
        ]);

        $response->assertStatus(201);
        $this->assertEquals(InvoiceType::FACTURA_A->value, $response->json('tipo_comprobante'));
        $this->assertNotNull($response->json('cae'));
    }

    public function test_admin_can_emit_nota_credito()
    {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/invoices', [
            'customer_id' => $this->customer->id,
            'tipo_comprobante' => InvoiceType::NOTA_CREDITO_B->value,
            'importe_total' => 500,
        ]);

        $response->assertStatus(201);
        $this->assertEquals(InvoiceType::NOTA_CREDITO_B->value, $response->json('tipo_comprobante'));
    }

    public function test_cadete_cannot_emit_invoice()
    {
        $this->actingAs($this->cadete, 'sanctum');

        $response = $this->postJson('/api/admin/invoices', [
            'customer_id' => $this->customer->id,
            'tipo_comprobante' => InvoiceType::FACTURA_B->value,
            'importe_total' => 1000,
        ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_emit_invoice()
    {
        $response = $this->postJson('/api/admin/invoices', [
            'customer_id' => $this->customer->id,
            'tipo_comprobante' => InvoiceType::FACTURA_B->value,
            'importe_total' => 1000,
        ]);

        $response->assertStatus(401);
    }

    // --- Commission Invoicing ---

    public function test_can_facturar_commission_pago_validacion()
    {
        $destination = Destination::factory()->create();
        $commission = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $destination->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'franchise_id' => $this->franchise->id,
            'total' => 5000,
            'status' => CommissionStatus::PAGO_VALIDACION->value,
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson("/api/admin/invoices/commission/{$commission->id}/facturar", [
            'tipo_comprobante' => InvoiceType::FACTURA_B->value,
        ]);

        $response->assertStatus(201);
        $this->assertEquals($commission->id, $response->json('commission_id'));
        $this->assertEquals(5000, (float) $response->json('importe_total'));
        $this->assertNotNull($response->json('cae'));
    }

    public function test_facturar_commission_guarda_periodo_de_servicio()
    {
        $destination = Destination::factory()->create();
        $commission = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $destination->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'franchise_id' => $this->franchise->id,
            'total' => 5000,
            'date' => '2026-08-12',
            'status' => CommissionStatus::PAGO_VALIDACION->value,
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson("/api/admin/invoices/commission/{$commission->id}/facturar", [
            'tipo_comprobante' => InvoiceType::FACTURA_B->value,
        ]);

        $response->assertStatus(201);

        $invoice = Invoice::find($response->json('id'));

        // Concepto 2 (servicios): ARCA exige periodo facturado y vencimiento de pago.
        $this->assertSame(2, (int) $invoice->concepto);
        $this->assertSame('2026-08-12', $invoice->fecha_servicio_desde->toDateString());
        $this->assertSame('2026-08-12', $invoice->fecha_servicio_hasta->toDateString());
        $this->assertNotNull($invoice->fecha_vto_pago);
    }

    public function test_facturar_con_concepto_productos_no_guarda_periodo_de_servicio()
    {
        $this->actingAs($this->admin, 'sanctum');

        $invoice = app(\App\Services\InvoiceService::class)->emitirFactura([
            'customer_id' => $this->customer->id,
            'tipo_comprobante' => InvoiceType::FACTURA_B->value,
            'importe_total' => 1000,
            'concepto' => 1,
        ], $this->admin);

        // Concepto 1 es productos: ARCA rechaza el comprobante si le mandan fechas de servicio.
        $this->assertNull($invoice->fecha_servicio_desde);
        $this->assertNull($invoice->fecha_servicio_hasta);
        $this->assertNull($invoice->fecha_vto_pago);
    }

    public function test_cannot_facturar_commission_wrong_status()
    {
        $destination = Destination::factory()->create();
        $commission = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $destination->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson("/api/admin/invoices/commission/{$commission->id}/facturar");
        $response->assertStatus(422);
    }

    public function test_cannot_facturar_commission_twice()
    {
        $destination = Destination::factory()->create();
        $commission = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $destination->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'franchise_id' => $this->franchise->id,
            'total' => 3000,
            'status' => CommissionStatus::PAGO_VALIDACION->value,
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $first = $this->postJson("/api/admin/invoices/commission/{$commission->id}/facturar");
        $first->assertStatus(201);

        $second = $this->postJson("/api/admin/invoices/commission/{$commission->id}/facturar");
        $second->assertStatus(500);
    }

    // --- Income Invoicing ---

    public function test_can_facturar_confirmed_income()
    {
        $payment = CurrentAccount::create([
            'customer_id' => $this->customer->id,
            'type' => 'credit',
            'status' => 'OK',
            'amount' => 2000,
            'description' => 'Pago recibido',
            'transaction_date' => now(),
            'balance' => 2000,
            'payment_method' => 'cash',
            'franchise_id' => $this->franchise->id,
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson("/api/admin/invoices/payment/{$payment->id}/facturar");

        $response->assertStatus(201);
        $this->assertEquals($payment->id, $response->json('current_account_id'));
        $this->assertEquals(2000, (float) $response->json('importe_total'));
    }

    public function test_cannot_facturar_pending_income()
    {
        $payment = CurrentAccount::create([
            'customer_id' => $this->customer->id,
            'type' => 'credit',
            'status' => 'PENDIENTE',
            'amount' => 1000,
            'description' => 'Pendiente',
            'transaction_date' => now(),
            'balance' => 1000,
            'payment_method' => 'cash',
            'franchise_id' => $this->franchise->id,
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson("/api/admin/invoices/payment/{$payment->id}/facturar");
        $response->assertStatus(422);
    }

    public function test_cannot_facturar_debit()
    {
        $payment = CurrentAccount::create([
            'customer_id' => $this->customer->id,
            'type' => 'debit',
            'status' => 'OK',
            'amount' => 1000,
            'description' => 'Gasto',
            'transaction_date' => now(),
            'balance' => -1000,
            'payment_method' => 'cash',
            'franchise_id' => $this->franchise->id,
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson("/api/admin/invoices/payment/{$payment->id}/facturar");
        $response->assertStatus(422);
    }

    // --- Listing & Filters ---

    public function test_admin_can_list_invoices()
    {
        Invoice::factory()->count(3)->create([
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/invoices');
        $response->assertOk();
        $this->assertGreaterThanOrEqual(3, $response->json('total'));
    }

    public function test_can_filter_invoices_by_customer()
    {
        Invoice::factory()->count(2)->create([
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
        ]);

        $otherCustomer = Customer::factory()->create();
        Invoice::factory()->create([
            'customer_id' => $otherCustomer->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson("/api/admin/invoices?customer_id={$this->customer->id}");
        $response->assertOk();
        $this->assertEquals(2, $response->json('total'));
    }

    public function test_can_filter_invoices_by_date_range()
    {
        Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'fecha_emision' => '2026-06-01',
        ]);
        Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'fecha_emision' => '2026-01-01',
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/invoices?date_from=2026-05-01&date_to=2026-07-01');
        $response->assertOk();
        $this->assertEquals(1, $response->json('total'));
    }

    public function test_can_get_customer_invoices()
    {
        Invoice::factory()->count(2)->create([
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson("/api/admin/invoices/customer/{$this->customer->id}");
        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    // --- Show & PDF ---

    public function test_can_show_invoice_detail()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson("/api/admin/invoices/{$invoice->id}");
        $response->assertOk();
        $response->assertJsonFragment(['id' => $invoice->id]);
    }

    public function test_can_get_invoice_pdf_data()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson("/api/admin/invoices/{$invoice->id}/pdf");
        $response->assertOk();
        $response->assertJsonStructure(['invoice', 'letter', 'label', 'formatted_number', 'emisor']);
    }

    // --- Anular ---

    public function test_can_anular_invoice()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'status' => 'emitida',
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->patchJson("/api/admin/invoices/{$invoice->id}/anular");
        $response->assertOk();
        $this->assertEquals('anulada', $response->json('status'));
    }

    public function test_cannot_anular_already_anulada()
    {
        $invoice = Invoice::factory()->anulada()->create([
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->patchJson("/api/admin/invoices/{$invoice->id}/anular");
        $response->assertStatus(422);
    }

    // --- Tipos Comprobante ---

    public function test_can_get_tipos_comprobante()
    {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/invoices/tipos-comprobante');
        $response->assertOk();
        $this->assertGreaterThan(0, count($response->json()));
    }

    // --- CUIT Lookup ---

    public function test_can_consultar_cuit()
    {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/invoices/consultar-cuit', [
            'cuit' => '20345678901',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['razonSocial', 'tipoResponsable', 'domicilio']);
    }

    // --- Franchise Isolation ---

    public function test_franchise_admin_sees_only_own_invoices()
    {
        Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'franchise_id' => $this->franchise->id,
        ]);

        $otherFranchise = Franchise::factory()->create();
        $otherCustomer = Customer::factory()->create(['franchise_id' => $otherFranchise->id]);
        Invoice::factory()->create([
            'customer_id' => $otherCustomer->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'franchise_id' => $otherFranchise->id,
        ]);

        $this->actingAs($this->franchiseAdmin, 'sanctum');

        $response = $this->getJson('/api/admin/invoices');
        $response->assertOk();
        $this->assertEquals(1, $response->json('total'));
    }

    // --- IVA Calculation ---

    public function test_iva_is_calculated_correctly()
    {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/invoices', [
            'customer_id' => $this->customer->id,
            'tipo_comprobante' => InvoiceType::FACTURA_B->value,
            'importe_total' => 1210,
            'iva_rate' => 21,
        ]);

        $response->assertStatus(201);
        $this->assertEquals(1210, (float) $response->json('importe_total'));
        $neto = (float) $response->json('importe_neto');
        $iva = (float) $response->json('importe_iva');
        $this->assertEqualsWithDelta(1000, $neto, 0.01);
        $this->assertEqualsWithDelta(210, $iva, 0.01);
    }

    // --- Mock Mode ---

    public function test_mock_mode_generates_valid_cae()
    {
        config(['afip.mock_arca' => true]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/invoices', [
            'customer_id' => $this->customer->id,
            'tipo_comprobante' => InvoiceType::FACTURA_B->value,
            'importe_total' => 500,
        ]);

        $response->assertStatus(201);
        $cae = $response->json('cae');
        $this->assertNotNull($cae);
        $this->assertEquals(14, strlen($cae));
    }

    // --- Validation ---

    public function test_cannot_emit_without_customer()
    {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/invoices', [
            'tipo_comprobante' => InvoiceType::FACTURA_B->value,
            'importe_total' => 1000,
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_emit_with_zero_amount()
    {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/invoices', [
            'customer_id' => $this->customer->id,
            'tipo_comprobante' => InvoiceType::FACTURA_B->value,
            'importe_total' => 0,
        ]);

        $response->assertStatus(422);
    }
}
