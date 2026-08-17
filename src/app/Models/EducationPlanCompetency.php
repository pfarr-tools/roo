<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['education_plan_competence_area_id', 'external_identifier', 'number', 'text', 'position'])]
class EducationPlanCompetency extends Model
{
    public $timestamps = false;

    public function variants(): HasMany
    {
        return $this->hasMany(EducationPlanCompetenceVariant::class, 'education_plan_competency_id');
    }

    public function relations(): HasMany
    {
        return $this->hasMany(EducationPlanCompetenceRelation::class, 'source_competency_id');
    }
}
