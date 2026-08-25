<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assessment_task_image_id', 'position', 'x_percent', 'y_percent', 'solution', 'lines'])]
class AssessmentTaskImageLabel extends Model
{
    protected $attributes = ['lines' => 1];

    protected function casts(): array
    {
        return ['position' => 'integer', 'x_percent' => 'decimal:3', 'y_percent' => 'decimal:3', 'lines' => 'integer'];
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(AssessmentTaskImage::class, 'assessment_task_image_id');
    }
}
