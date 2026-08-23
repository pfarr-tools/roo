<?php

namespace App\Services\AssessmentEvaluation;

use App\Models\AssessmentBooklet;
use App\Models\AssessmentTask;
use App\Models\AssessmentTaskReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveAssessmentTaskReview
{
    /** @param array{items: list<array{expectation_id: int, occurrence: int, awarded_points: int|float|string, note?: ?string}>, extra_points: int|float|string|null, extra_note?: ?string} $data */
    public function handle(AssessmentBooklet $booklet, AssessmentTask $task, array $data): AssessmentTaskReview
    {
        $expectedOccurrences = $task->expectations()
            ->get(['id', 'repetitions'])
            ->flatMap(fn ($expectation) => collect(range(1, max(1, (int) $expectation->repetitions)))
                ->map(fn (int $occurrence): string => "{$expectation->id}:{$occurrence}"))
            ->values();
        $providedOccurrences = collect($data['items'])
            ->map(fn (array $item): string => "{$item['expectation_id']}:{$item['occurrence']}")
            ->values();

        if ($providedOccurrences->count() !== $expectedOccurrences->count()
            || $providedOccurrences->unique()->count() !== $providedOccurrences->count()
            || $providedOccurrences->diff($expectedOccurrences)->isNotEmpty()
            || $expectedOccurrences->diff($providedOccurrences)->isNotEmpty()) {
            throw ValidationException::withMessages(['items' => 'Für jede Erwartungsausprägung muss genau eine Bewertungszeile übermittelt werden.']);
        }

        return DB::transaction(function () use ($booklet, $task, $data): AssessmentTaskReview {
            $review = AssessmentTaskReview::query()
                ->where('assessment_booklet_id', $booklet->getKey())
                ->where('assessment_task_id', $task->getKey())
                ->lockForUpdate()
                ->first();

            if ($review === null) {
                $review = AssessmentTaskReview::create([
                    'assessment_booklet_id' => $booklet->getKey(),
                    'assessment_task_id' => $task->getKey(),
                ]);
            }

            $review->update([
                'extra_points' => $data['extra_points'] ?? 0,
                'extra_note' => $data['extra_note'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $review->items()->updateOrCreate(
                    [
                        'assessment_task_expectation_id' => $item['expectation_id'],
                        'occurrence' => $item['occurrence'],
                    ],
                    [
                        'awarded_points' => $item['awarded_points'],
                        'note' => $item['note'] ?? null,
                    ],
                );
            }

            $review->load('items');

            return $review;
        });
    }
}
