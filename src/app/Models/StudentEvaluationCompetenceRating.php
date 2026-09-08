<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['student_evaluation_id', 'education_plan_competency_id', 'rating', 'include_in_text', 'include_in_grade'])]
class StudentEvaluationCompetenceRating extends Model
{
    protected function casts(): array
    {
        return ['rating' => 'integer', 'include_in_text' => 'boolean', 'include_in_grade' => 'boolean'];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(StudentEvaluation::class, 'student_evaluation_id');
    }

    public function competence(): BelongsTo
    {
        return $this->belongsTo(EducationPlanCompetency::class, 'education_plan_competency_id');
    }
}
