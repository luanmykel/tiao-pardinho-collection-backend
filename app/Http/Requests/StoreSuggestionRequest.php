<?php

namespace App\Http\Requests;

use App\Rules\YouTubeInput;
use App\Services\YouTubeScraperService;
use Illuminate\Foundation\Http\FormRequest;

class StoreSuggestionRequest extends FormRequest
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
            $id = $yt->extractId($value);
            if ($id) {
                $this->merge(['youtube_id' => $id]);
            }
        }
        $this->request->remove('youtube_url');
    }

    public function rules(): array
    {
        return [
            'youtube' => ['sometimes', new YouTubeInput],
            'youtube_id' => ['required', 'regex:/^[A-Za-z0-9_-]{11}$/', 'string'],
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }
}
