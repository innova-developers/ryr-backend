<?php

namespace Tests\Feature\Users;

use App\Shared\Models\Branch;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['role' => 'administrador']);
        $this->actingAs($admin, 'sanctum');
    }

    public function test_admin_can_update_a_user()
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create([
            'name' => 'Original',
            'email' => 'original@example.com',
            'role' => 'usuario',
            'branch_id' => $branch->id,
        ]);

        $newBranch = Branch::factory()->create();
        $payload = [
            'name' => 'Editado',
            'email' => 'editado@example.com',
            'password' => 'password123',
            'role' => 'usuario',
            'branch_id' => $newBranch->id,
        ];

        $response = $this->putJson("/api/users/{$user->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'Editado',
                'email' => 'editado@example.com',
                'role' => 'usuario',
                'branch_id' => $newBranch->id,
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Editado',
            'email' => 'editado@example.com',
            'branch_id' => $newBranch->id,
        ]);
    }

    public function test_update_returns_404_if_user_not_found()
    {
        $branch = Branch::factory()->create();

        $response = $this->putJson('/api/users/999', [
            'name' => 'No existe',
            'email' => 'noexiste@example.com',
            'password' => 'password123',
            'role' => 'usuario',
            'branch_id' => $branch->id,
        ]);

        $response->assertStatus(404)
            ->assertJsonFragment(['message' => 'Usuario no encontrado']);
    }
}
