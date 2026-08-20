<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['song_chord_set_id', 'song_part_id', 'line_number', 'repetition', 'character_offset', 'chord'])]
class SongChord extends Model
{
    protected $casts = ['line_number' => 'integer', 'repetition' => 'integer', 'character_offset' => 'integer'];

    public function set(): BelongsTo
    {
        return $this->belongsTo(SongChordSet::class, 'song_chord_set_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(SongPart::class, 'song_part_id');
    }
}
