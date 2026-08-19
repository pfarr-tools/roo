<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['organization_id', 'school_id', 'school_year_id', 'name', 'aktenzeichen', 'notes'])]
class TeachingGroup extends Model
{
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'teaching_group_memberships')->withPivot(['starts_on', 'ends_on'])->withTimestamps();
    }

    public function gradeLevels(): HasMany
    {
        return $this->hasMany(TeachingGroupGradeLevel::class);
    }

    public function timetableSlots(): HasMany
    {
        return $this->hasMany(TimetableSlot::class);
    }

    public function curricula(): BelongsToMany
    {
        return $this->belongsToMany(Curriculum::class, 'teaching_group_curricula')->withPivot('role')->withTimestamps();
    }

    public function schoolPeriods(): BelongsToMany
    {
        return $this->belongsToMany(SchoolPeriod::class, 'teaching_group_periods')->withPivot('weekday');
    }

    public function yearPlan(): HasOne
    {
        return $this->hasOne(GroupYearPlan::class);
    }

    public function teachingUnits(): HasMany
    {
        return $this->hasMany(TeachingUnit::class);
    }

    public function scheduleSlots(): HasMany
    {
        return $this->hasMany(ScheduleSlot::class);
    }

    public function rituals(): HasMany
    {
        return $this->hasMany(TeachingGroupRitual::class)->orderBy('position');
    }

    public function songbook(): HasOne
    {
        return $this->hasOne(GroupSongbook::class);
    }
}
