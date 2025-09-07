<?php

namespace App\Rules;

use App\Services\YouTubeScraperService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class YouTubeInput implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $svc = app(YouTubeScraperService::class);
        $id = $svc->extractId((string) $value);

        if (! $id) {
            $fail('Forneça uma URL do YouTube válida ou um ID de 11 caracteres.');
        }
    }
}
