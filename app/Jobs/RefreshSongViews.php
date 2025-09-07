<?php

namespace App\Jobs;

use App\Models\Song;
use App\Services\YouTubeScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Random\RandomException;
use Throwable;

class RefreshSongViews implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?int $limit;

    public bool $withJitter;

    public function __construct(?int $limit = null, bool $withJitter = true)
    {
        $this->limit = $limit;
        $this->withJitter = $withJitter;
        $this->onQueue('default');
    }

    public function handle(YouTubeScraperService $yt): void
    {
        $processed = 0;
        $batchSize = 50;

        Song::query()
            ->whereNotNull('youtube_id')
            ->orderBy('id')
            ->when($this->limit, fn ($q) => $q->limit($this->limit))
            ->chunkById($batchSize, function (Collection $chunk) use ($yt, &$processed) {
                foreach ($chunk as $song) {
                    try {
                        $meta = $yt->fetchById($song->youtube_id);
                        if ($meta && isset($meta['views']) && is_int($meta['views'])) {
                            $song->update(['plays' => $meta['views']]);
                        }
                    } catch (Throwable $e) {
                        Log::warning('Falha ao atualizar views', [
                            'song_id' => $song->id,
                            'youtube_id' => $song->youtube_id,
                            'error' => $e->getMessage(),
                        ]);
                        $this->sleepRandomMs(800, 2000);
                    }

                    $processed++;

                    if ($this->withJitter) {
                        $this->sleepRandomMs(500, 1800);
                        if ($processed % 20 === 0) {
                            $this->sleepRandomMs(15000, 35000);
                        }
                    }
                }
            });
    }

    private function sleepRandomMs(int $minMs, int $maxMs): void
    {
        if ($maxMs < $minMs) {
            [$minMs, $maxMs] = [$maxMs, $minMs];
        }
        if ($minMs < 0) {
            $minMs = 0;
        }

        try {
            $ms = random_int($minMs, $maxMs);
        } catch (RandomException $e) {
            $ms = (int) (($minMs + $maxMs) / 2);
            Log::notice('random_int falhou; usando fallback', [
                'min' => $minMs, 'max' => $maxMs, 'fallback_ms' => $ms, 'error' => $e->getMessage(),
            ]);
        }

        usleep($ms * 1000);
    }
}
