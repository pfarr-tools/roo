<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['student_evaluation_id', 'custom_process_competence_id', 'competence_text_snapshot', 'position_snapshot', 'interval_count_snapshot', 'custom_scale_level', 'custom_scale_status'])]
class StudentEvaluationObservationScale extends Model
{
    protected function casts(): array
    {
        return ['position_snapshot' => 'integer', 'interval_count_snapshot' => 'integer', 'custom_scale_level' => 'integer'];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(StudentEvaluation::class, 'student_evaluation_id');
    }

    public function customProcessCompetence(): BelongsTo
    {
        return $this->belongsTo(CustomProcessCompetence::class);
    }
}
