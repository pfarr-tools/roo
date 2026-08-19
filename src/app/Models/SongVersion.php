<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['song_id', 'name', 'language', 'lyrics', 'notation', 'chords', 'rights_status', 'rights_note', 'text_export_allowed', 'metadata_export_allowed'])]
class SongVersion extends Model
{
    public function song(): BelongsTo { return $this->belongsTo(Song::class); }
    public function sheet(): HasOne { return $this->hasOne(SongSheet::class); }
    public function unitSongs(): BelongsToMany { return $this->belongsToMany(TeachingUnit::class, 'unit_songs'); }
    public function lessonSongs(): BelongsToMany { return $this->belongsToMany(Lesson::class, 'lesson_songs'); }
    public function phaseSongs(): BelongsToMany { return $this->belongsToMany(LessonPhase::class, 'phase_songs'); }
}
