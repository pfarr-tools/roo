<?php

namespace App\Services\AssessmentEvaluation;

use App\Models\AssessmentBooklet;
use App\Models\AssessmentTask;
use App\Models\StudentAssessmentResult;

final class SyncStudentAssessmentResult
{
    public function handle(AssessmentBooklet $booklet, AssessmentTask $task): void
    {
        $booklet->loadMissing('reviews.items');

        if ($booklet->status !== 'open' || $booklet->student_id === null) {
            $this->remove($booklet->student_id, $task);

            return;
        }

        $review = $booklet->reviews->firstWhere('assessment_task_id', $task->getKey());

        if ($review === null) {
            $this->remove($booklet->student_id, $task);

            return;
        }

        $points = $review->items->sum(fn ($item): float => (float) $item->awarded_points) + (float) $review->extra_points;

        StudentAssessmentResult::query()->updateOrCreate(
            [
                'assessment_task_id' => $task->getKey(),
                'student_id' => $booklet->student_id,
            ],
            ['points' => number_format($points, 2, '.', '')],
        );
    }

    public function remove(?int $studentId, AssessmentTask $task): void
    {
        if ($studentId === null) {
            return;
        }

        StudentAssessmentResult::query()
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
                $this->remove($previousStudentId, $task);
            }

            $this->handle($booklet, $task);
        }
    }
}
