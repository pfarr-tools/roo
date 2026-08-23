<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assessment_task_review_id', 'assessment_task_expectation_id', 'occurrence', 'awarded_points', 'note'])]
class AssessmentTaskReviewItem extends Model
{
    protected function casts(): array
    {
        return ['awarded_points' => 'decimal:2'];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(AssessmentTaskReview::class, 'assessment_task_review_id');
    }

    public function expectation(): BelongsTo
    {
        return $this->belongsTo(AssessmentTaskExpectation::class, 'assessment_task_expectation_id');
    }
}
