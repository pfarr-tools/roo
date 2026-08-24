<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assessment_task_id', 'resource_reference_id', 'identifier', 'position', 'label', 'answer'])]
class AssessmentTaskImage extends Model
{
    public function task(): BelongsTo
    {
        return $this->belongsTo(AssessmentTask::class, 'assessment_task_id');
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(ResourceReference::class, 'resource_reference_id');
    }
}
