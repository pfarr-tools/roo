<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assessment_task_id', 'student_id', 'points', 'level', 'numeric_grade', 'note'])]
class StudentAssessmentResult extends Model
{
    public function task(): BelongsTo { return $this->belongsTo(AssessmentTask::class, 'assessment_task_id'); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
}
