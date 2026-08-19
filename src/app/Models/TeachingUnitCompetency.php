<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['teaching_unit_id', 'education_plan_competency_id', 'curriculum_topic_competency_id', 'source_curriculum_topic_id', 'local_wording', 'is_secondary'])]
class TeachingUnitCompetency extends Model
{
    protected function casts(): array
    {
        return ['is_secondary' => 'boolean'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(TeachingUnit::class, 'teaching_unit_id');
    }

    public function educationPlanCompetency(): BelongsTo
    {
        return $this->belongsTo(EducationPlanCompetency::class);
    }

    public function curriculumCompetency(): BelongsTo
    {
        return $this->belongsTo(CurriculumTopicCompetency::class, 'curriculum_topic_competency_id');
    }

    public function lessons(): BelongsToMany
    {
        return $this->belongsToMany(Lesson::class, 'lesson_competencies');
    }
}
