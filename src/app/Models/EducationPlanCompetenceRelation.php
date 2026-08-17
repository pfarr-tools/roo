<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['source_competency_id', 'target_competency_id', 'relation_type', 'target_plan_identifier', 'target_external_identifier', 'raw_reference', 'position'])]
class EducationPlanCompetenceRelation extends Model
{
    public $timestamps = false;
}
