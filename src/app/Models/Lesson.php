<?php

namespace App\Models;

use App\Search\SearchableFields;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

#[Fillable(['teaching_unit_id', 'lesson_template_id', 'title', 'duration', 'position', 'learning_goals', 'materials', 'homework', 'assessment_note', 'notes'])]
class Lesson extends Model
{
    use Searchable, SearchableFields;

    protected function searchableFields(): array
    {
        return ['title', 'learning_goals', 'materials', 'homework', 'assessment_note', 'notes'];
    }

    public function toSearchableArray(): array
    {
        $payload = $this->searchablePayload();
        $payload['user_id'] = $this->unit()->value('user_id');

        return $payload;
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(TeachingUnit::class, 'teaching_unit_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(LessonTemplate::class, 'lesson_template_id');
    }

    public function educationPlanCompetencies(): BelongsToMany
    {
        return $this->belongsToMany(EducationPlanCompetency::class, 'lesson_competencies', 'lesson_id', 'education_plan_competency_id')
            ->withPivot('curriculum_topic_education_plan_reference_id');
    }

    public function phases(): HasMany
    {
        return $this->hasMany(LessonPhase::class)->orderBy('position');
    }

    public function scheduledLessons(): HasMany
    {
        return $this->hasMany(ScheduledLesson::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(ResourceReference::class);
    }

    public function galleryImages(): HasMany
    {
        return $this->hasMany(LessonGalleryImage::class)->orderBy('position');
    }

    public function resourceLinks(): HasMany
    {
        return $this->hasMany(ResourceLink::class);
    }

    public function materialItems(): BelongsToMany
    {
        return $this->morphToMany(MaterialItem::class, 'material_itemable');
    }

    public function assessmentTasks(): BelongsToMany
    {
        return $this->belongsToMany(AssessmentTask::class, 'lesson_assessment_tasks')->withPivot('position')->withTimestamps();
    }

    public function songs(): BelongsToMany
    {
        return $this->belongsToMany(SongVersion::class, 'lesson_songs')->withPivot('position')->withTimestamps();
    }

    public function songbooks(): BelongsToMany
    {
        return $this->belongsToMany(GroupSongbook::class, 'lesson_songbooks')->withTimestamps();
    }
}
