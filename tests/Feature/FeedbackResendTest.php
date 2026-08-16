<?php

namespace Tests\Feature;

use App\Mail\FeedbackSurveyMail;
use App\Services\WhatsAppService;
use App\Shared\Models\Branch;
use App\Shared\Models\Customer;
use App\Shared\Models\FeedbackSurvey;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

/**
 * RC-507 — Reenvío de la encuesta desde el dashboard, eligiendo canal.
 *
 * Antes la encuesta sólo salía por WhatsApp y, si el cliente no la contestaba, no
 * había forma de volver a pedírsela: quedaba pendiente para siempre.
 */
class FeedbackResendTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'branch_id' => Branch::factory()->create()->id,
        ]);
    }

    private function survey(array $customerAttrs = [], array $surveyAttrs = []): FeedbackSurvey
    {
        $customer = Customer::factory()->create($customerAttrs + [
            'mobile' => '3492326189',
            'email' => 'cliente@example.com',
        ]);

        return FeedbackSurvey::factory()->create($surveyAttrs + [
            'customer_id' => $customer->id,
            'status' => 'pending',
            'token' => str_repeat('a', 64),
        ]);
    }

    private function mockWhatsApp(int $veces = 1): void
    {
        $mock = Mockery::mock(WhatsAppService::class);
        $mock->shouldReceive('sendMessage')->times($veces)->andReturn(true);
        $this->app->instance(WhatsAppService::class, $mock);
    }

    public function test_resend_by_whatsapp(): void
    {
        $this->mockWhatsApp();
        $survey = $this->survey();

        $this->actingAs($this->admin)
            ->postJson("/api/admin/feedback/{$survey->id}/resend", ['channels' => ['whatsapp']])
            ->assertOk()
            ->assertJsonPath('sent', ['whatsapp']);

        Mail::assertNothingSent();
    }

    public function test_resend_by_email(): void
    {
        $this->mockWhatsApp(0);
        $survey = $this->survey();

        $this->actingAs($this->admin)
            ->postJson("/api/admin/feedback/{$survey->id}/resend", ['channels' => ['email']])
            ->assertOk()
            ->assertJsonPath('sent', ['email']);

        Mail::assertSent(FeedbackSurveyMail::class);
    }

    public function test_resend_by_both_channels(): void
    {
        $this->mockWhatsApp();
        $survey = $this->survey();

        $this->actingAs($this->admin)
            ->postJson("/api/admin/feedback/{$survey->id}/resend", ['channels' => ['whatsapp', 'email']])
            ->assertOk()
            ->assertJsonPath('sent', ['whatsapp', 'email']);

        Mail::assertSent(FeedbackSurveyMail::class);
    }

    public function test_missing_contact_is_reported_not_silent(): void
    {
        $this->mockWhatsApp(0);
        $survey = $this->survey(['mobile' => null, 'phone' => null]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/feedback/{$survey->id}/resend", ['channels' => ['whatsapp']])
            ->assertStatus(422)
            ->assertJsonPath('skipped.whatsapp', 'El cliente no tiene teléfono cargado');
    }

    public function test_partial_send_reports_the_skipped_channel(): void
    {
        $this->mockWhatsApp();
        $survey = $this->survey(['email' => '']);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/feedback/{$survey->id}/resend", ['channels' => ['whatsapp', 'email']])
            ->assertOk()
            ->assertJsonPath('sent', ['whatsapp'])
            ->assertJsonPath('skipped.email', 'El cliente no tiene email cargado');
    }

    public function test_answered_survey_cannot_be_resent(): void
    {
        $this->mockWhatsApp(0);
        $survey = $this->survey([], ['status' => 'responded', 'rating' => 5]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/feedback/{$survey->id}/resend", ['channels' => ['whatsapp']])
            ->assertStatus(422);
    }

    public function test_resend_does_not_change_the_token(): void
    {
        $this->mockWhatsApp();
        $survey = $this->survey();
        $tokenOriginal = $survey->token;

        $this->actingAs($this->admin)
            ->postJson("/api/admin/feedback/{$survey->id}/resend", ['channels' => ['whatsapp']])
            ->assertOk();

        // El link que ya tiene el cliente tiene que seguir sirviendo.
        $this->assertSame($tokenOriginal, $survey->fresh()->token);
    }

    public function test_resend_is_recorded(): void
    {
        $this->mockWhatsApp(2);
        $survey = $this->survey();

        $this->actingAs($this->admin)->postJson("/api/admin/feedback/{$survey->id}/resend", ['channels' => ['whatsapp']]);
        $this->actingAs($this->admin)->postJson("/api/admin/feedback/{$survey->id}/resend", ['channels' => ['whatsapp']]);

        $fresh = $survey->fresh();
        $this->assertSame(2, (int) $fresh->resend_count);
        $this->assertSame('whatsapp', $fresh->last_resent_channels);
        $this->assertNotNull($fresh->last_resent_at);
    }

    public function test_channel_must_be_valid(): void
    {
        $survey = $this->survey();

        $this->actingAs($this->admin)
            ->postJson("/api/admin/feedback/{$survey->id}/resend", ['channels' => ['paloma']])
            ->assertStatus(422);
    }

    public function test_resend_requires_admin(): void
    {
        $survey = $this->survey();

        $this->postJson("/api/admin/feedback/{$survey->id}/resend", ['channels' => ['whatsapp']])
            ->assertStatus(401);
    }

    public function test_unknown_survey_returns_404(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/admin/feedback/999999/resend', ['channels' => ['whatsapp']])
            ->assertStatus(404);
    }
}
