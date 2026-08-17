<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['curriculum_version_id', 'source_curriculum_version_id', 'external_identifier', 'number', 'title', 'position', 'year', 'hours', 'notes', 'preparation_questions', 'shared_plan', 'raw_rows'])]
class CurriculumTopic extends Model
{
    protected function casts(): array
    {
        return ['preparation_questions' => 'array', 'shared_plan' => 'array', 'raw_rows' => 'array'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(CurriculumVersion::class, 'curriculum_version_id');
    }

    public function sourceVersion(): BelongsTo
    {
        return $this->belongsTo(CurriculumVersion::class, 'source_curriculum_version_id');
    }

    public function competencies(): HasMany
    {
        return $this->hasMany(CurriculumTopicCompetency::class);
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(CurriculumTopicProfile::class);
    }
}
