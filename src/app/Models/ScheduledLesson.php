<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lesson_id', 'schedule_slot_id', 'status'])]
class ScheduledLesson extends Model
{
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(ScheduleSlot::class, 'schedule_slot_id');
    }
}
