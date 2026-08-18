<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['organization_id', 'school_id', 'first_name', 'last_name', 'class_name', 'notes'])]
class Student extends Model
{
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function teachingGroups(): BelongsToMany
    {
        return $this->belongsToMany(TeachingGroup::class, 'teaching_group_memberships')->withPivot(['starts_on', 'ends_on'])->withTimestamps();
    }
}
