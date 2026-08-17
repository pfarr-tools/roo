<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['school_year_id', 'data_source_id', 'date', 'kind', 'label', 'change_reason'])]
class CalendarException extends Model
{
    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
