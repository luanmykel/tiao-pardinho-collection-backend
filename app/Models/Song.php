<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Song extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'youtube_id', 'plays'];

    protected $appends = ['youtube_url', 'thumbnail_url'];

    protected static function booted()
    {
        static::deleting(function (Song $song) {
            $reason = 'song_deleted';

            DB::table('suggestions')
                ->where('song_id', $song->id)
                ->update([
                    'status' => 'deleted',
                    'removed_at' => now(),
                    'removed_reason' => $reason,
                    'updated_at' => now(),
                ]);
        });
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(Suggestion::class);
    }

    public function getYoutubeUrlAttribute(): string
    {
        return "https://www.youtube.com/watch?v=$this->youtube_id";
    }

    public function getThumbnailUrlAttribute(): string
    {
        return "https://img.youtube.com/vi/$this->youtube_id/hqdefault.jpg";
    }
}
