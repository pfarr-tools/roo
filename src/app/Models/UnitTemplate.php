<?php

namespace App\Models;

use App\Search\SearchableFields;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

#[Fillable(['user_id', 'copied_from_id', 'title', 'description', 'expected_hours', 'notes', 'version', 'is_active'])]
class UnitTemplate extends Model
{
    use Searchable, SearchableFields;

    protected function searchableFields(): array
    {
        return ['title', 'description', 'notes'];
    }

    public function toSearchableArray(): array
    {
        return $this->searchablePayload();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function copiedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'copied_from_id');
    }

    public function copies(): HasMany
    {
        return $this->hasMany(self::class, 'copied_from_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'unit_template_tags');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(ResourceReference::class);
    }
}
