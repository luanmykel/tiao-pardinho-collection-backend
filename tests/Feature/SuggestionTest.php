<?php

namespace Tests\Feature;

use App\Services\YouTubeScraperService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuggestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_stores_valid_youtube_suggestion_using_scraper(): void
    {
        $this->partialMock(YouTubeScraperService::class, function ($m) {
            $m->shouldReceive('fetchById')
                ->once()
                ->with('u2FOGNSJfX8')
                ->andReturn([
                    'id' => 'u2FOGNSJfX8',
                    'title' => 'Fernandinho | Santo Pra Sempre',
                    'channel' => 'Fernandinho',
                    'views' => 123456789,
                ]);
        });

        $this->postJson('/api/suggestions', [
            'youtube' => 'https://www.youtube.com/watch?v=u2FOGNSJfX8',
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('youtube_id', 'u2FOGNSJfX8')
            ->assertJsonPath('title', 'Fernandinho | Santo Pra Sempre')
            ->assertJsonPath('view_count', 123456789);
    }

    public function test_scraper_extract_id_variants(): void
    {
        $s = app(YouTubeScraperService::class);

        $this->assertSame('u2FOGNSJfX8', $s->extractId('u2FOGNSJfX8'));
        $this->assertSame('u2FOGNSJfX8', $s->extractId('https://www.youtube.com/watch?v=u2FOGNSJfX8'));
        $this->assertSame('u2FOGNSJfX8', $s->extractId('https://youtu.be/u2FOGNSJfX8'));
        $this->assertSame('u2FOGNSJfX8', $s->extractId('https://www.youtube.com/shorts/u2FOGNSJfX8'));
        $this->assertNull($s->extractId('https://example.com/not-youtube'));
        $this->assertNull($s->extractId('invalid-id-xxxxx'));
    }

    public function test_rejects_invalid_suggestion(): void
    {
        $this->postJson('/api/suggestions', ['youtube' => 'https://example.com/abc'])
            ->assertStatus(422);
    }
}
