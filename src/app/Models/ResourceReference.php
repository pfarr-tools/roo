<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Scout\Searchable;
use App\Search\SearchableFields;

#[Fillable(['user_id', 'teaching_unit_id', 'lesson_id', 'unit_template_id', 'lesson_template_id', 'phase_template_id', 'original_name', 'description', 'copyrights', 'storage_path', 'mime_type', 'size', 'page_count', 'checksum', 'security_status', 'source', 'version', 'publication_status'])]
class ResourceReference extends Model
{
    use Searchable, SearchableFields;

    protected function searchableFields(): array
    {
        return ['original_name', 'description', 'mime_type'];
    }

    public function toSearchableArray(): array
    {
        return $this->searchablePayload();
    }

    protected function casts(): array
    {
        return ['publication_status' => PublicationStatus::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unitTemplate(): BelongsTo
    {
        return $this->belongsTo(UnitTemplate::class);
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
        return $this->belongsToMany(LessonPhase::class, 'lesson_phase_resources')->using(LessonPhaseResource::class)->withPivot('publication_status');
    }
}
