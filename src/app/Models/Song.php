<?php

namespace App\Models;

use App\Search\SearchableFields;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

#[Fillable(['user_id', 'title', 'composer', 'author', 'copyright_notice', 'age_group', 'topics', 'notes'])]
class Song extends Model
{
    use Searchable, SearchableFields;

    protected function searchableFields(): array
    {
        return ['title', 'composer', 'author', 'copyright_notice', 'age_group', 'topics', 'notes'];
    }

    public function toSearchableArray(): array
    {
        return $this->searchablePayload();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SongVersion::class);
    }
}
