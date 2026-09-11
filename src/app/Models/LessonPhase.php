<?php

namespace App\Models;

use App\Search\SearchableFields;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Scout\Searchable;

#[Fillable(['lesson_id', 'phase_template_id', 'title', 'position', 'duration_minutes', 'social_form_id', 'teacher_interaction', 'learner_activity', 'differentiation', 'didactic_comment', 'materials', 'media'])]
class LessonPhase extends Model
{
    use Searchable, SearchableFields;

    protected function searchableFields(): array
    {
        return ['title', 'teacher_interaction', 'learner_activity', 'differentiation', 'didactic_comment', 'materials', 'media'];
    }

    public function toSearchableArray(): array
    {
        $payload = $this->searchablePayload();
        $payload['user_id'] = $this->lesson()->with('unit')->first()?->unit?->user_id;

        return $payload;
    }

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

    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(ResourceReference::class, 'lesson_phase_resources')->using(LessonPhaseResource::class)->withPivot('publication_status');
    }

    public function resourceLinks(): BelongsToMany
    {
        return $this->belongsToMany(ResourceLink::class, 'lesson_phase_resource_links')->using(LessonPhaseResourceLink::class)->withPivot('publication_status');
    }

    public function materialItems(): BelongsToMany
    {
        return $this->belongsToMany(MaterialItem::class, 'lesson_phase_material_items');
    }

    public function songs(): BelongsToMany
    {
        return $this->belongsToMany(SongVersion::class, 'phase_songs')->withPivot('position')->withTimestamps();
    }

    public function songbooks(): BelongsToMany
    {
        return $this->belongsToMany(GroupSongbook::class, 'phase_songbooks')->withTimestamps();
    }
}
