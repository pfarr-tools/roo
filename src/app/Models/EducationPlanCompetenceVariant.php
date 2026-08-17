<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['education_plan_competency_id', 'education_plan_level_id', 'text', 'position'])]
class EducationPlanCompetenceVariant extends Model
{
    public $timestamps = false;

    public function level(): BelongsTo
    {
        return $this->belongsTo(EducationPlanLevel::class, 'education_plan_level_id');
    }
}
