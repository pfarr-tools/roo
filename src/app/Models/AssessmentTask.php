<?php

namespace App\Models;

use App\Search\SearchableFields;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Laravel\Scout\Searchable;

#[Fillable(['organization_id', 'education_plan_id', 'education_plan_competency_id', 'title', 'task_type', 'content', 'solution', 'max_points', 'level', 'position'])]
class AssessmentTask extends Model
{
    use Searchable, SearchableFields;

    protected function searchableFields(): array
    {
        return ['title', 'task_type', 'level'];
    }

    public function toSearchableArray(): array
    {
        return $this->searchablePayload();
    }

    protected function casts(): array
    {
        return ['content' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $task): void {
            if (! $task->organization_id) {
                throw ValidationException::withMessages(['organization_id' => 'Eine Prüfungsaufgabe benötigt eine Organisation.']);
            }
            if (! $task->education_plan_competency_id) {
                throw ValidationException::withMessages(['education_plan_competency_id' => 'Eine Prüfungsaufgabe benötigt eine Kompetenz.']);
            }
            if ($task->education_plan_competency_id && ! $task->education_plan_id) {
                throw ValidationException::withMessages(['education_plan_id' => 'Eine Prüfungsaufgabe benötigt einen Bildungsplan.']);
            }
            if ($task->task_type === 'expectation_list') {
                if (! $task->education_plan_competency_id) {
                    throw ValidationException::withMessages(['education_plan_competency_id' => 'Eine Erwartungsliste benötigt eine Prozesskompetenz.']);
                }
                $task->loadMissing('educationPlanCompetency.area');
                if ($task->educationPlanCompetency?->area?->kind !== 'process') {
                    throw ValidationException::withMessages(['education_plan_competency_id' => 'Eine Erwartungsliste muss einer Prozesskompetenz zugeordnet werden.']);
                }
            }
        });
    }

    public function assessments(): BelongsToMany
    {
        return $this->belongsToMany(Assessment::class, 'assessment_task_assessment')->withPivot('position', 'weight')->withTimestamps();
    }

    public function educationPlan(): BelongsTo
    {
        return $this->belongsTo(EducationPlan::class);
    }

    public function educationPlanCompetency(): BelongsTo
    {
        return $this->belongsTo(EducationPlanCompetency::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(StudentAssessmentResult::class);
    }

    public function levels(): HasMany
    {
        return $this->hasMany(AssessmentTaskLevel::class);
    }

    public function expectations(): HasMany
    {
        return $this->hasMany(AssessmentTaskExpectation::class)->orderBy('position');
    }

    public function fragments(): HasMany
    {
        return $this->hasMany(AssessmentBookletFragment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(AssessmentTaskReview::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(AssessmentTaskImage::class)->with(['resource', 'labels'])->orderBy('position');
    }

    public function maximumPoints(): ?int
    {
        $expectations = $this->relationLoaded('expectations') ? $this->expectations : $this->expectations()->get();
        $manualPoints = $expectations->sum(fn ($expectation): int => (int) $expectation->points * (int) ($expectation->repetitions ?: 1));

        if ($this->task_type === 'matching_table') {
            $points = $this->pointsPerCorrectAnswer();
            $rows = collect($this->content['rows'] ?? []);
            $relations = $rows->sum(fn (array $row): int => count($row['category_ids'] ?? []));

            return (int) (($this->content['matching_scoring_mode'] ?? 'per_category') === 'complete_row'
                ? $rows->count() * $points
                : $relations * $points);
        }

        if ($this->task_type === 'sorting') {
            return (int) (collect($this->content['questions'] ?? [])->filter(fn ($question): bool => is_array($question) && trim((string) ($question['label'] ?? '')) !== '')->count()
                * (float) ($this->content['points_per_sentence'] ?? 1));
        }

        if ($this->task_type === 'sentence_builder') {
            return $this->max_points;
        }

        if (! in_array($this->task_type, ['checkbox', 'image_matching', 'image_labeling'], true)) {
            return $manualPoints ?: $this->max_points;
        }

        if ($this->task_type === 'image_matching') {
            return $this->images()->count() * $this->pointsPerCorrectAnswer() + $manualPoints;
        }

        if ($this->task_type === 'image_labeling') {
            $images = $this->relationLoaded('images') ? $this->images : $this->images()->withCount('labels')->get();
            $labelCount = $images->sum(fn (AssessmentTaskImage $image): int => $image->relationLoaded('labels') ? $image->labels->count() : (int) ($image->labels_count ?? 0));

            return $labelCount * $this->pointsPerCorrectAnswer() + $manualPoints;
        }

        $options = collect($this->content['options'] ?? []);
        $correctOptions = $this->checkboxScoringMode() === 'correct_states'
            ? $options->count()
            : $options->where('correct', true)->count();
        $optionPoints = $correctOptions * $this->checkboxPointsPerCorrectAnswer();

        return $optionPoints + $manualPoints;
    }

    public function checkboxPointsPerCorrectAnswer(): int
    {
        $points = $this->content['points_per_correct_answer'] ?? null;

        return is_numeric($points) && (int) $points >= 0 ? (int) $points : 1;
    }

    public function pointsPerCorrectAnswer(): float
    {
        $points = $this->content['points_per_correct_answer'] ?? null;

        return is_numeric($points) && (float) $points >= 0 ? (float) $points : 1.0;
    }

    public function imageWidthCm(): float
    {
        $width = $this->content['image_width_cm'] ?? 3.0;

        return min(4.0, max(1.5, is_numeric($width) ? (float) $width : 3.0));
    }

    public function imageLabelWidthCm(): float
    {
        $width = $this->content['image_label_width_cm'] ?? 6.0;

        return min(8.0, max(4.0, is_numeric($width) ? (float) $width : 6.0));
    }

    public function checkboxScoringMode(): string
    {
        return ($this->content['checkbox_scoring_mode'] ?? null) === 'correct_states'
            ? 'correct_states'
            : 'correct_selections';
    }

    public function lessons(): BelongsToMany
    {
        return $this->belongsToMany(Lesson::class, 'lesson_assessment_tasks')->withPivot('position')->withTimestamps();
    }
}
