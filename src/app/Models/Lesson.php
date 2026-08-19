<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['teaching_unit_id', 'lesson_template_id', 'title', 'duration', 'position', 'learning_goals', 'materials', 'homework', 'assessment_note', 'notes'])]
class Lesson extends Model
{
    public function unit(): BelongsTo
    {
        return $this->belongsTo(TeachingUnit::class, 'teaching_unit_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(LessonTemplate::class, 'lesson_template_id');
    }

    public function competencies(): BelongsToMany
    {
        return $this->belongsToMany(TeachingUnitCompetency::class, 'lesson_competencies');
    }

    public function phases(): HasMany
    {
        return $this->hasMany(LessonPhase::class)->orderBy('position');
    }

    public function scheduledLessons(): HasMany
    {
        return $this->hasMany(ScheduledLesson::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(ResourceReference::class);
    }

    public function resourceLinks(): HasMany
    {
        return $this->hasMany(ResourceLink::class);
    }

    public function materialItems(): BelongsToMany
    {
        return $this->morphToMany(MaterialItem::class, 'material_itemable');
    }
}
