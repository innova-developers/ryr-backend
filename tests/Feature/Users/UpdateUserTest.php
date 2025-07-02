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
            'role' => 'administrador',
            'branch_id' => $branch->id,
        ]);

        $newBranch = Branch::factory()->create();
        $payload = [
            'name' => 'Editado',
            'email' => 'editado@example.com',
            'password' => 'password123',
            'role' => 'cadete',
            'branch_id' => $newBranch->id,
            'base_salary' => 6000.00,
            'commission_percentage' => 20.00,
        ];

        $response = $this->putJson("/api/users/{$user->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'Editado',
                'email' => 'editado@example.com',
                'role' => 'cadete',
                'branch_id' => $newBranch->id,
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Editado',
            'email' => 'editado@example.com',
            'branch_id' => $newBranch->id,
            'base_salary' => 6000.00,
            'commission_percentage' => 20.00,
        ]);
    }

    public function test_update_returns_404_if_user_not_found()
    {
        $branch = Branch::factory()->create();

        $response = $this->putJson('/api/users/999', [
            'name' => 'No existe',
            'email' => 'noexiste@example.com',
            'password' => 'password123',
            'role' => 'cadete',
            'branch_id' => $branch->id,
            'base_salary' => 4000.00,
            'commission_percentage' => 12.50,
        ]);

        $response->assertStatus(404)
            ->assertJsonFragment(['message' => 'Usuario no encontrado']);
    }

    public function test_admin_can_update_only_salary_fields()
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create([
            'name' => 'Usuario Original',
            'email' => 'original@example.com',
            'role' => 'administrador',
            'branch_id' => $branch->id,
            'base_salary' => 3000.00,
            'commission_percentage' => 10.00,
        ]);

        $payload = [
            'name' => 'Usuario Original', // Mantener el mismo nombre
            'email' => 'original@example.com', // Mantener el mismo email
            'role' => 'administrador', // Mantener el mismo rol
            'branch_id' => $branch->id, // Mantener la misma sucursal
            'base_salary' => 4500.00, // Actualizar solo el salario
            'commission_percentage' => 18.50, // Actualizar solo el porcentaje
        ];

        $response = $this->putJson("/api/users/{$user->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Usuario Original',
            'email' => 'original@example.com',
            'base_salary' => 4500.00,
            'commission_percentage' => 18.50,
        ]);
    }
}
