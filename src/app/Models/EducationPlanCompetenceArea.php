<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['education_plan_version_id', 'education_plan_stage_id', 'parent_id', 'kind', 'external_identifier', 'title', 'introduction', 'notes', 'source_raw', 'position'])]
class EducationPlanCompetenceArea extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['notes' => 'array'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(EducationPlanVersion::class, 'education_plan_version_id');
    }

    public function competencies(): HasMany
    {
        return $this->hasMany(EducationPlanCompetency::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(EducationPlanStage::class, 'education_plan_stage_id');
    }
}
