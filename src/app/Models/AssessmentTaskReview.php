<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['assessment_booklet_id', 'assessment_task_id', 'extra_points', 'extra_note'])]
class AssessmentTaskReview extends Model
{
    protected function casts(): array
    {
        return ['extra_points' => 'decimal:2'];
    }

    public function booklet(): BelongsTo
    {
        return $this->belongsTo(AssessmentBooklet::class, 'assessment_booklet_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(AssessmentTask::class, 'assessment_task_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AssessmentTaskReviewItem::class);
    }
}
