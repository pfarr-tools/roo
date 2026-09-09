<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'curriculum_id', 'school_id', 'valid_from', 'valid_until', 'school_type', 'grades', 'notes'])]
class CurriculumSchoolAssignment extends Model
{
    protected function casts(): array
    {
        return ['valid_from' => 'date', 'valid_until' => 'date', 'grades' => 'array'];
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
