<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;
use App\Search\SearchableFields;

#[Fillable(['organization_id', 'external_identifier', 'country', 'state', 'subject', 'title'])]
class EducationPlan extends Model
{
    use Searchable, SearchableFields;

    protected function searchableFields(): array
    {
        return ['title', 'external_identifier', 'subject', 'school_type'];
    }

    public function toSearchableArray(): array
    {
        return $this->searchablePayload();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(EducationPlanVersion::class);
    }
}
