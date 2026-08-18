<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['organization_id', 'name', 'description'])]
class MaterialItem extends Model
{
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function phaseTemplates(): BelongsToMany
    {
        return $this->belongsToMany(PhaseTemplate::class, 'phase_template_material_items')->withPivot('quantity');
    }
}
