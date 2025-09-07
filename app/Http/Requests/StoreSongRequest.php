<?php

namespace App\Http\Requests;

use App\Rules\YouTubeInput;
use App\Services\YouTubeScraperService;
use Illuminate\Foundation\Http\FormRequest;

class StoreSongRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $value = $this->input('youtube', $this->input('youtube_id', $this->input('youtube_url')));
        if ($value) {
            $yt = app(YouTubeScraperService::class);

            if ($id = $yt->extractId($value)) {
                $this->merge(['youtube_id' => $id]);
                $this->fillMissingMeta($yt, $id);
            }
        }

        $this->request->remove('youtube_url');
    }

    private function fillMissingMeta(YouTubeScraperService $yt, string $id): void
    {
        $needTitle = ! $this->filled('title');
        $needPlays = ! $this->filled('plays');

        if (! $needTitle && ! $needPlays) {
            return;
        }

        $meta = $yt->fetchById($id);

        if ($needTitle && ! empty($meta['title'])) {
            $this->merge(['title' => $meta['title']]);
        }

        if ($needPlays && isset($meta['views'])) {
            $this->merge(['plays' => (int) $meta['views']]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'youtube' => ['sometimes', new YouTubeInput],
            'youtube_id' => ['required', 'regex:/^[A-Za-z0-9_-]{11}$/'],
            'plays' => ['nullable', 'integer', 'min:0'],
            'suggestion_id' => ['required', 'integer', 'exists:suggestions,id'],
        ];
    }
}
