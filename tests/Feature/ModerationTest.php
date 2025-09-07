<?php

namespace Tests\Feature;

use App\Models\Suggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_approves_suggestion_and_creates_song(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $token = auth('api')->login($admin);
        $this->withHeader('Authorization', "Bearer {$token}");

        $id = 'abc123def45';

        $s = Suggestion::factory()->create([
            'status' => 'pending',
            'youtube_id' => $id,
        ]);

        $this->postJson("/api/suggestions/{$s->id}/approve")
            ->assertOk()
            ->assertJsonPath('status', 'approved');

        $this->assertDatabaseHas('songs', ['youtube_id' => $id]);
    }
}
