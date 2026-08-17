<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['curriculum_version_id', 'education_plan_id', 'plan_code', 'role', 'denomination', 'subject', 'raw_data'])]
class CurriculumEducationPlanBinding extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['raw_data' => 'array'];
    }

    public function educationPlan(): BelongsTo
    {
        return $this->belongsTo(EducationPlan::class);
    }
}
