<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['education_plan_version_id', 'external_identifier', 'label', 'position'])]
class EducationPlanLevel extends Model
{
    public $timestamps = false;
}
