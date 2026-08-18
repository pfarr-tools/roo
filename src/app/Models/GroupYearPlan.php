<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'teaching_group_id', 'school_year_id', 'revision'])]
class GroupYearPlan extends Model
{
    public function teachingGroup(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(PlannedUnit::class)->orderBy('position');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PlanRevision::class)->latest();
    }
}
