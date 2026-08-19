<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'teaching_group_id', 'source_curriculum_topic_id', 'unit_template_id', 'title', 'position', 'notes'])]
class TeachingUnit extends Model
{
    public function group(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class, 'teaching_group_id');
    }

    public function sourceCurriculumTopic(): BelongsTo
    {
        return $this->belongsTo(CurriculumTopic::class, 'source_curriculum_topic_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(UnitTemplate::class, 'unit_template_id');
    }

    public function competencies(): HasMany
    {
        return $this->hasMany(TeachingUnitCompetency::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position');
    }
}
