<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['organization_id', 'name', 'material_number', 'storage_location', 'description', 'image_path', 'image_mime_type'])]
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

    public function phases(): BelongsToMany
    {
        return $this->belongsToMany(LessonPhase::class, 'lesson_phase_material_items');
    }

    public function teachingUnits(): BelongsToMany
    {
        return $this->morphedByMany(TeachingUnit::class, 'material_itemable');
    }

    public function lessons(): BelongsToMany
    {
        return $this->morphedByMany(Lesson::class, 'material_itemable');
    }
}
