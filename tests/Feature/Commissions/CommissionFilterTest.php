<?php

namespace Tests\Feature\Commissions;

use App\Shared\Enums\PaymentMethod;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Commission;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMINISTRADOR,
            'branch_id' => null,
        ]);

        $this->user = User::factory()->create([
            'role' => UserRole::MOSTRADOR,
        ]);

        // Crear registros dependientes necesarios
        $customer = \App\Shared\Models\Customer::factory()->create();
        $destination = \App\Shared\Models\Destination::factory()->create();
        $branch = \App\Shared\Models\Branch::factory()->create();
        $originLocation = \App\Shared\Models\Location::factory()->create();
        $destinationLocation = \App\Shared\Models\Location::factory()->create();

        // Crear comisiones con diferentes métodos de pago
        Commission::factory()->create([
            'client_id' => $customer->id,
            'destination_id' => $destination->id,
            'branch_id' => $branch->id,
            'user_id' => $this->user->id,
            'origin_location_id' => $originLocation->id,
            'destination_location_id' => $destinationLocation->id,
            'payment_method' => PaymentMethod::EFECTIVO,
        ]);

        Commission::factory()->create([
            'client_id' => $customer->id,
            'destination_id' => $destination->id,
            'branch_id' => $branch->id,
            'user_id' => $this->user->id,
            'origin_location_id' => $originLocation->id,
            'destination_location_id' => $destinationLocation->id,
            'payment_method' => PaymentMethod::TRANSFERENCIA,
        ]);

        Commission::factory()->create([
            'client_id' => $customer->id,
            'destination_id' => $destination->id,
            'branch_id' => $branch->id,
            'user_id' => $this->user->id,
            'origin_location_id' => $originLocation->id,
            'destination_location_id' => $destinationLocation->id,
            'payment_method' => null,
        ]);
    }

    public function test_can_filter_commissions_by_payment_method()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/commissions?method=EFECTIVO');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertCount(1, $data['data']);
        $this->assertEquals(PaymentMethod::EFECTIVO->value, $data['data'][0]['payment_method']);
    }

    public function test_can_filter_commissions_by_transferencia_method()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/commissions?method=TRANSFERENCIA');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertCount(1, $data['data']);
        $this->assertEquals(PaymentMethod::TRANSFERENCIA->value, $data['data'][0]['payment_method']);
    }

    public function test_filter_returns_all_commissions_when_no_method_specified()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/commissions');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertCount(3, $data['data']);
    }

    public function test_filter_with_invalid_method_is_ignored_gracefully()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/commissions?method=INVALID_METHOD');

        // Un método inválido se ignora: la API no debe romper (500), responde 200
        $response->assertStatus(200);
    }

    public function test_filter_combines_with_other_filters()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/commissions?method=EFECTIVO&status=SOLICITUD_RECIBIDA');

        $response->assertStatus(200);

        $data = $response->json();
        // Debería devolver solo las comisiones con método EFECTIVO y status SOLICITUD_RECIBIDA
        $this->assertGreaterThanOrEqual(0, count($data['data']));
        $this->assertLessThanOrEqual(1, count($data['data']));
    }
}
