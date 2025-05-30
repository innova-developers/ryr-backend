<?php

namespace Tests\Feature\Users;

use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_users_returns_all_users():void
    {
        User::factory()->count(3)->create();
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $response = $this->getJson('/api/users');
        $response->assertStatus(200)
            ->assertJsonCount(4);
    }
}
