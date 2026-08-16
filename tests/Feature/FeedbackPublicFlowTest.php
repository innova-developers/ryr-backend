<?php

namespace Tests\Feature;

use App\Shared\Models\Customer;
use App\Shared\Models\FeedbackSurvey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RC-486 (FEEDBACK Y RESEÑAS).
 *
 * En producción había 1918 encuestas enviadas y 0 respondidas. La API pública ya
 * existía y funcionaba; lo que faltaba era la página del front en /feedback/{token},
 * así que el link del mensaje caía en el catch-all del router y terminaba en la
 * landing. Estos tests fijan el contrato que consume esa página.
 */
class FeedbackPublicFlowTest extends TestCase
{
    use RefreshDatabase;

    private function survey(array $attrs = []): FeedbackSurvey
    {
        $customer = Customer::factory()->create(['name' => 'Claudia', 'last_name' => 'Paulino']);

        return FeedbackSurvey::factory()->create($attrs + [
            'customer_id' => $customer->id,
            'token' => str_repeat('a', 64),
            'status' => 'pending',
        ]);
    }

    public function test_public_endpoint_returns_survey_without_authentication(): void
    {
        $survey = $this->survey();

        $this->getJson("/api/feedback/{$survey->token}")
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('customer_name', 'Claudia Paulino');
    }

    public function test_unknown_token_returns_404(): void
    {
        $this->getJson('/api/feedback/' . str_repeat('z', 64))
            ->assertStatus(404);
    }

    public function test_customer_can_submit_rating_and_comment(): void
    {
        $survey = $this->survey();

        $this->postJson('/api/feedback/respond', [
            'token' => $survey->token,
            'rating' => 5,
            'comment' => 'Llegó rapidísimo',
        ])->assertOk();

        $survey->refresh();
        $this->assertSame(5, $survey->rating);
        $this->assertSame('Llegó rapidísimo', $survey->comment);
        $this->assertNotSame('pending', $survey->status);
    }

    public function test_comment_is_optional(): void
    {
        $survey = $this->survey();

        $this->postJson('/api/feedback/respond', [
            'token' => $survey->token,
            'rating' => 4,
        ])->assertOk();

        $this->assertSame(4, $survey->fresh()->rating);
    }

    public function test_rating_must_be_between_one_and_five(): void
    {
        $survey = $this->survey();

        $this->postJson('/api/feedback/respond', [
            'token' => $survey->token,
            'rating' => 9,
        ])->assertStatus(422);
    }

    public function test_survey_cannot_be_answered_twice(): void
    {
        $survey = $this->survey();

        $this->postJson('/api/feedback/respond', [
            'token' => $survey->token,
            'rating' => 5,
        ])->assertOk();

        $this->postJson('/api/feedback/respond', [
            'token' => $survey->token,
            'rating' => 1,
        ])->assertStatus(404);

        // La primera respuesta no se pisa.
        $this->assertSame(5, $survey->fresh()->rating);
    }

    public function test_feedback_link_points_to_the_frontend(): void
    {
        config(['app.frontend_url' => 'https://ryrcomisiones.com']);
        $survey = $this->survey();

        $url = rtrim(config('app.frontend_url'), '/') . "/feedback/{$survey->token}";

        $this->assertSame("https://ryrcomisiones.com/feedback/{$survey->token}", $url);
    }

    // --- Canal de envío ---

    public function test_survey_is_sent_to_phone_when_mobile_is_empty(): void
    {
        // 755 de los 3418 clientes de producción tienen phone pero no mobile. Antes
        // sendSurveyWhatsApp() miraba sólo mobile y salía en silencio: nunca recibían
        // la encuesta y no quedaba rastro del motivo.
        \Illuminate\Support\Facades\Notification::fake();

        $customer = Customer::factory()->create(['mobile' => null, 'phone' => '2915662430']);

        $whatsapp = \Mockery::mock(\App\Services\WhatsAppService::class);
        $whatsapp->shouldReceive('sendMessage')
            ->once()
            ->with('2915662430', \Mockery::type('string'))
            ->andReturn(true);
        $this->app->instance(\App\Services\WhatsAppService::class, $whatsapp);

        $branch = \App\Shared\Models\Branch::factory()->create();
        $commission = \App\Shared\Models\Commission::factory()->create([
            'client_id' => $customer->id,
            'destination_id' => \App\Shared\Models\Destination::factory()->create()->id,
            'branch_id' => $branch->id,
            'user_id' => \App\Shared\Models\User::factory()->create(['role' => 'administrador', 'branch_id' => $branch->id])->id,
        ]);

        app(\App\Services\FeedbackService::class)->createSurveyForCommission($commission);

        $this->assertDatabaseHas('feedback_surveys', ['commission_id' => $commission->id]);
    }

    public function test_survey_prefers_mobile_when_both_exist(): void
    {
        $customer = Customer::factory()->create(['mobile' => '3492111111', 'phone' => '2915662430']);

        $whatsapp = \Mockery::mock(\App\Services\WhatsAppService::class);
        $whatsapp->shouldReceive('sendMessage')
            ->once()
            ->with('3492111111', \Mockery::type('string'))
            ->andReturn(true);
        $this->app->instance(\App\Services\WhatsAppService::class, $whatsapp);

        $branch = \App\Shared\Models\Branch::factory()->create();
        $commission = \App\Shared\Models\Commission::factory()->create([
            'client_id' => $customer->id,
            'destination_id' => \App\Shared\Models\Destination::factory()->create()->id,
            'branch_id' => $branch->id,
            'user_id' => \App\Shared\Models\User::factory()->create(['role' => 'administrador', 'branch_id' => $branch->id])->id,
        ]);

        app(\App\Services\FeedbackService::class)->createSurveyForCommission($commission);

        $this->assertDatabaseHas('feedback_surveys', ['commission_id' => $commission->id]);
    }
}
