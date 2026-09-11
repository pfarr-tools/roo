<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'school_id', 'first_name', 'last_name', 'class_name', 'notes', 'receives_grades', 'pronoun_set', 'denomination'])]
class Student extends Model
{
    protected function casts(): array
    {
        return ['receives_grades' => 'boolean'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function teachingGroups(): BelongsToMany
    {
        return $this->belongsToMany(TeachingGroup::class, 'teaching_group_memberships')->withPivot(['starts_on', 'ends_on'])->withTimestamps();
    }

    public function assessmentBooklets(): HasMany
    {
        return $this->hasMany(AssessmentBooklet::class);
    }
}
