<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['education_plan_version_id', 'external_identifier', 'label', 'course_identifier', 'course_label', 'position', 'raw_data'])]
class EducationPlanStage extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['raw_data' => 'array'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(EducationPlanVersion::class, 'education_plan_version_id');
    }

    public function gradeLevels(): BelongsToMany
    {
        return $this->belongsToMany(EducationPlanGradeLevel::class, 'education_plan_stage_grade_level');
    }

    public function levels(): BelongsToMany
    {
        return $this->belongsToMany(EducationPlanLevel::class, 'education_plan_stage_level');
    }

    public function competenceAreas(): HasMany
    {
        return $this->hasMany(EducationPlanCompetenceArea::class);
    }
}
