<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['organization_id', 'education_plan_id', 'education_plan_competency_id', 'teaching_unit_competency_id', 'title', 'task_type', 'content', 'solution', 'max_points', 'level', 'position'])]
class AssessmentTask extends Model
{
    protected function casts(): array
    {
        return ['content' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $task): void {
            if (! $task->organization_id) {
                throw ValidationException::withMessages(['organization_id' => 'Eine Prüfungsaufgabe benötigt eine Organisation.']);
            }
            if (! $task->teaching_unit_competency_id && ! $task->education_plan_competency_id) {
                throw ValidationException::withMessages(['education_plan_competency_id' => 'Eine Prüfungsaufgabe benötigt eine Kompetenz.']);
            }
            if ($task->education_plan_competency_id && ! $task->education_plan_id) {
                throw ValidationException::withMessages(['education_plan_id' => 'Eine Prüfungsaufgabe benötigt einen Bildungsplan.']);
            }
        });
    }

    public function assessments(): BelongsToMany
    {
        return $this->belongsToMany(Assessment::class, 'assessment_task_assessment')->withPivot('position')->withTimestamps();
    }

    public function competency(): BelongsTo
    {
        return $this->belongsTo(TeachingUnitCompetency::class, 'teaching_unit_competency_id');
    }

    public function educationPlan(): BelongsTo
    {
        return $this->belongsTo(EducationPlan::class);
    }

    public function educationPlanCompetency(): BelongsTo
    {
        return $this->belongsTo(EducationPlanCompetency::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(StudentAssessmentResult::class);
    }

    public function levels(): HasMany
    {
        return $this->hasMany(AssessmentTaskLevel::class);
    }

    public function expectations(): HasMany
    {
        return $this->hasMany(AssessmentTaskExpectation::class)->orderBy('position');
    }

    public function lessons(): BelongsToMany
    {
        return $this->belongsToMany(Lesson::class, 'lesson_assessment_tasks')->withPivot('position')->withTimestamps();
    }
}
