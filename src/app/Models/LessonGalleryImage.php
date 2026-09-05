<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lesson_id', 'resource_reference_id', 'position'])]
class LessonGalleryImage extends Model
{
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(ResourceReference::class, 'resource_reference_id');
    }
}
