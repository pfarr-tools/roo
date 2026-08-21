<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'area', 'level', 'text', 'is_active'])]
class TextBlockTemplate extends Model {}
