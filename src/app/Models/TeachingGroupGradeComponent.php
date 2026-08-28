<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['teaching_group_id', 'type', 'label', 'percentage', 'position'])]
class TeachingGroupGradeComponent extends Model
{
    protected function casts(): array
    {
        return ['percentage' => 'integer', 'position' => 'integer'];
    }

    public function teachingGroup(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class);
    }
}
