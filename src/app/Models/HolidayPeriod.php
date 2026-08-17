<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['school_year_id', 'data_source_id', 'name', 'external_identifier', 'starts_on', 'ends_on', 'change_reason'])]
class HolidayPeriod extends Model
{
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }
}
