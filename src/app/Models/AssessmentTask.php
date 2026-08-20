<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['assessment_id', 'teaching_unit_competency_id', 'title', 'solution', 'max_points', 'level', 'position'])]
class AssessmentTask extends Model
{
    public function assessment(): BelongsTo { return $this->belongsTo(Assessment::class); }
    public function competency(): BelongsTo { return $this->belongsTo(TeachingUnitCompetency::class, 'teaching_unit_competency_id'); }
    public function results(): HasMany { return $this->hasMany(StudentAssessmentResult::class); }
}
