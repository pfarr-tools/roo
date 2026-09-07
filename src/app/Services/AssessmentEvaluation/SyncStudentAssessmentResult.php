<?php

namespace App\Services\AssessmentEvaluation;

use App\Models\AssessmentBooklet;
use App\Models\AssessmentTask;
use App\Models\StudentAssessmentResult;

final class SyncStudentAssessmentResult
{
    public function __construct(private readonly AssessmentTaskEvaluatorRegistry $evaluators) {}

    public function handle(AssessmentBooklet $booklet, AssessmentTask $task): void
    {
        $booklet->loadMissing('reviews.items', 'reviews.options');

        if ($booklet->status !== 'open' || $booklet->student_id === null) {
            $this->remove($booklet->student_id, $task, $booklet->assessment_id);

            return;
        }

        $review = $booklet->reviews->firstWhere('assessment_task_id', $task->getKey());

        if ($review === null) {
            $this->remove($booklet->student_id, $task, $booklet->assessment_id);

            return;
        }

        $points = $review->items->sum(fn ($item): float => (float) $item->awarded_points) + (float) $review->extra_points;
        $evaluator = $this->evaluators->for($task);
        if ($evaluator !== null) {
            $points += match ($task->task_type) {
                'sorting' => $evaluator->score($task, $review->sorting_sequence ?? []),
                'sentence_builder' => $evaluator->score($task, $review->student_sentence ?? ''),
                default => $evaluator->score($task, $review->options->map(fn ($option): array => [
                    'id' => $option->option_id,
                    'selected' => $option->selected,
                ])->all()),
            };
        }

        $levels = collect([$booklet->level])->filter()->values();
        if ($levels->isEmpty()) {
            $levels = $task->levels()->pluck('level')->filter()->values();
        }
        if ($levels->isEmpty() && filled($task->level)) {
            $levels = collect([$task->level]);
        }
        StudentAssessmentResult::query()->updateOrCreate(
            [
                'assessment_id' => $booklet->assessment_id,
                'assessment_task_id' => $task->getKey(),
                'student_id' => $booklet->student_id,
            ],
            ['points' => number_format($points, 2, '.', ''), ...($levels->count() === 1 ? ['level' => $levels->first()] : [])],
        );
    }

    public function remove(?int $studentId, AssessmentTask $task, int $assessmentId): void
    {
        if ($studentId === null) {
            return;
        }

        StudentAssessmentResult::query()
            ->where('assessment_id', $assessmentId)
            ->where('assessment_task_id', $task->getKey())
            ->where('student_id', $studentId)
            ->delete();
    }

    public function synchronizeBooklet(AssessmentBooklet $booklet, ?int $previousStudentId = null): void
    {
        $booklet->loadMissing('reviews');
        $taskIds = $booklet->reviews->pluck('assessment_task_id')->unique();

        foreach (AssessmentTask::query()->whereKey($taskIds)->get() as $task) {
            if ($previousStudentId !== null && ($previousStudentId !== $booklet->student_id || $booklet->status !== 'open')) {
                $this->remove($previousStudentId, $task, $booklet->assessment_id);
            }

            $this->handle($booklet, $task);
        }
    }
}
