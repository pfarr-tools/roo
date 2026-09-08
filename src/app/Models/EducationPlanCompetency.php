<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['education_plan_competence_area_id', 'external_identifier', 'number', 'text', 'is_active', 'position'])]
class EducationPlanCompetency extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(EducationPlanCompetenceArea::class, 'education_plan_competence_area_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(EducationPlanCompetenceVariant::class, 'education_plan_competency_id');
    }

    public function curriculumTopicReferences(): HasMany
    {
        return $this->hasMany(CurriculumTopicEducationPlanReference::class, 'education_plan_competency_id');
    }

    public function lessons(): BelongsToMany
    {
        return $this->belongsToMany(Lesson::class, 'lesson_competencies', 'education_plan_competency_id', 'lesson_id');
    }

    public function teachingUnits(): BelongsToMany
    {
        return $this->belongsToMany(TeachingUnit::class, 'teaching_unit_education_plan_competencies', 'education_plan_competency_id', 'teaching_unit_id');
    }

    public function relations(): HasMany
    {
        return $this->hasMany(EducationPlanCompetenceRelation::class, 'source_competency_id');
    }
}
