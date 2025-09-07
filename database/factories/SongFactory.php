<?php

namespace Database\Factories;

use App\Models\Song;
use Illuminate\Database\Eloquent\Factories\Factory;

class SongFactory extends Factory
{
    protected $model = Song::class;

    public function definition(): array
    {
        $id = substr(str_replace(['=', '/', '+'], '_', base64_encode($this->faker->uuid())), 0, 11);

        return [
            'youtube_id' => $id,
        ];
    }
}
