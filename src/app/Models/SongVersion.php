<?php

namespace App\Models;

use App\Search\SearchableFields;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Scout\Searchable;

#[Fillable(['song_id', 'name', 'language', 'lyrics', 'notation', 'chords', 'text_export_allowed', 'metadata_export_allowed', 'layout_data', 'generated_sheet_path', 'generated_sheet_at', 'generated_sheet_a4_path', 'generated_sheet_a4_at', 'generated_chord_sheet_paths', 'generated_chord_sheet_at'])]
class SongVersion extends Model
{
    use Searchable, SearchableFields;

    protected function searchableFields(): array
    {
        return ['name', 'language', 'lyrics', 'notation', 'chords'];
    }

    public function toSearchableArray(): array
    {
        $payload = $this->searchablePayload();
        $parts = $this->relationLoaded('parts') ? $this->parts : $this->parts()->get();
        $partsText = $parts->flatMap(fn (SongPart $part): array => [$part->title, $part->content])->filter(fn (mixed $value): bool => is_string($value) && filled($value))->implode(' ');

        $payload['user_id'] = $this->song()->value('user_id');
        $payload['parts_text'] = $partsText;
        $payload['search_text'] = trim(implode(' ', array_filter([$payload['search_text'], $partsText])));

        return $payload;
    }

    protected $casts = ['layout_data' => 'array', 'generated_chord_sheet_paths' => 'array', 'generated_sheet_at' => 'datetime', 'generated_sheet_a4_at' => 'datetime', 'generated_chord_sheet_at' => 'datetime'];

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
