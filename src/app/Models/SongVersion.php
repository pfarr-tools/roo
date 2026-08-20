<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['song_id', 'name', 'language', 'lyrics', 'notation', 'chords', 'text_export_allowed', 'metadata_export_allowed', 'layout_data', 'generated_sheet_path', 'generated_sheet_at', 'generated_sheet_a4_path', 'generated_sheet_a4_at'])]
class SongVersion extends Model
{
    protected $casts = ['layout_data' => 'array', 'generated_sheet_at' => 'datetime', 'generated_sheet_a4_at' => 'datetime'];

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    public function sheet(): HasOne
    {
        return $this->hasOne(SongSheet::class);
    }

    public function unitSongs(): BelongsToMany
    {
        return $this->belongsToMany(TeachingUnit::class, 'unit_songs');
    }

    public function lessonSongs(): BelongsToMany
    {
        return $this->belongsToMany(Lesson::class, 'lesson_songs');
    }

    public function phaseSongs(): BelongsToMany
    {
        return $this->belongsToMany(LessonPhase::class, 'phase_songs');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(SongPart::class)->orderBy('position');
    }

    public function images(): HasMany
    {
        return $this->hasMany(SongImage::class);
    }

    public function chordSets(): HasMany
    {
        return $this->hasMany(SongChordSet::class)->with('chords');
    }
}
