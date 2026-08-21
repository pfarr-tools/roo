<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['song_version_id', 'original_name', 'copyrights', 'storage_path', 'mime_type', 'size'])]
class SongImage extends Model
{
    public function version(): BelongsTo
    {
        return $this->belongsTo(SongVersion::class, 'song_version_id');
    }
}
