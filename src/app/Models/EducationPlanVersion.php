<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['education_plan_id', 'external_identifier', 'schema_version', 'title', 'version_date', 'source_url', 'is_complete', 'conversion_metadata', 'raw_payload', 'supplementary_content_raw'])]
class EducationPlanVersion extends Model
{
    public function plan(): BelongsTo
    {
        return $this->belongsTo(EducationPlan::class, 'education_plan_id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(EducationPlanStage::class);
    }

    public function competenceAreas(): HasMany
    {
        return $this->hasMany(EducationPlanCompetenceArea::class);
    }

    protected function casts(): array
    {
        return ['version_date' => 'date', 'is_complete' => 'boolean', 'conversion_metadata' => AsArrayObject::class, 'raw_payload' => AsArrayObject::class];
    }
}
