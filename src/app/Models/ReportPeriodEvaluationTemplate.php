<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['report_period_id', 'level', 'original_text', 'text'])]
class ReportPeriodEvaluationTemplate extends Model
{
    public function period(): BelongsTo
    {
        return $this->belongsTo(ReportPeriod::class, 'report_period_id');
    }
}
