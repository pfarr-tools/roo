<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['group_year_plan_id', 'user_id', 'revision', 'action', 'description'])]
class PlanRevision extends Model
{
    public function plan(): BelongsTo
    {
        return $this->belongsTo(GroupYearPlan::class, 'group_year_plan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
