<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function curriculumCompetencies(): HasMany
    {
        return $this->hasMany(CurriculumTopicCompetency::class);
    }

    public function relations(): HasMany
    {
        return $this->hasMany(EducationPlanCompetenceRelation::class, 'source_competency_id');
    }
}
