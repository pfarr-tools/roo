<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assessment_task_review_id', 'option_id', 'selected'])]
class AssessmentTaskReviewOption extends Model
{
    protected function casts(): array
    {
        return ['selected' => 'boolean'];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(AssessmentTaskReview::class, 'assessment_task_review_id');
    }
}
