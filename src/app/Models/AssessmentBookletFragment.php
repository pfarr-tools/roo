<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assessment_booklet_id', 'assessment_task_id', 'image_path', 'page', 'start_y_cm', 'end_y_cm'])]
class AssessmentBookletFragment extends Model
{
    protected function casts(): array
    {
        return [
            'start_y_cm' => 'decimal:3',
            'end_y_cm' => 'decimal:3',
        ];
    }

    public function booklet(): BelongsTo
    {
        return $this->belongsTo(AssessmentBooklet::class, 'assessment_booklet_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(AssessmentTask::class, 'assessment_task_id');
    }
}
