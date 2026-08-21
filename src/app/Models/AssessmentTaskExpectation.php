<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assessment_task_id', 'text', 'points', 'repetitions', 'position'])]
class AssessmentTaskExpectation extends Model
{
    public function task(): BelongsTo
    {
        return $this->belongsTo(AssessmentTask::class, 'assessment_task_id');
    }
}
