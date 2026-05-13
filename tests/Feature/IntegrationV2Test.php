<?php

namespace Tests\Feature;

use App\Services\FeedbackService;
use App\Services\MatrixCommissionService;
use App\Services\WhatsAppService;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\InvoiceType;
use App\Shared\Enums\PaymentMethod;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\CurrentAccount;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\FeedbackSurvey;
use App\Shared\Models\Franchise;
use App\Shared\Models\Invoice;
use App\Shared\Models\MatrixReceivable;
use App\Shared\Models\User;
use App\Shared\Models\WhatsAppCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationV2Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $franchiseAdmin;
    private User $mostrador;
    private User $cadete;
    private Franchise $franchise;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $mock = $this->createMock(WhatsAppService::class);
        $mock->method('sendMessage')->willReturn(true);
        $this->app->instance(WhatsAppService::class, $mock);

        $this->branch = Branch::factory()->create();
        $this->franchise = Franchise::factory()->create();

        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'branch_id' => $this->branch->id,
        ]);
        $this->franchiseAdmin = User::factory()->create([
            'role' => 'admin_franquicia',
            'franchise_id' => $this->franchise->id,
            'branch_id' => $this->branch->id,
        ]);
        $this->mostrador = User::factory()->create([
            'role' => 'mostrador',
            'branch_id' => $this->branch->id,
        ]);
        $this->cadete = User::factory()->create([
            'role' => 'cadete',
            'branch_id' => $this->branch->id,
        ]);
    }

    // --- Permission Matrix ---

    public function test_admin_can_access_all_v2_endpoints()
    {
        $this->actingAs($this->admin, 'sanctum');

        $this->getJson('/api/franchises')->assertOk();
        $this->getJson('/api/admin/matrix-receivables')->assertOk();
        $this->getJson('/api/admin/feedback/dashboard')->assertOk();
        $this->getJson('/api/admin/whatsapp-campaigns')->assertOk();
    }

    public function test_franchise_admin_can_access_scoped_endpoints()
    {
        $this->actingAs($this->franchiseAdmin, 'sanctum');

        $this->getJson('/api/franchises')->assertOk();
        $this->getJson('/api/admin/feedback/dashboard')->assertOk();
        $this->getJson('/api/admin/whatsapp-campaigns')->assertOk();
    }

    public function test_mostrador_can_access_franchise_endpoints()
    {
        $this->actingAs($this->mostrador, 'sanctum');

        $this->getJson('/api/franchises')->assertOk();
    }

    public function test_cadete_cannot_access_admin_v2_endpoints()
    {
        $this->actingAs($this->cadete, 'sanctum');

        $this->getJson('/api/franchises')->assertStatus(403);
        $this->getJson('/api/admin/feedback/dashboard')->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_admin_endpoints()
    {
        $this->getJson('/api/franchises')->assertStatus(401);
        $this->getJson('/api/admin/matrix-receivables')->assertStatus(401);
        $this->getJson('/api/admin/feedback/dashboard')->assertStatus(401);
    }

    // --- Cross-Module: Commission → Matrix Receivable ---

    public function test_e2e_commission_pago_confirmado_creates_receivable()
    {
        $customer = Customer::factory()->create();
        $destination = Destination::factory()->create();
        $commission = Commission::factory()->create([
            'client_id' => $customer->id,
            'destination_id' => $destination->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'franchise_id' => $this->franchise->id,
            'total' => 1000,
            'status' => CommissionStatus::PAGO_VALIDACION->value,
        ]);

        $service = new MatrixCommissionService();
        $receivable = $service->createReceivableForCommission($commission);

        $this->assertNotNull($receivable);
        $this->assertEquals('pending', $receivable->status);
        $this->assertEquals($this->franchise->id, $receivable->franchise_id);
        $this->assertEquals($this->franchise->commission_percentage_to_matrix, $receivable->percentage_applied);
    }

    // --- Cross-Module: Commission Delivery → Feedback Survey ---

    public function test_e2e_delivery_creates_feedback_survey()
    {
        $customer = Customer::factory()->create(['mobile' => '1155001234']);
        $destination = Destination::factory()->create();
        $commission = Commission::factory()->create([
            'client_id' => $customer->id,
            'destination_id' => $destination->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'status' => CommissionStatus::ENTREGADO->value,
        ]);

        $feedbackService = app(FeedbackService::class);
        $survey = $feedbackService->createSurveyForCommission($commission);

        $this->assertNotNull($survey);
        $this->assertEquals('pending', $survey->status);
        $this->assertEquals($customer->id, $survey->customer_id);

        $response = $this->postJson('/api/feedback/respond', [
            'token' => $survey->token,
            'rating' => 5,
            'comment' => 'Excelente!',
        ]);
        $response->assertOk();

        $this->actingAs($this->admin, 'sanctum');
        $dashResponse = $this->getJson('/api/admin/feedback/dashboard');
        $dashResponse->assertOk();
        $this->assertEquals(1, $dashResponse->json('total_responses'));
        $this->assertEquals(5.0, $dashResponse->json('average_rating'));
    }

    // --- Cross-Module: Franchise Data Isolation ---

    public function test_franchise_admin_isolation_across_modules()
    {
        $otherFranchise = Franchise::factory()->create();
        $customer = Customer::factory()->create();
        $destination = Destination::factory()->create();

        // Create campaign for this franchise
        WhatsAppCampaign::factory()->create([
            'created_by' => $this->admin->id,
            'franchise_id' => $this->franchise->id,
        ]);
        // Create campaign for another franchise
        WhatsAppCampaign::factory()->create([
            'created_by' => $this->admin->id,
            'franchise_id' => $otherFranchise->id,
        ]);

        // Create feedback for this franchise
        FeedbackSurvey::factory()->responded()->create([
            'franchise_id' => $this->franchise->id,
            'rating' => 5,
        ]);
        // Create feedback for another franchise
        FeedbackSurvey::factory()->responded()->create([
            'franchise_id' => $otherFranchise->id,
            'rating' => 1,
        ]);

        $this->actingAs($this->franchiseAdmin, 'sanctum');

        // Campaigns: only sees own
        $campaigns = $this->getJson('/api/admin/whatsapp-campaigns');
        $campaigns->assertOk();
        $this->assertCount(1, $campaigns->json());

        // Feedback: only sees own franchise stats
        $feedback = $this->getJson('/api/admin/feedback/dashboard');
        $feedback->assertOk();
        $this->assertEquals(1, $feedback->json('total_responses'));
        $this->assertEquals(5.0, $feedback->json('average_rating'));
    }

    // --- IVA Full Flow ---

    public function test_iva_full_flow_customer_update_and_report()
    {
        $customer = Customer::factory()->create(['iva_status' => 'auto']);

        $this->actingAs($this->admin, 'sanctum');

        // Update to always
        $response = $this->patchJson("/api/customers/{$customer->id}/auto-calculate-iva", [
            'iva_status' => 'always',
        ]);
        $response->assertOk();
        $this->assertEquals('always', $response->json('customer.iva_status'));

        // Create invoices with IVA
        Invoice::factory()->count(2)->create([
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'importe_total' => 1210,
            'importe_neto' => 1000,
            'importe_iva' => 210,
            'fecha_emision' => '2026-06-15',
            'status' => 'emitida',
        ]);

        // Get IVA report
        $report = $this->getJson('/api/admin/reports/iva?date_from=2026-06-01&date_to=2026-06-30');
        $report->assertOk();
        $this->assertEquals(420, $report->json('total_iva'));
        $this->assertEquals(2, $report->json('invoice_count'));
    }

    // --- Franchise CRUD + Dashboard ---

    public function test_franchise_crud_and_dashboard_flow()
    {
        $this->actingAs($this->admin, 'sanctum');

        // Create
        $createResponse = $this->postJson('/api/franchises', [
            'name' => 'Test Franchise',
            'slug' => 'test-franchise',
            'commission_percentage_to_matrix' => 8.5,
            'status' => 'active',
        ]);
        $createResponse->assertStatus(201);
        $franchiseId = $createResponse->json('id');

        // Read
        $this->getJson("/api/franchises/{$franchiseId}")->assertOk();

        // Update
        $this->putJson("/api/franchises/{$franchiseId}", [
            'name' => 'Updated Franchise',
            'slug' => 'test-franchise',
            'commission_percentage_to_matrix' => 10,
        ])->assertOk();

        // Dashboard
        $dashboard = $this->getJson("/api/franchises/{$franchiseId}/dashboard");
        $dashboard->assertOk();
        $dashboard->assertJsonStructure([
            'kpis' => [
                'total_commissions',
                'total_commission_amount',
                'total_expenses',
                'total_incomes',
                'matrix_debt',
                'net_result',
            ],
        ]);

        // Delete
        $this->deleteJson("/api/franchises/{$franchiseId}")->assertOk();
    }

    // --- Campaign → Send → Results ---

    public function test_campaign_full_lifecycle()
    {
        Customer::factory()->count(3)->create(['mobile' => '1155001234']);

        $this->actingAs($this->admin, 'sanctum');

        // Create
        $create = $this->postJson('/api/admin/whatsapp-campaigns', [
            'name' => 'Test Campaign',
            'message_template' => 'Hola {nombre}!',
            'segment_filters' => ['has_mobile' => true],
        ]);
        $create->assertStatus(201);
        $id = $create->json('id');

        // Preview
        $preview = $this->postJson("/api/admin/whatsapp-campaigns/{$id}/preview");
        $preview->assertOk();
        $this->assertEquals(3, $preview->json('eligible_with_phone'));

        // Send
        $send = $this->postJson("/api/admin/whatsapp-campaigns/{$id}/send");
        $send->assertOk();
        $this->assertEquals('completed', $send->json('campaign.status'));
        $this->assertEquals(3, $send->json('campaign.sent_count'));

        // Cannot re-send
        $resend = $this->postJson("/api/admin/whatsapp-campaigns/{$id}/send");
        $resend->assertStatus(422);
    }

    // --- Public Feedback Flow ---

    public function test_public_feedback_flow()
    {
        $survey = FeedbackSurvey::factory()->create();

        // View survey (public)
        $show = $this->getJson("/api/feedback/{$survey->token}");
        $show->assertOk();
        $this->assertEquals('pending', $show->json('status'));

        // Respond (public)
        $respond = $this->postJson('/api/feedback/respond', [
            'token' => $survey->token,
            'rating' => 4,
            'comment' => 'Good service',
        ]);
        $respond->assertOk();

        // View again — now responded
        $showAgain = $this->getJson("/api/feedback/{$survey->token}");
        $showAgain->assertOk();
        $this->assertEquals('responded', $showAgain->json('status'));
        $this->assertEquals(4, $showAgain->json('rating'));

        // Cannot respond again
        $respondAgain = $this->postJson('/api/feedback/respond', [
            'token' => $survey->token,
            'rating' => 5,
        ]);
        $respondAgain->assertStatus(404);
    }

    // --- Cross-Module: Commission → Invoice ---

    public function test_e2e_commission_to_invoice_flow()
    {
        $customer = Customer::factory()->create();
        $destination = Destination::factory()->create();
        $commission = Commission::factory()->create([
            'client_id' => $customer->id,
            'destination_id' => $destination->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'franchise_id' => $this->franchise->id,
            'total' => 5000,
            'status' => CommissionStatus::PAGO_VALIDACION->value,
        ]);

        $this->actingAs($this->admin, 'sanctum');

        // Facturar commission
        $invoice = $this->postJson("/api/admin/invoices/commission/{$commission->id}/facturar", [
            'tipo_comprobante' => InvoiceType::FACTURA_B->value,
        ]);
        $invoice->assertStatus(201);
        $this->assertEquals($commission->id, $invoice->json('commission_id'));
        $this->assertNotNull($invoice->json('cae'));

        // View customer invoices
        $list = $this->getJson("/api/admin/invoices/customer/{$customer->id}");
        $list->assertOk();
        $this->assertCount(1, $list->json());

        // Get PDF data
        $invoiceId = $invoice->json('id');
        $pdf = $this->getJson("/api/admin/invoices/{$invoiceId}/pdf");
        $pdf->assertOk();
        $pdf->assertJsonStructure(['invoice', 'letter', 'formatted_number', 'emisor']);
    }

    // --- Cross-Module: Income → Invoice ---

    public function test_e2e_income_to_invoice_flow()
    {
        $customer = Customer::factory()->create();
        $payment = CurrentAccount::create([
            'customer_id' => $customer->id,
            'type' => 'credit',
            'status' => 'OK',
            'amount' => 3000,
            'description' => 'Pago confirmado',
            'transaction_date' => now(),
            'balance' => 3000,
            'payment_method' => 'transfer',
            'franchise_id' => $this->franchise->id,
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $invoice = $this->postJson("/api/admin/invoices/payment/{$payment->id}/facturar");
        $invoice->assertStatus(201);
        $this->assertEquals($payment->id, $invoice->json('current_account_id'));
        $this->assertEquals(3000, (float) $invoice->json('importe_total'));
    }

    // --- Billing Section Full Flow ---

    public function test_billing_full_lifecycle()
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin, 'sanctum');

        // Emit manual invoice
        $create = $this->postJson('/api/admin/invoices', [
            'customer_id' => $customer->id,
            'tipo_comprobante' => InvoiceType::FACTURA_B->value,
            'importe_total' => 2420,
        ]);
        $create->assertStatus(201);
        $id = $create->json('id');

        // List
        $list = $this->getJson('/api/admin/invoices');
        $list->assertOk();
        $this->assertGreaterThanOrEqual(1, $list->json('total'));

        // Show
        $show = $this->getJson("/api/admin/invoices/{$id}");
        $show->assertOk();

        // Get types
        $tipos = $this->getJson('/api/admin/invoices/tipos-comprobante');
        $tipos->assertOk();

        // Anular
        $anular = $this->patchJson("/api/admin/invoices/{$id}/anular");
        $anular->assertOk();
        $this->assertEquals('anulada', $anular->json('status'));
    }
}
