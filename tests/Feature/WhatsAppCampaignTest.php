<?php

namespace Tests\Feature;

use App\Services\CampaignService;
use App\Services\WhatsAppService;
use App\Shared\Models\Branch;
use App\Shared\Models\Customer;
use App\Shared\Models\Franchise;
use App\Shared\Models\User;
use App\Shared\Models\WhatsAppCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsAppCampaignTest extends TestCase
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

    // --- CRUD ---

    public function test_admin_can_list_campaigns()
    {
        WhatsAppCampaign::factory()->count(3)->create(['created_by' => $this->admin->id]);

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->getJson('/api/admin/whatsapp-campaigns');

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }

    public function test_admin_can_create_campaign()
    {
        $this->actingAs($this->admin, 'sanctum');
        $response = $this->postJson('/api/admin/whatsapp-campaigns', [
            'name' => 'Promo Verano',
            'message_template' => 'Hola {nombre}, tenemos una promo para vos!',
            'segment_filters' => ['city' => 'Buenos Aires', 'is_premium' => true],
        ]);

        $response->assertStatus(201);
        $this->assertEquals('Promo Verano', $response->json('name'));
        $this->assertEquals('draft', $response->json('status'));
        $this->assertDatabaseHas('whatsapp_campaigns', ['name' => 'Promo Verano']);
    }

    public function test_admin_can_create_scheduled_campaign()
    {
        $this->actingAs($this->admin, 'sanctum');
        $scheduledAt = now()->addDays(1)->toISOString();

        $response = $this->postJson('/api/admin/whatsapp-campaigns', [
            'name' => 'Campaña Programada',
            'message_template' => 'Hola {nombre}!',
            'scheduled_at' => $scheduledAt,
        ]);

        $response->assertStatus(201);
        $this->assertEquals('scheduled', $response->json('status'));
    }

    public function test_admin_can_view_campaign_detail()
    {
        $campaign = WhatsAppCampaign::factory()->create(['created_by' => $this->admin->id]);

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->getJson("/api/admin/whatsapp-campaigns/{$campaign->id}");

        $response->assertOk();
        $this->assertEquals($campaign->name, $response->json('name'));
    }

    public function test_admin_can_update_draft_campaign()
    {
        $campaign = WhatsAppCampaign::factory()->create([
            'created_by' => $this->admin->id,
            'status' => 'draft',
        ]);

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->putJson("/api/admin/whatsapp-campaigns/{$campaign->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertOk();
        $this->assertEquals('Updated Name', $response->json('name'));
    }

    public function test_cannot_update_completed_campaign()
    {
        $campaign = WhatsAppCampaign::factory()->completed()->create([
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->putJson("/api/admin/whatsapp-campaigns/{$campaign->id}", [
            'name' => 'Should Fail',
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_delete_draft_campaign()
    {
        $campaign = WhatsAppCampaign::factory()->create([
            'created_by' => $this->admin->id,
            'status' => 'draft',
        ]);

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->deleteJson("/api/admin/whatsapp-campaigns/{$campaign->id}");

        $response->assertOk();
        $this->assertSoftDeleted('whatsapp_campaigns', ['id' => $campaign->id]);
    }

    public function test_cannot_delete_sending_campaign()
    {
        $campaign = WhatsAppCampaign::factory()->create([
            'created_by' => $this->admin->id,
            'status' => 'sending',
        ]);

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->deleteJson("/api/admin/whatsapp-campaigns/{$campaign->id}");

        $response->assertStatus(422);
    }

    // --- Cancel ---

    public function test_admin_can_cancel_scheduled_campaign()
    {
        $campaign = WhatsAppCampaign::factory()->scheduled()->create([
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->patchJson("/api/admin/whatsapp-campaigns/{$campaign->id}/cancel");

        $response->assertOk();
        $this->assertEquals('cancelled', $response->json('status'));
    }

    public function test_cannot_cancel_completed_campaign()
    {
        $campaign = WhatsAppCampaign::factory()->completed()->create([
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->patchJson("/api/admin/whatsapp-campaigns/{$campaign->id}/cancel");

        $response->assertStatus(422);
    }

    // --- Segmentation ---

    public function test_segment_preview_returns_customers()
    {
        Customer::factory()->count(3)->create(['city' => 'Buenos Aires', 'mobile' => '1155001234']);
        Customer::factory()->count(2)->create(['city' => 'Córdoba', 'mobile' => '3514001234']);

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->getJson('/api/admin/whatsapp-campaigns/segment-preview?city=Buenos%20Aires&has_mobile=true');

        $response->assertOk();
        $this->assertEquals(3, $response->json('eligible_with_phone'));
    }

    public function test_segment_preview_filters_premium()
    {
        Customer::factory()->count(2)->create(['is_premium' => true, 'mobile' => '1155001234']);
        Customer::factory()->count(3)->create(['is_premium' => false, 'mobile' => '1155005678']);

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->getJson('/api/admin/whatsapp-campaigns/segment-preview?is_premium=true');

        $response->assertOk();
        $this->assertEquals(2, $response->json('eligible_with_phone'));
    }

    public function test_segment_excludes_customers_without_mobile()
    {
        Customer::factory()->count(2)->create(['mobile' => '1155001234']);
        Customer::factory()->count(3)->create(['mobile' => null, 'phone' => null]);

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->getJson('/api/admin/whatsapp-campaigns/segment-preview?has_mobile=true');

        $response->assertOk();
        $this->assertEquals(2, $response->json('eligible_with_phone'));
    }

    // --- Send ---

    public function test_send_campaign_with_mocked_whatsapp()
    {
        $mockWhatsApp = $this->createMock(WhatsAppService::class);
        $mockWhatsApp->method('sendMessage')->willReturn(true);
        $this->app->instance(WhatsAppService::class, $mockWhatsApp);

        Customer::factory()->count(2)->create(['mobile' => '1155001234', 'city' => 'Test']);

        $campaign = WhatsAppCampaign::factory()->create([
            'created_by' => $this->admin->id,
            'status' => 'draft',
            'message_template' => 'Hola {nombre}!',
            'segment_filters' => ['has_mobile' => true],
        ]);

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->postJson("/api/admin/whatsapp-campaigns/{$campaign->id}/send");

        $response->assertOk();
        $this->assertEquals('completed', $response->json('campaign.status'));
        $this->assertEquals(2, $response->json('campaign.sent_count'));
        $this->assertEquals(0, $response->json('campaign.failed_count'));
        $this->assertDatabaseCount('whatsapp_campaign_messages', 2);
    }

    public function test_send_campaign_tracks_failures()
    {
        $mockWhatsApp = $this->createMock(WhatsAppService::class);
        $mockWhatsApp->method('sendMessage')->willReturn(false);
        $this->app->instance(WhatsAppService::class, $mockWhatsApp);

        Customer::factory()->count(2)->create(['mobile' => '1155001234']);

        $campaign = WhatsAppCampaign::factory()->create([
            'created_by' => $this->admin->id,
            'status' => 'draft',
            'message_template' => 'Test',
            'segment_filters' => ['has_mobile' => true],
        ]);

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->postJson("/api/admin/whatsapp-campaigns/{$campaign->id}/send");

        $response->assertOk();
        $this->assertEquals(0, $response->json('campaign.sent_count'));
        $this->assertEquals(2, $response->json('campaign.failed_count'));
    }

    public function test_cannot_send_completed_campaign()
    {
        $campaign = WhatsAppCampaign::factory()->completed()->create([
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->postJson("/api/admin/whatsapp-campaigns/{$campaign->id}/send");

        $response->assertStatus(422);
    }

    // --- Preview ---

    public function test_campaign_preview_shows_recipients()
    {
        Customer::factory()->count(3)->create(['mobile' => '1155001234']);

        $campaign = WhatsAppCampaign::factory()->create([
            'created_by' => $this->admin->id,
            'message_template' => 'Hola {nombre}, promo en {ciudad}!',
            'segment_filters' => ['has_mobile' => true],
        ]);

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->postJson("/api/admin/whatsapp-campaigns/{$campaign->id}/preview");

        $response->assertOk();
        $response->assertJsonStructure(['total_customers', 'eligible_with_phone', 'sample_message', 'customers']);
        $this->assertEquals(3, $response->json('eligible_with_phone'));
    }

    // --- Franchise scoping ---

    public function test_franchise_admin_only_sees_own_campaigns()
    {
        $franchise = Franchise::factory()->create();
        $franchiseAdmin = User::factory()->create([
            'role' => 'admin_franquicia',
            'franchise_id' => $franchise->id,
        ]);

        WhatsAppCampaign::factory()->create([
            'created_by' => $this->admin->id,
            'franchise_id' => $franchise->id,
        ]);
        WhatsAppCampaign::factory()->create([
            'created_by' => $this->admin->id,
            'franchise_id' => null,
        ]);

        $this->actingAs($franchiseAdmin, 'sanctum');
        $response = $this->getJson('/api/admin/whatsapp-campaigns');

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    // --- Service unit tests ---

    public function test_render_message_replaces_placeholders()
    {
        $service = new CampaignService(new WhatsAppService());
        $customer = Customer::factory()->create([
            'name' => 'Juan',
            'last_name' => 'Pérez',
            'city' => 'Buenos Aires',
        ]);

        $result = $service->renderMessage('Hola {nombre} {apellido} de {ciudad}!', $customer);
        $this->assertEquals('Hola Juan Pérez de Buenos Aires!', $result);
    }

    // --- Validation ---

    public function test_create_campaign_validates_name()
    {
        $this->actingAs($this->admin, 'sanctum');
        $response = $this->postJson('/api/admin/whatsapp-campaigns', [
            'message_template' => 'Test',
        ]);

        $response->assertStatus(422);
    }

    public function test_create_campaign_validates_message_template()
    {
        $this->actingAs($this->admin, 'sanctum');
        $response = $this->postJson('/api/admin/whatsapp-campaigns', [
            'name' => 'Test',
        ]);

        $response->assertStatus(422);
    }

    // --- New configurable fields ---

    public function test_admin_can_create_campaign_with_advanced_fields()
    {
        Storage::fake('public');

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->post('/api/admin/whatsapp-campaigns', [
            'name' => 'Campaña Avanzada',
            'description' => 'Promo de invierno con imagen',
            'message_template' => 'Hola {nombre}, mirá esta promo! Saldo: {saldo}',
            'image' => UploadedFile::fake()->image('promo.jpg', 400, 300),
            'message_delay_ms' => 2000,
            'send_time_start' => '09:00',
            'send_time_end' => '20:00',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201);
        $this->assertEquals('Campaña Avanzada', $response->json('name'));
        $this->assertEquals('Promo de invierno con imagen', $response->json('description'));
        $this->assertNotNull($response->json('image_url'));
        $this->assertEquals(2000, $response->json('message_delay_ms'));
        Storage::disk('public')->assertExists('campaigns/' . basename($response->json('image_url')));
    }

    public function test_create_campaign_validates_image_file()
    {
        $this->actingAs($this->admin, 'sanctum');
        $response = $this->post('/api/admin/whatsapp-campaigns', [
            'name' => 'Test',
            'message_template' => 'Test msg',
            'image' => UploadedFile::fake()->create('doc.pdf', 100),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('image');
    }

    public function test_create_campaign_validates_delay_range()
    {
        $this->actingAs($this->admin, 'sanctum');
        $response = $this->postJson('/api/admin/whatsapp-campaigns', [
            'name' => 'Test',
            'message_template' => 'Test msg',
            'message_delay_ms' => 100,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('message_delay_ms');
    }

    public function test_segment_preview_filters_by_branch()
    {
        $branch = Branch::factory()->create();
        Customer::factory()->count(2)->create(['branch_id' => $branch->id, 'mobile' => '1155001234']);
        Customer::factory()->count(3)->create(['mobile' => '1155005678']);

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->getJson("/api/admin/whatsapp-campaigns/segment-preview?branch_id={$branch->id}&has_mobile=true");

        $response->assertOk();
        $this->assertEquals(2, $response->json('eligible_with_phone'));
    }

    public function test_segment_preview_filters_by_date_range()
    {
        Customer::factory()->count(2)->create([
            'mobile' => '1155001234',
            'created_at' => '2026-01-15',
        ]);
        Customer::factory()->count(3)->create([
            'mobile' => '1155005678',
            'created_at' => '2026-05-10',
        ]);

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->getJson('/api/admin/whatsapp-campaigns/segment-preview?created_after=2026-05-01&has_mobile=true');

        $response->assertOk();
        $this->assertEquals(3, $response->json('eligible_with_phone'));
    }

    public function test_segment_preview_filters_has_email()
    {
        Customer::factory()->create(['mobile' => '1155001234', 'email' => 'test1@example.com']);
        Customer::factory()->create(['mobile' => '1155001235', 'email' => 'test2@example.com']);
        Customer::factory()->create(['mobile' => '1155005678', 'email' => 'no1@x.com', 'city' => 'NoEmailCity']);
        Customer::factory()->create(['mobile' => '1155005679', 'email' => 'no2@x.com', 'city' => 'NoEmailCity']);

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->getJson('/api/admin/whatsapp-campaigns/segment-preview?has_email=true&has_mobile=true');

        $response->assertOk();
        $this->assertEquals(4, $response->json('eligible_with_phone'));
    }

    public function test_render_message_replaces_new_variables()
    {
        $service = new CampaignService(new WhatsAppService());
        $customer = Customer::factory()->create([
            'name' => 'María',
            'last_name' => 'López',
            'email' => 'maria@test.com',
            'address' => 'Av. Corrientes 1234',
            'dni' => 12345678,
        ]);

        $result = $service->renderMessage(
            '{nombre} - DNI: {dni} - Email: {email} - Dir: {direccion}',
            $customer
        );
        $this->assertStringContainsString('María', $result);
        $this->assertStringContainsString('12345678', $result);
        $this->assertStringContainsString('maria@test.com', $result);
        $this->assertStringContainsString('Av. Corrientes 1234', $result);
    }

    public function test_send_campaign_with_image_uses_file_method()
    {
        $mockWhatsApp = $this->createMock(WhatsAppService::class);
        $mockWhatsApp->expects($this->exactly(2))
            ->method('sendFileByUrl')
            ->willReturn(true);
        $mockWhatsApp->expects($this->never())->method('sendMessage');
        $this->app->instance(WhatsAppService::class, $mockWhatsApp);

        Customer::factory()->count(2)->create(['mobile' => '1155001234']);

        $campaign = WhatsAppCampaign::factory()->create([
            'created_by' => $this->admin->id,
            'status' => 'draft',
            'message_template' => 'Mirá esta promo!',
            'image_url' => 'https://example.com/promo.jpg',
            'segment_filters' => ['has_mobile' => true],
        ]);

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->postJson("/api/admin/whatsapp-campaigns/{$campaign->id}/send");

        $response->assertOk();
        $this->assertEquals(2, $response->json('campaign.sent_count'));
    }

    public function test_update_campaign_with_advanced_fields()
    {
        $campaign = WhatsAppCampaign::factory()->create([
            'created_by' => $this->admin->id,
            'status' => 'draft',
        ]);

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->putJson("/api/admin/whatsapp-campaigns/{$campaign->id}", [
            'description' => 'Updated desc',
            'message_delay_ms' => 3000,
            'send_time_start' => '10:00',
            'send_time_end' => '18:00',
        ]);

        $response->assertOk();
        $this->assertEquals('Updated desc', $response->json('description'));
        $this->assertEquals(3000, $response->json('message_delay_ms'));
    }
}
