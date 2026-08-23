<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['assessment_id', 'student_id', 'number', 'status', 'name_fragment_path'])]
class AssessmentBooklet extends Model
{
    protected static function booted(): void
    {
        static::deleting(function (AssessmentBooklet $booklet): void {
            $paths = array_filter([
                $booklet->name_fragment_path,
                ...$booklet->fragments()->pluck('image_path')->all(),
            ]);
            Storage::disk('documents')->delete($paths);
            Storage::disk('documents')->deleteDirectory("assessment-booklets/{$booklet->assessment_id}/{$booklet->getKey()}");
        });
    }

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
