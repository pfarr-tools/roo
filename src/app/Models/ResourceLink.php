<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['organization_id', 'teaching_unit_id', 'lesson_id', 'title', 'url', 'description', 'publication_status'])]
class ResourceLink extends Model
{
    protected function casts(): array
    {
        return ['publication_status' => PublicationStatus::class];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function teachingUnit(): BelongsTo
    {
        return $this->belongsTo(TeachingUnit::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function phases(): BelongsToMany
    {
        return $this->belongsToMany(LessonPhase::class, 'lesson_phase_resource_links')->using(LessonPhaseResourceLink::class)->withPivot('publication_status');
    }
}
