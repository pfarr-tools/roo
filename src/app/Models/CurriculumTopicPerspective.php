<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['curriculum_topic_id', 'denomination', 'text'])]
class CurriculumTopicPerspective extends Model
{
    public function topic(): BelongsTo
    {
        return $this->belongsTo(CurriculumTopic::class, 'curriculum_topic_id');
    }
}
