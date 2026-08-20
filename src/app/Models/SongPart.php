<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['song_version_id', 'title', 'content', 'position', 'is_refrain'])]
class SongPart extends Model
{
    protected $casts = ['is_refrain' => 'boolean'];
    public function version(): BelongsTo { return $this->belongsTo(SongVersion::class, 'song_version_id'); }
}
