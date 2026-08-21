<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['student_evaluation_id', 'text_block_template_id', 'area', 'text', 'position'])]
class EvaluationBlock extends Model
{
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(StudentEvaluation::class, 'student_evaluation_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TextBlockTemplate::class, 'text_block_template_id');
    }
}
