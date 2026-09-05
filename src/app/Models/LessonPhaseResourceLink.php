<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use Illuminate\Database\Eloquent\Relations\Pivot;

class LessonPhaseResourceLink extends Pivot
{
    protected function casts(): array
    {
        return ['publication_status' => PublicationStatus::class];
    }
}
