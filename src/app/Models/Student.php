<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Scout\Searchable;

#[Fillable(['organization_id', 'school_id', 'first_name', 'last_name', 'class_name', 'notes'])]
class Student extends Model
{
    use Searchable;

    public function toSearchableArray(): array
    {
        $this->loadMissing('teachingGroups:id,name');
        $groupNames = $this->teachingGroups->pluck('name')->filter()->values()->all();

        return [
            'id' => (string) $this->getKey(),
            'organization_id' => (int) $this->organization_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'class_name' => $this->class_name,
            'teaching_groups' => $groupNames,
            'search_text' => collect([$this->first_name, $this->last_name, $this->class_name, ...$groupNames])
                ->filter()
                ->implode(' '),
        ];
    }

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
