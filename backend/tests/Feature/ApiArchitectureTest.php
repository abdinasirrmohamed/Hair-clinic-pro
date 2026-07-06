<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login_and_load_spa_bootstrap(): void
    {
        $user = User::factory()->create([
            'username' => 'admin',
            'full_name' => 'System Admin',
            'role' => 'Administrator',
        ]);

        $login = $this->postJson('/api/auth/login', [
            'username' => 'admin',
            'password' => 'Password1!',
        ])->assertOk()->assertJsonStructure(['token', 'user']);

        $this->withToken($login->json('token'))
            ->getJson('/api/bootstrap')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', 'Administrator')
            ->assertJsonStructure(['permissions', 'lookups']);
    }

    public function test_role_permissions_are_enforced_by_the_api(): void
    {
        $user = User::factory()->create(['role' => 'Receptionist']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/users')
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/patients')
            ->assertOk();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create(['username' => 'disabled', 'status' => 'Inactive']);

        $this->postJson('/api/auth/login', [
            'username' => 'disabled',
            'password' => 'Password1!',
        ])->assertForbidden();
    }
}
