<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['assessment_task_id', 'level'])]
class AssessmentTaskLevel extends Model
{
    public $timestamps = false;
    public $incrementing = false;

    protected $table = 'assessment_task_levels';
    protected $primaryKey = null;
}
