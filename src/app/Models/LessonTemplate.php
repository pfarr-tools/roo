<?php

namespace App\Models;

use App\Search\SearchableFields;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

#[Fillable(['user_id', 'unit_template_id', 'copied_from_id', 'title', 'duration_minutes', 'objective', 'notes', 'version', 'is_active'])]
class LessonTemplate extends Model
{
    use Searchable, SearchableFields;

    protected function searchableFields(): array
    {
        return ['title', 'objective', 'notes'];
    }

    public function toSearchableArray(): array
    {
        return $this->searchablePayload();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unitTemplate(): BelongsTo
    {
        return $this->belongsTo(UnitTemplate::class);
    }

    public function copiedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'copied_from_id');
    }

    public function copies(): HasMany
    {
        return $this->hasMany(self::class, 'copied_from_id');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(ResourceReference::class);
    }
}
