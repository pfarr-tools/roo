<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['teaching_group_id', 'title_page_path', 'title_page_a4_path', 'title_page_original_name', 'title_page_mime_type', 'title_page_size'])]
class GroupSongbook extends Model
{
    public function group(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class, 'teaching_group_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(GroupSongbookEntry::class)->orderBy('song_number');
    }

    public function exports(): HasMany
    {
        return $this->hasMany(SongbookExport::class);
    }

    public function checkpoints(): HasMany
    {
        return $this->hasMany(PrintCheckpoint::class);
    }

    public function lessons(): BelongsToMany
    {
        return $this->belongsToMany(Lesson::class, 'lesson_songbooks')->withTimestamps();
    }

    public function phases(): BelongsToMany
    {
        return $this->belongsToMany(LessonPhase::class, 'phase_songbooks')->withTimestamps();
    }

    public function teachingUnits(): BelongsToMany
    {
        return $this->belongsToMany(TeachingUnit::class, 'teaching_unit_songbooks')->withTimestamps();
    }
}
