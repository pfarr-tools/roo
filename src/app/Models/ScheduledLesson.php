<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['lesson_id', 'schedule_slot_id', 'status', 'actual_on', 'execution_notes'])]
class ScheduledLesson extends Model
{
    protected function casts(): array
    {
        return ['actual_on' => 'date'];
    }

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_PLANNED = 'planned';

    public const STATUS_READY = 'ready';

    public const STATUS_CONDUCTED = 'conducted';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_POSTPONED = 'postponed';

    public static function statuses(): array
    {
        return [self::STATUS_ASSIGNED, self::STATUS_PLANNED, self::STATUS_READY, self::STATUS_CONDUCTED, self::STATUS_CANCELLED, self::STATUS_POSTPONED];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(ScheduleSlot::class, 'schedule_slot_id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function observations(): HasMany
    {
        return $this->hasMany(Observation::class);
    }

    public function competenceEvidences(): HasMany
    {
        return $this->hasMany(CompetenceEvidence::class);
    }
}
