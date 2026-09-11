<?php

namespace App\Models;

use App\Search\SearchableFields;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

#[Fillable(['user_id', 'lesson_template_id', 'copied_from_id', 'title', 'duration_minutes', 'social_form_id', 'teacher_interaction', 'learner_activity', 'differentiation', 'didactic_comment', 'material', 'media', 'position', 'version', 'is_active'])]
class PhaseTemplate extends Model
{
    use Searchable, SearchableFields;

    protected function searchableFields(): array
    {
        return ['title', 'social_form', 'teacher_interaction', 'learner_activity', 'differentiation', 'didactic_comment', 'material', 'media'];
    }

    public function toSearchableArray(): array
    {
        return $this->searchablePayload();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lessonTemplate(): BelongsTo
    {
        return $this->belongsTo(LessonTemplate::class);
    }

    public function socialForm(): BelongsTo
    {
        return $this->belongsTo(SocialForm::class);
    }

    public function copiedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'copied_from_id');
    }

    public function copies(): HasMany
    {
        return $this->hasMany(self::class, 'copied_from_id');
    }

    public function materialItems(): BelongsToMany
    {
        return $this->belongsToMany(MaterialItem::class, 'phase_template_material_items')->withPivot('quantity');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(ResourceReference::class);
    }
}
