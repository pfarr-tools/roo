<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'teaching_group_id', 'report_period_id', 'title', 'assessed_on', 'status', 'notes'])]
class Assessment extends Model
{
    protected function casts(): array
    {
        return ['assessed_on' => 'date'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class, 'teaching_group_id');
    }

    public function scheduleSlots(): HasMany
    {
        return $this->hasMany(ScheduleSlot::class);
    }

    public function reportPeriod(): BelongsTo
    {
        return $this->belongsTo(ReportPeriod::class);
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(AssessmentTask::class, 'assessment_task_assessment')->withPivot('position')->orderBy('assessment_task_assessment.position')->withTimestamps();
    }
}
