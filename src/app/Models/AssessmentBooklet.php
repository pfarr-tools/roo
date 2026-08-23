<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['assessment_id', 'student_id', 'number', 'status', 'name_fragment_path'])]
class AssessmentBooklet extends Model
{
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function fragments(): HasMany
    {
        return $this->hasMany(AssessmentBookletFragment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(AssessmentTaskReview::class);
    }
}
