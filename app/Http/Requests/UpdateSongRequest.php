<?php

namespace App\Http\Requests;

use App\Rules\YouTubeInput;
use App\Services\YouTubeScraperService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSongRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    protected function prepareForValidation(): void
    {
        $value = $this->input('youtube', $this->input('youtube_id', $this->input('youtube_url')));
        if ($value) {
            $yt = app(YouTubeScraperService::class);
            $id = $yt->extractId($value);
            if ($id) {
                $this->merge(['youtube_id' => $id]);

                $needTitle = $this->has('title') && $this->input('title') === null;
                $needPlays = $this->has('plays') && $this->input('plays') === null;

                if ($needTitle || $needPlays) {
                    $meta = $yt->fetchById($id);
                    if ($needTitle && ! empty($meta['title'])) {
                        $this->merge(['title' => $meta['title']]);
                    }
                    if ($needPlays && isset($meta['views'])) {
                        $this->merge(['plays' => (int) $meta['views']]);
                    }
                }
            }
        }
        $this->request->remove('youtube_url');
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'youtube' => ['sometimes', new YouTubeInput],
            'youtube_id' => ['sometimes', 'regex:/^[A-Za-z0-9_-]{11}$/'],
            'plays' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
