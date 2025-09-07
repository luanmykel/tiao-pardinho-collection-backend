<?php

namespace Database\Factories;

use App\Models\Suggestion;
use Illuminate\Database\Eloquent\Factories\Factory;

class SuggestionFactory extends Factory
{
    protected $model = Suggestion::class;

    public function definition(): array
    {
        return [
            'youtube_id' => $this->faker->regexify('[A-Za-z0-9_-]{11}'),
            'status' => 'pending',
        ];
    }
}
