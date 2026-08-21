<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['song_version_id', 'title', 'content', 'position', 'is_refrain', 'is_repeated', 'repeat_count', 'is_numbered', 'number'])]
class SongPart extends Model
{
    protected $casts = ['is_refrain' => 'boolean', 'is_repeated' => 'boolean', 'repeat_count' => 'integer', 'is_numbered' => 'boolean', 'number' => 'integer'];

    public function version(): BelongsTo
    {
        return $this->belongsTo(SongVersion::class, 'song_version_id');
    }
}
