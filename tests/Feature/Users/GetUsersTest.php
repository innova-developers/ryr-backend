<?php

namespace Tests\Feature\Users;

use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_users_returns_all_users(): void
    {
        User::factory()->count(3)->create(['role' => 'administrador']);
        $user = User::factory()->create(['role' => 'administrador', 'branch_id' => null]);
        $this->actingAs($user, 'sanctum');
        $response = $this->getJson('/api/users');
        $response->assertStatus(200);

        $data = $response->json();
        if (isset($data['data'])) {
            // Con paginación
            $this->assertGreaterThanOrEqual(4, count($data['data']));
        } else {
            // Sin paginación
            $this->assertGreaterThanOrEqual(4, count($data));
        }
    }
}
