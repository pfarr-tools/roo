<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['song_version_id', 'instrument', 'name', 'key_signature'])]
class SongChordSet extends Model
{
    public function version(): BelongsTo
    {
        return $this->belongsTo(SongVersion::class, 'song_version_id');
    }

    public function chords(): HasMany
    {
        return $this->hasMany(SongChord::class)->orderBy('line_number')->orderBy('repetition')->orderBy('character_offset');
    }
}
