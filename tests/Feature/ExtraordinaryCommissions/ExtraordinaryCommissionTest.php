<?php

namespace Feature\ExtraordinaryCommissions;

use App\Shared\Models\ExtraordinaryCommission;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtraordinaryCommissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create(['role' => 'administrador']);
        $this->actingAs($user, 'sanctum');
    }

    public function test_can_create_extraordinary_commission(): void
    {
        $data = [
            'origin' => 'Buenos Aires',
            'destination' => 'Córdoba',
            'detail' => 'Servicio especial',
            'price' => 1500.50,
            'observations' => 'Comisión por servicio urgente',
        ];

        $response = $this->postJson('/api/extraordinary-commissions', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'origin',
                'destination',
                'detail',
                'price',
                'observations',
                'created_at',
                'updated_at',
            ]);

        $this->assertDatabaseHas('extraordinary_commissions', $data);
    }

    public function test_can_list_extraordinary_commissions(): void
    {
        ExtraordinaryCommission::factory()->count(3)->create();

        $response = $this->getJson('/api/extraordinary-commissions');

        $response->assertStatus(200)
            ->assertJsonCount(3)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'origin',
                    'destination',
                    'detail',
                    'price',
                    'observations',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    public function test_can_show_extraordinary_commission(): void
    {
        $commission = ExtraordinaryCommission::factory()->create();

        $response = $this->getJson("/api/extraordinary-commissions/{$commission->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'origin',
                'destination',
                'detail',
                'price',
                'observations',
                'created_at',
                'updated_at',
            ]);
    }

    public function test_can_update_extraordinary_commission(): void
    {
        $commission = ExtraordinaryCommission::factory()->create();
        $data = [
            'origin' => 'Nuevo Origen',
            'destination' => 'Nuevo Destino',
            'detail' => 'Nuevo Detalle',
            'price' => 2000.00,
            'observations' => 'Nuevas observaciones',
        ];

        $response = $this->putJson("/api/extraordinary-commissions/{$commission->id}", $data);

        $response->assertStatus(204);

        $this->assertDatabaseHas('extraordinary_commissions', $data);
    }

    public function test_can_delete_extraordinary_commission(): void
    {
        $commission = ExtraordinaryCommission::factory()->create();

        $response = $this->deleteJson("/api/extraordinary-commissions/{$commission->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('extraordinary_commissions', ['id' => $commission->id]);
    }

    public function test_validates_required_fields(): void
    {
        $response = $this->postJson('/api/extraordinary-commissions', []);

        $response->assertStatus(422);
    }
}
