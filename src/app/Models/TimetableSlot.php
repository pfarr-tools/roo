<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['teaching_group_id', 'weekday', 'starts_at', 'ends_at', 'room'])]
class TimetableSlot extends Model
{
    protected $table = 'teaching_group_timetable_slots';

    public function teachingGroup(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class);
    }
}
