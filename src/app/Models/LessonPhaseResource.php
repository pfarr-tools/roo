<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use Illuminate\Database\Eloquent\Relations\Pivot;

class LessonPhaseResource extends Pivot
{
    protected function casts(): array
    {
        return ['publication_status' => PublicationStatus::class];
    }
}
