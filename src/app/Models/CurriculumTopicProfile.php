<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['curriculum_topic_id', 'denomination', 'perspective'])]
class CurriculumTopicProfile extends Model
{
    protected function casts(): array
    {
        return ['perspective' => 'array'];
    }
}
