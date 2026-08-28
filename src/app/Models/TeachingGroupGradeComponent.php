<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['teaching_group_id', 'type', 'stable_key', 'label', 'percentage', 'position', 'is_active'])]
class TeachingGroupGradeComponent extends Model
{
    protected function casts(): array
    {
        return ['percentage' => 'integer', 'position' => 'integer', 'is_active' => 'boolean'];
    }

    public function teachingGroup(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class);
    }
}
