<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use App\Search\SearchableFields;

#[Fillable(['organization_id', 'name', 'slug', 'short_name', 'city', 'notes', 'messenger_name', 'observation_scale_interval_count'])]
class School extends Model
{
    use Searchable, SearchableFields;

    protected function searchableFields(): array
    {
        return ['name', 'short_name', 'city'];
    }

    public function toSearchableArray(): array
    {
        return $this->searchablePayload();
    }

    protected function casts(): array
    {
        return ['observation_scale_interval_count' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (School $school): void {
            if ($school->isDirty('name') || ! $school->slug) {
                $base = Str::slug($school->name) ?: 'schule';
                $slug = $base;
                $suffix = 2;
                while (static::query()->where('organization_id', $school->organization_id)->where('slug', $slug)->when($school->exists, fn ($query) => $query->whereKeyNot($school->id))->exists()) {
                    $slug = $base.'-'.$suffix++;
                }
                $school->slug = $slug;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function schoolYears(): HasMany
    {
        return $this->hasMany(SchoolYear::class);
    }

    public function curriculumAssignments(): HasMany
    {
        return $this->hasMany(CurriculumSchoolAssignment::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function teachingGroups(): HasMany
    {
        return $this->hasMany(TeachingGroup::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(SchoolPeriod::class);
    }

    public function customProcessCompetences(): HasMany
    {
        return $this->hasMany(CustomProcessCompetence::class)->orderBy('position')->orderBy('id');
    }
}
