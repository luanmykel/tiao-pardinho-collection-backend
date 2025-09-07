<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAdminTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
            'password' => Hash::make('Admin123!'),
        ]);
    }

    private function asJwt(User $user): void
    {
        $token = auth('api')->login($user);
        $this->withHeader('Authorization', "Bearer {$token}");
    }

    public function test_admin_creates_user(): void
    {
        $admin = $this->makeAdmin();
        $this->asJwt($admin);

        $payload = [
            'name' => 'Novo Usuário',
            'email' => 'novo@example.com',
            'password' => 'SenhaForte123!',
            'password_confirmation' => 'SenhaForte123!',
            'is_active' => true,
        ];

        $this->postJson('/api/admin/users', $payload)
            ->assertCreated()
            ->assertJsonFragment([
                'name' => 'Novo Usuário',
                'email' => 'novo@example.com',
                'is_active' => true,
                'is_admin' => true,
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'novo@example.com',
            'is_active' => 1,
            'is_admin' => 1,
        ]);
    }

    public function test_create_user_validation_errors(): void
    {
        $admin = $this->makeAdmin();
        $this->asJwt($admin);

        $this->postJson('/api/admin/users', [
            'name' => 'X',
            'email' => 'x@example.com',
            'password' => 'Senha123!',
            // sem confirmation
        ])->assertStatus(422)
            ->assertJsonStructure(['errors' => ['password']]);

        User::factory()->create(['email' => 'duplicado@example.com']);
        $this->postJson('/api/admin/users', [
            'name' => 'Y',
            'email' => 'duplicado@example.com',
            'password' => 'Senha123!',
            'password_confirmation' => 'Senha123!',
        ])->assertStatus(422)
            ->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_admin_updates_user(): void
    {
        $admin = $this->makeAdmin();
        $this->asJwt($admin);

        $user = User::factory()->create([
            'name' => 'Antigo',
            'email' => 'old@example.com',
            'is_active' => true,
            'is_admin' => true,
        ]);

        $this->putJson("/api/admin/users/{$user->id}", [
            'name' => 'Novo Nome',
            'email' => 'new@example.com',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonFragment([
                'name' => 'Novo Nome',
                'email' => 'new@example.com',
                'is_active' => false,
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Novo Nome',
            'email' => 'new@example.com',
            'is_active' => 0,
        ]);
    }

    public function test_admin_updates_user_password(): void
    {
        $admin = $this->makeAdmin();
        $this->asJwt($admin);

        $user = User::factory()->create([
            'email' => 'alvo@example.com',
            'password' => Hash::make('Velha123!'),
            'is_active' => true,
        ]);

        $this->putJson("/api/admin/users/{$user->id}/password", [
            'password' => 'NovaSenha123!',
            'password_confirmation' => 'NovaSenha123!',
        ])->assertOk();

        $this->postJson('/api/login', [
            'email' => 'alvo@example.com',
            'password' => 'NovaSenha123!',
        ])->assertOk()
            ->assertJsonStructure(['token']);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = $this->makeAdmin();
        $this->asJwt($admin);

        $this->deleteJson("/api/admin/users/{$admin->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_deletes_other_user(): void
    {
        $admin = $this->makeAdmin();
        $this->asJwt($admin);

        $other = User::factory()->create();
        $this->deleteJson("/api/admin/users/{$other->id}")
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $other->id]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => Hash::make('Qualquer123!'),
            'is_active' => false,
        ]);

        $this->postJson('/api/login', [
            'email' => 'inactive@example.com',
            'password' => 'Qualquer123!',
        ])->assertStatus(403)
            ->assertJson(['message' => 'Usuário inativo']);
    }
}
