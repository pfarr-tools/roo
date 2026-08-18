<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['group_year_plan_id', 'unit_template_id', 'curriculum_topic_id', 'title', 'starts_on', 'ends_on', 'planned_hours', 'position', 'is_interrupted', 'notes'])]
class PlannedUnit extends Model
{
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'is_interrupted' => 'boolean'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(GroupYearPlan::class, 'group_year_plan_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(UnitTemplate::class, 'unit_template_id');
    }

    public function curriculumTopic(): BelongsTo
    {
        return $this->belongsTo(CurriculumTopic::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(PlannedLesson::class)->orderBy('position');
    }
}
