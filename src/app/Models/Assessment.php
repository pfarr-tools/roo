<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'teaching_group_id', 'title', 'assessed_on', 'status', 'notes'])]
class Assessment extends Model
{
    protected function casts(): array { return ['assessed_on' => 'date']; }
    public function group(): BelongsTo { return $this->belongsTo(TeachingGroup::class, 'teaching_group_id'); }
    public function tasks(): HasMany { return $this->hasMany(AssessmentTask::class)->orderBy('position'); }
}
