<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['school_year_id', 'date', 'kind', 'label', 'source_type', 'source_id'])]
class SchoolYearDay extends Model
{
    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
