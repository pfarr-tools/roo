<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'name', 'kind', 'external_identifier', 'imported_at'])]
class DataSource extends Model
{
    protected function casts(): array
    {
        return ['imported_at' => 'datetime'];
    }
}
