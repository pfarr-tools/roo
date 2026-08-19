<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'teaching_unit_id', 'unit_template_id', 'lesson_template_id', 'phase_template_id', 'original_name', 'description', 'storage_path', 'mime_type', 'size', 'page_count', 'checksum', 'security_status', 'source', 'version'])]
class ResourceReference extends Model
{
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function unitTemplate(): BelongsTo
    {
        return $this->belongsTo(UnitTemplate::class);
    }

    public function teachingUnit(): BelongsTo
    {
        return $this->belongsTo(TeachingUnit::class);
    }
}
