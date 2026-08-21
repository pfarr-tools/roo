<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\TeachingGroup;

#[Fillable(['curriculum_topic_id', 'education_plan_competency_id', 'denomination', 'competency_kind', 'external_identifier', 'display', 'text', 'raw_text', 'position'])]
class CurriculumTopicCompetency extends Model
{
    public $timestamps = false;

    public function topic(): BelongsTo
    {
        return $this->belongsTo(CurriculumTopic::class, 'curriculum_topic_id');
    }

    public function educationPlanCompetency(): BelongsTo
    {
        return $this->belongsTo(EducationPlanCompetency::class);
    }

    public function scopeForGroup($query, TeachingGroup $group)
    {
        return $query->when($group->denomination, fn ($query) => $query->where(function ($query) use ($group): void {
            $query->whereNull('denomination')->orWhere('denomination', $group->denomination);
        }));
    }
}
