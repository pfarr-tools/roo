<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['organization_id', 'derived_from_id', 'external_identifier', 'title', 'country', 'state', 'school_type', 'grades', 'variant', 'cooperation_model', 'denominations'])]
class Curriculum extends Model
{
    protected function casts(): array
    {
        return ['grades' => 'array', 'denominations' => 'array'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CurriculumVersion::class);
    }

    public function derivedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'derived_from_id');
    }

    public function topics(): HasManyThrough
    {
        return $this->hasManyThrough(CurriculumTopic::class, CurriculumVersion::class);
    }

    public function schoolAssignments(): HasMany
    {
        return $this->hasMany(CurriculumSchoolAssignment::class);
    }
}
