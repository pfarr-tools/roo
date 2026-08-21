<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['scheduled_lesson_id', 'student_id', 'status', 'note'])]
class AttendanceRecord extends Model
{
    public const STATUSES = ['present', 'absent', 'late'];

    public function scheduledLesson(): BelongsTo
    {
        return $this->belongsTo(ScheduledLesson::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
