<?php

namespace Tests\Feature;

use App\Services\IvaCalculationService;
use App\Shared\Enums\InvoiceType;
use App\Shared\Enums\PaymentMethod;
use App\Shared\Models\Branch;
use App\Shared\Models\Customer;
use App\Shared\Models\Franchise;
use App\Shared\Models\Invoice;
use App\Shared\Models\SystemSetting;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IvaExpandedTest extends TestCase
{
    use RefreshDatabase;

    private IvaCalculationService $service;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IvaCalculationService();
        $branch = Branch::factory()->create();
        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'branch_id' => $branch->id,
        ]);
    }

    // --- IVA Status: ALWAYS ---

    public function test_iva_always_applies_for_cash_payment()
    {
        $customer = Customer::factory()->create(['iva_status' => 'always']);

        $result = $this->service->calculateIva($customer, PaymentMethod::EFECTIVO, 1000);

        $this->assertTrue($result['iva_applied']);
        $this->assertEquals(210.00, $result['iva_amount']);
        $this->assertEquals(1210.00, $result['total_with_iva']);
    }

    public function test_iva_always_applies_for_check_payment()
    {
        $customer = Customer::factory()->create(['iva_status' => 'always']);

        $result = $this->service->calculateIva($customer, PaymentMethod::CHEQUE, 500);

        $this->assertTrue($result['iva_applied']);
        $this->assertEquals(105.00, $result['iva_amount']);
    }

    public function test_iva_always_applies_for_transfer_payment()
    {
        $customer = Customer::factory()->create(['iva_status' => 'always']);

        $result = $this->service->calculateIva($customer, PaymentMethod::TRANSFERENCIA, 1000);

        $this->assertTrue($result['iva_applied']);
        $this->assertEquals(210.00, $result['iva_amount']);
    }

    public function test_iva_always_applies_for_cuenta_corriente()
    {
        $customer = Customer::factory()->create(['iva_status' => 'always']);

        $result = $this->service->calculateIva($customer, PaymentMethod::CUENTA_CORRIENTE, 800);

        $this->assertTrue($result['iva_applied']);
        $this->assertEquals(168.00, $result['iva_amount']);
    }

    // --- IVA Status: EXEMPT ---

    public function test_iva_exempt_never_applies_for_transfer()
    {
        $customer = Customer::factory()->create([
            'iva_status' => 'exempt',
            'auto_calculate_iva' => true,
        ]);

        $result = $this->service->calculateIva($customer, PaymentMethod::TRANSFERENCIA, 1000);

        $this->assertFalse($result['iva_applied']);
        $this->assertEquals(0.0, $result['iva_amount']);
        $this->assertEquals(1000.00, $result['total_with_iva']);
    }

    public function test_iva_exempt_never_applies_for_cash()
    {
        $customer = Customer::factory()->create(['iva_status' => 'exempt']);

        $result = $this->service->calculateIva($customer, PaymentMethod::EFECTIVO, 1000);

        $this->assertFalse($result['iva_applied']);
    }

    // --- IVA Status: AUTO (default, backward compatible) ---

    public function test_iva_auto_applies_for_transfer_with_flag()
    {
        $customer = Customer::factory()->create([
            'iva_status' => 'auto',
            'auto_calculate_iva' => true,
        ]);

        $result = $this->service->calculateIva($customer, PaymentMethod::TRANSFERENCIA, 1000);

        $this->assertTrue($result['iva_applied']);
        $this->assertEquals(210.00, $result['iva_amount']);
    }

    public function test_iva_auto_does_not_apply_for_transfer_without_flag()
    {
        $customer = Customer::factory()->create([
            'iva_status' => 'auto',
            'auto_calculate_iva' => false,
        ]);

        $result = $this->service->calculateIva($customer, PaymentMethod::TRANSFERENCIA, 1000);

        $this->assertFalse($result['iva_applied']);
    }

    public function test_iva_auto_does_not_apply_for_cash_even_with_flag()
    {
        $customer = Customer::factory()->create([
            'iva_status' => 'auto',
            'auto_calculate_iva' => true,
        ]);

        $result = $this->service->calculateIva($customer, PaymentMethod::EFECTIVO, 1000);

        $this->assertFalse($result['iva_applied']);
    }

    public function test_iva_auto_does_not_apply_for_check_even_with_flag()
    {
        $customer = Customer::factory()->create([
            'iva_status' => 'auto',
            'auto_calculate_iva' => true,
        ]);

        $result = $this->service->calculateIva($customer, PaymentMethod::CHEQUE, 1000);

        $this->assertFalse($result['iva_applied']);
    }

    // --- Null iva_status defaults to AUTO ---

    public function test_null_iva_status_defaults_to_auto()
    {
        $customer = Customer::factory()->create([
            'iva_status' => null,
            'auto_calculate_iva' => true,
        ]);

        $result = $this->service->calculateIva($customer, PaymentMethod::TRANSFERENCIA, 1000);
        $this->assertTrue($result['iva_applied']);

        $resultCash = $this->service->calculateIva($customer, PaymentMethod::EFECTIVO, 1000);
        $this->assertFalse($resultCash['iva_applied']);
    }

    // --- API: Update IVA status ---

    public function test_api_update_customer_iva_status()
    {
        $customer = Customer::factory()->create(['iva_status' => 'auto']);

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->patchJson("/api/customers/{$customer->id}/auto-calculate-iva", [
            'iva_status' => 'always',
        ]);

        $response->assertOk();
        $this->assertEquals('always', $response->json('customer.iva_status'));
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'iva_status' => 'always']);
    }

    public function test_api_update_customer_iva_status_validates_values()
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->patchJson("/api/customers/{$customer->id}/auto-calculate-iva", [
            'iva_status' => 'invalid_value',
        ]);

        $response->assertStatus(422);
    }

    // --- IVA Report ---

    public function test_api_iva_report_requires_dates()
    {
        $this->actingAs($this->admin, 'sanctum');
        $response = $this->getJson('/api/admin/reports/iva');
        $response->assertStatus(422);
    }

    public function test_api_iva_report_returns_correct_structure()
    {
        $customer = Customer::factory()->create();
        $branch = Branch::factory()->create();

        Invoice::factory()->count(3)->create([
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'user_id' => $this->admin->id,
            'tipo_comprobante' => InvoiceType::FACTURA_B->value,
            'importe_total' => 1210,
            'importe_neto' => 1000,
            'importe_iva' => 210,
            'fecha_emision' => '2026-06-15',
            'status' => 'emitida',
        ]);

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->getJson('/api/admin/reports/iva?date_from=2026-06-01&date_to=2026-06-30');
        $response->assertOk();
        $response->assertJsonStructure([
            'period' => ['from', 'to'],
            'total_iva',
            'total_neto',
            'total_final',
            'invoice_count',
            'by_type',
            'by_franchise',
        ]);

        $this->assertEquals(630.00, $response->json('total_iva'));
        $this->assertEquals(3, $response->json('invoice_count'));
    }

    public function test_api_iva_report_scoped_by_franchise_admin()
    {
        $franchise = Franchise::factory()->create();
        $franchiseAdmin = User::factory()->create([
            'role' => 'admin_franquicia',
            'franchise_id' => $franchise->id,
        ]);

        $customer = Customer::factory()->create();
        $branch = Branch::factory()->create();

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'user_id' => $franchiseAdmin->id,
            'franchise_id' => $franchise->id,
            'importe_total' => 580,
            'importe_neto' => 480,
            'importe_iva' => 100,
            'fecha_emision' => '2026-06-15',
            'status' => 'emitida',
        ]);

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'user_id' => $this->admin->id,
            'franchise_id' => null,
            'importe_total' => 1200,
            'importe_neto' => 1000,
            'importe_iva' => 200,
            'fecha_emision' => '2026-06-15',
            'status' => 'emitida',
        ]);

        $this->actingAs($franchiseAdmin, 'sanctum');
        $response = $this->getJson('/api/admin/reports/iva?date_from=2026-06-01&date_to=2026-06-30');
        $response->assertOk();

        $this->assertEquals(1, $response->json('invoice_count'));
        $this->assertEquals(100, $response->json('total_iva'));
    }

    // --- Settings API ---

    public function test_api_get_iva_config()
    {
        SystemSetting::set('iva_payment_methods', ['TRANSFERENCIA']);

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->getJson('/api/admin/settings/iva');
        $response->assertOk();
        $response->assertJsonStructure(['payment_methods']);

        $methods = collect($response->json('payment_methods'));
        $this->assertCount(4, $methods);
        $this->assertTrue($methods->firstWhere('value', 'TRANSFERENCIA')['enabled']);
        $this->assertFalse($methods->firstWhere('value', 'EFECTIVO')['enabled']);
    }

    public function test_api_update_iva_config()
    {
        $this->actingAs($this->admin, 'sanctum');
        $response = $this->putJson('/api/admin/settings/iva', [
            'payment_methods' => ['TRANSFERENCIA', 'CHEQUE'],
        ]);
        $response->assertOk();

        $saved = SystemSetting::get('iva_payment_methods');
        $this->assertContains('TRANSFERENCIA', $saved);
        $this->assertContains('CHEQUE', $saved);
        $this->assertNotContains('EFECTIVO', $saved);
    }

    public function test_iva_auto_applies_for_configured_methods()
    {
        SystemSetting::set('iva_payment_methods', ['TRANSFERENCIA', 'CHEQUE']);

        $customer = Customer::factory()->create([
            'iva_status' => 'auto',
            'auto_calculate_iva' => true,
        ]);

        $service = new IvaCalculationService();

        $resultTransfer = $service->shouldApplyIva($customer, PaymentMethod::TRANSFERENCIA);
        $this->assertTrue($resultTransfer);

        $resultCheque = $service->shouldApplyIva($customer, PaymentMethod::CHEQUE);
        $this->assertTrue($resultCheque);

        $resultCash = $service->shouldApplyIva($customer, PaymentMethod::EFECTIVO);
        $this->assertFalse($resultCash);
    }

    public function test_api_update_iva_config_validates_methods()
    {
        $this->actingAs($this->admin, 'sanctum');
        $response = $this->putJson('/api/admin/settings/iva', [
            'payment_methods' => ['INVALIDO'],
        ]);
        $response->assertStatus(422);
    }
}
