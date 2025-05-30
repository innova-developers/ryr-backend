<?php

namespace Tests\Feature\Users;

use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_soft_delete_a_user()
    {
        $admin = User::factory()->create(['role' => 'administrador']);
        $this->actingAs($admin, 'sanctum');
        $user = User::factory()->create();

        $response = $this->deleteJson("/api/users/{$user->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }
}
