<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['planned_lesson_id', 'planned_on', 'actual_on', 'status', 'notes'])]
class LessonOccurrence extends Model
{
    protected function casts(): array
    {
        return ['planned_on' => 'date', 'actual_on' => 'date'];
    }

    public function plannedLesson(): BelongsTo
    {
        return $this->belongsTo(PlannedLesson::class);
    }
}
