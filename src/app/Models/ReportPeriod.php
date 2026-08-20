<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
#[Fillable(['organization_id','teaching_group_id','label','starts_on','ends_on'])]
class ReportPeriod extends Model { protected function casts(): array { return ['starts_on'=>'date','ends_on'=>'date']; } public function group(): BelongsTo { return $this->belongsTo(TeachingGroup::class,'teaching_group_id'); } public function evaluations(): HasMany { return $this->hasMany(StudentEvaluation::class); } }
