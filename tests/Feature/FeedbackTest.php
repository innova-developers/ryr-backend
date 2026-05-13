<?php

namespace Tests\Feature;

use App\Services\FeedbackService;
use App\Services\WhatsAppService;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\FeedbackSurvey;
use App\Shared\Models\Franchise;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FeedbackTest extends TestCase
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

        $mock = $this->createMock(WhatsAppService::class);
        $mock->method('sendMessage')->willReturn(true);
        $this->app->instance(WhatsAppService::class, $mock);
    }

    // --- Service: Create Survey ---

    public function test_create_survey_for_commission()
    {
        $customer = Customer::factory()->create(['mobile' => '1155001234']);
        $destination = Destination::factory()->create();
        $commission = Commission::factory()->create([
            'client_id' => $customer->id,
            'destination_id' => $destination->id,
        ]);

        $service = app(FeedbackService::class);
        $survey = $service->createSurveyForCommission($commission);

        $this->assertNotNull($survey);
        $this->assertEquals('pending', $survey->status);
        $this->assertEquals($commission->id, $survey->commission_id);
        $this->assertEquals($customer->id, $survey->customer_id);
        $this->assertEquals(64, strlen($survey->token));
        $this->assertDatabaseHas('feedback_surveys', ['commission_id' => $commission->id]);
    }

    public function test_does_not_create_duplicate_survey()
    {
        $customer = Customer::factory()->create();
        $destination = Destination::factory()->create();
        $commission = Commission::factory()->create([
            'client_id' => $customer->id,
            'destination_id' => $destination->id,
        ]);

        $service = app(FeedbackService::class);
        $first = $service->createSurveyForCommission($commission);
        $second = $service->createSurveyForCommission($commission);

        $this->assertEquals($first->id, $second->id);
        $this->assertDatabaseCount('feedback_surveys', 1);
    }

    // --- Service: Respond ---

    public function test_respond_to_survey()
    {
        $survey = FeedbackSurvey::factory()->create();

        $service = app(FeedbackService::class);
        $result = $service->respondToSurvey($survey->token, 5, 'Excelente servicio!');

        $this->assertNotNull($result);
        $this->assertEquals(5, $result->rating);
        $this->assertEquals('Excelente servicio!', $result->comment);
        $this->assertEquals('responded', $result->status);
        $this->assertNotNull($result->responded_at);
    }

    public function test_cannot_respond_twice()
    {
        $survey = FeedbackSurvey::factory()->responded()->create();

        $service = app(FeedbackService::class);
        $result = $service->respondToSurvey($survey->token, 3);

        $this->assertNull($result);
    }

    public function test_invalid_token_returns_null()
    {
        $service = app(FeedbackService::class);
        $result = $service->respondToSurvey(str_repeat('x', 64), 5);
        $this->assertNull($result);
    }

    // --- Service: Dashboard Stats ---

    public function test_dashboard_stats_with_responses()
    {
        FeedbackSurvey::factory()->responded()->create(['rating' => 5]);
        FeedbackSurvey::factory()->responded()->create(['rating' => 4]);
        FeedbackSurvey::factory()->responded()->create(['rating' => 3]);
        FeedbackSurvey::factory()->create();

        $service = app(FeedbackService::class);
        $stats = $service->getDashboardStats();

        $this->assertEquals(3, $stats['total_responses']);
        $this->assertEquals(4.0, $stats['average_rating']);
        $this->assertEquals(1, $stats['pending_count']);
        $this->assertEquals(1, $stats['rating_distribution'][3]);
        $this->assertEquals(1, $stats['rating_distribution'][4]);
        $this->assertEquals(1, $stats['rating_distribution'][5]);
        $this->assertGreaterThan(0, $stats['response_rate']);
    }

    public function test_dashboard_stats_empty()
    {
        $service = app(FeedbackService::class);
        $stats = $service->getDashboardStats();

        $this->assertEquals(0, $stats['total_responses']);
        $this->assertEquals(0, $stats['average_rating']);
        $this->assertEquals(0, $stats['pending_count']);
    }

    public function test_dashboard_stats_scoped_by_franchise()
    {
        $franchise = Franchise::factory()->create();
        FeedbackSurvey::factory()->responded()->create(['franchise_id' => $franchise->id, 'rating' => 5]);
        FeedbackSurvey::factory()->responded()->create(['franchise_id' => null, 'rating' => 1]);

        $service = app(FeedbackService::class);
        $stats = $service->getDashboardStats($franchise->id);

        $this->assertEquals(1, $stats['total_responses']);
        $this->assertEquals(5.0, $stats['average_rating']);
    }

    // --- API: Public respond ---

    public function test_api_respond_to_survey()
    {
        $survey = FeedbackSurvey::factory()->create();

        $response = $this->postJson('/api/feedback/respond', [
            'token' => $survey->token,
            'rating' => 4,
            'comment' => 'Buen servicio',
        ]);

        $response->assertOk();
        $this->assertEquals('responded', $response->json('survey.status'));
        $this->assertEquals(4, $response->json('survey.rating'));
    }

    public function test_api_respond_validates_rating()
    {
        $survey = FeedbackSurvey::factory()->create();

        $response = $this->postJson('/api/feedback/respond', [
            'token' => $survey->token,
            'rating' => 6,
        ]);

        $response->assertStatus(422);
    }

    public function test_api_respond_invalid_token()
    {
        $response = $this->postJson('/api/feedback/respond', [
            'token' => str_repeat('a', 64),
            'rating' => 5,
        ]);

        $response->assertStatus(404);
    }

    // --- API: Public show ---

    public function test_api_show_survey_by_token()
    {
        $survey = FeedbackSurvey::factory()->create();

        $response = $this->getJson("/api/feedback/{$survey->token}");

        $response->assertOk();
        $response->assertJsonStructure(['status', 'commission_id', 'customer_name']);
    }

    // --- API: Admin dashboard ---

    public function test_api_admin_dashboard()
    {
        FeedbackSurvey::factory()->responded()->count(5)->create();

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->getJson('/api/admin/feedback/dashboard');

        $response->assertOk();
        $response->assertJsonStructure([
            'total_responses',
            'average_rating',
            'rating_distribution',
            'pending_count',
            'response_rate',
            'recent_feedback',
        ]);
        $this->assertEquals(5, $response->json('total_responses'));
    }

    // --- API: Admin list ---

    public function test_api_admin_list_feedback()
    {
        FeedbackSurvey::factory()->responded()->count(3)->create();
        FeedbackSurvey::factory()->count(2)->create();

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->getJson('/api/admin/feedback');

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
    }

    public function test_api_admin_list_filter_by_status()
    {
        FeedbackSurvey::factory()->responded()->count(3)->create();
        FeedbackSurvey::factory()->count(2)->create();

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->getJson('/api/admin/feedback?status=responded');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    // --- Franchise scoping ---

    public function test_franchise_admin_scoped_dashboard()
    {
        $franchise = Franchise::factory()->create();
        $franchiseAdmin = User::factory()->create([
            'role' => 'admin_franquicia',
            'franchise_id' => $franchise->id,
        ]);

        FeedbackSurvey::factory()->responded()->create(['franchise_id' => $franchise->id, 'rating' => 5]);
        FeedbackSurvey::factory()->responded()->create(['franchise_id' => null, 'rating' => 1]);

        $this->actingAs($franchiseAdmin, 'sanctum');
        $response = $this->getJson('/api/admin/feedback/dashboard');

        $response->assertOk();
        $this->assertEquals(1, $response->json('total_responses'));
        $this->assertEquals(5.0, $response->json('average_rating'));
    }
}
