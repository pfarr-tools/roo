<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['scheduled_lesson_id', 'student_id', 'teaching_unit_competency_id', 'scale', 'note'])]
class CompetenceEvidence extends Model
{
    protected $table = 'competence_evidences';

    public function scheduledLesson(): BelongsTo { return $this->belongsTo(ScheduledLesson::class); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function competency(): BelongsTo { return $this->belongsTo(TeachingUnitCompetency::class, 'teaching_unit_competency_id'); }
}
