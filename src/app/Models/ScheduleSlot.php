<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['teaching_group_id', 'date', 'period_number', 'starts_at', 'ends_at', 'status', 'label', 'notes'])]
class ScheduleSlot extends Model
{
    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class, 'teaching_group_id');
    }

    public function scheduledLesson(): HasOne
    {
        return $this->hasOne(ScheduledLesson::class);
    }
}
