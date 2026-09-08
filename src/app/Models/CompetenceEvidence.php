<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['scheduled_lesson_id', 'student_id', 'education_plan_competency_id', 'custom_process_competence_id', 'scale', 'custom_scale_level', 'custom_scale_status', 'note'])]
class CompetenceEvidence extends Model
{
    protected $table = 'competence_evidences';

    public function scheduledLesson(): BelongsTo
    {
        return $this->belongsTo(ScheduledLesson::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function educationPlanCompetency(): BelongsTo
    {
        return $this->belongsTo(EducationPlanCompetency::class, 'education_plan_competency_id');
    }

    public function customProcessCompetence(): BelongsTo
    {
        return $this->belongsTo(CustomProcessCompetence::class);
    }
}
