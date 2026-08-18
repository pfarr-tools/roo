<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['planned_unit_id', 'lesson_template_id', 'title', 'position'])]
class PlannedLesson extends Model
{
    public function unit(): BelongsTo
    {
        return $this->belongsTo(PlannedUnit::class, 'planned_unit_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(LessonTemplate::class, 'lesson_template_id');
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(LessonOccurrence::class);
    }
}
