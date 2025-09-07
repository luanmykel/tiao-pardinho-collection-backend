<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $resp = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);

        $resp->assertOk()
            ->assertJsonStructure([
                'token',
                'access_token',
                'token_type',
                'expires_in',
                'user' => ['id', 'name', 'email', 'avatar_url', 'is_admin', 'is_active'],
            ]);
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'adm3@example.com',
            'password' => Hash::make('correct-password'),
            'is_active' => true,
        ]);

        $this->postJson('/api/login', [
            'email' => 'adm3@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    public function test_non_admin_cannot_access_admin_route(): void
    {
        $user = User::factory()->create(['is_admin' => false, 'is_active' => true]);

        $token = auth('api')->login($user);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/suggestions')
            ->assertStatus(403);
    }

    public function test_admin_can_access_admin_route(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $token = auth('api')->login($admin);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/suggestions')
            ->assertOk();
    }
}
