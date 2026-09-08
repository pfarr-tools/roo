<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'teaching_group_id', 'label', 'starts_on', 'ends_on', 'whole_grades', 'include_full_school_year'])]
class ReportPeriod extends Model
{
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'whole_grades' => 'boolean', 'include_full_school_year' => 'boolean'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class, 'teaching_group_id');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(StudentEvaluation::class);
    }

    public function evaluationTemplates(): HasMany
    {
        return $this->hasMany(ReportPeriodEvaluationTemplate::class)->orderByRaw("CASE level WHEN 'G' THEN 1 WHEN 'M' THEN 2 WHEN 'E' THEN 3 ELSE 4 END");
    }
}
