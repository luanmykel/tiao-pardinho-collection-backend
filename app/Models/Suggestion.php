<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Suggestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'youtube_id',
        'title',
        'view_count',
        'status',
        'reviewer_id',
        'note',
        'reviewed_at',
        'song_id', 'removed_at', 'removed_reason',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'view_count' => 'integer',
        'removed_at' => 'datetime',
    ];

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    public function getIsRemovedAttribute(): bool
    {
        return $this->status === 'deleted' || ! is_null($this->removed_at);
    }
}
