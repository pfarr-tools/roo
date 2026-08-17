<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'education_plan_version_id', 'source_path', 'source_checksum', 'schema_version', 'status', 'statistics', 'error_message', 'started_at', 'finished_at'])]
class EducationPlanImportRun extends Model
{
    protected function casts(): array
    {
        return ['statistics' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
