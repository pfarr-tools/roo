<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lesson_id', 'phase_template_id', 'title', 'position', 'duration_minutes', 'social_form_id', 'teacher_interaction', 'learner_activity', 'differentiation', 'didactic_comment', 'materials', 'media'])]
class LessonPhase extends Model
{
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PhaseTemplate::class, 'phase_template_id');
    }

    public function socialForm(): BelongsTo
    {
        return $this->belongsTo(SocialForm::class);
    }
}
