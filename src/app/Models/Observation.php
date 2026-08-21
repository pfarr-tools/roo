<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['scheduled_lesson_id', 'student_id', 'observation_type_id', 'note'])]
class Observation extends Model
{
    public function scheduledLesson(): BelongsTo
    {
        return $this->belongsTo(ScheduledLesson::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ObservationType::class, 'observation_type_id');
    }
}
