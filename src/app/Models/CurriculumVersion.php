<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['curriculum_id', 'external_identifier', 'schema_version', 'source_url', 'source_format', 'is_editable', 'is_complete', 'conversion_metadata', 'raw_payload'])]
class CurriculumVersion extends Model
{
    protected function casts(): array
    {
        return ['is_editable' => 'boolean', 'is_complete' => 'boolean', 'conversion_metadata' => 'array', 'raw_payload' => 'array'];
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(CurriculumTopic::class);
    }

    public function bindings(): HasMany
    {
        return $this->hasMany(CurriculumEducationPlanBinding::class);
    }
}
