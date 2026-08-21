<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['report_period_id', 'student_id', 'draft_text', 'teacher_note', 'status', 'confirmed_at'])]
class StudentEvaluation extends Model
{
    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime'];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(ReportPeriod::class, 'report_period_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(EvaluationBlock::class)->orderBy('position');
    }
}
