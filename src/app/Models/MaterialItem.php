<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Scout\Searchable;
use App\Search\SearchableFields;

#[Fillable(['user_id', 'name', 'material_number', 'storage_location', 'description', 'image_path', 'image_mime_type'])]
class MaterialItem extends Model
{
    use Searchable, SearchableFields;

    protected function searchableFields(): array
    {
        return ['name', 'material_number', 'storage_location', 'description'];
    }

    public function toSearchableArray(): array
    {
        return $this->searchablePayload();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
