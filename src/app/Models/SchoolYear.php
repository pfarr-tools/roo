<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['organization_id', 'school_id', 'name', 'slug', 'starts_on', 'ends_on', 'timezone'])]
class SchoolYear extends Model
{
    protected static function booted(): void
    {
        static::saving(function (SchoolYear $schoolYear): void {
            if ($schoolYear->starts_on) {
                $startYear = $schoolYear->starts_on->year;
                $schoolYear->name = $startYear.'/'.str_pad((string) (($startYear + 1) % 100), 2, '0', STR_PAD_LEFT);
            }

            if ($schoolYear->isDirty('name') || ! $schoolYear->slug) {
                $schoolYear->slug = Str::slug(str_replace('/', '-', $schoolYear->name));
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(SchoolYearDay::class);
    }

    public function holidayPeriods(): HasMany
    {
        return $this->hasMany(HolidayPeriod::class);
    }

    public function calendarExceptions(): HasMany
    {
        return $this->hasMany(CalendarException::class);
    }

    public function teachingGroups(): HasMany
    {
        return $this->hasMany(TeachingGroup::class);
    }
}
