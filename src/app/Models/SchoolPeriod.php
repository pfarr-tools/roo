<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['school_id', 'period_number', 'starts_at', 'ends_at'])]
class SchoolPeriod extends Model
{
    protected function casts(): array
    {
        return ['starts_at' => 'datetime:H:i', 'ends_at' => 'datetime:H:i'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function teachingGroups(): BelongsToMany
    {
        return $this->belongsToMany(TeachingGroup::class, 'teaching_group_periods');
    }
}
