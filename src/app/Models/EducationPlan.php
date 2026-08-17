<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'external_identifier', 'country', 'state', 'subject', 'title'])]
class EducationPlan extends Model
{
    public function versions(): HasMany
    {
        return $this->hasMany(EducationPlanVersion::class);
    }
}
