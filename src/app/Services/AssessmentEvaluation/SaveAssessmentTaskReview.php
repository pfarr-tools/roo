<?php

namespace App\Services\AssessmentEvaluation;

use App\Models\AssessmentBooklet;
use App\Models\AssessmentTask;
use App\Models\AssessmentTaskReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveAssessmentTaskReview
{
    public function __construct(private readonly SyncStudentAssessmentResult $resultSync) {}

    /** @param array{items: list<array{expectation_id: int, occurrence: int, awarded_points: int|float|string, note?: ?string}>, extra_points: int|float|string|null, extra_note?: ?string} $data */
    public function handle(AssessmentBooklet $booklet, AssessmentTask $task, array $data): AssessmentTaskReview
    {
        $occurrences = ExpectationOccurrences::forTask($task);
        $expectedOccurrences = $occurrences
            ->map(fn (array $occurrence): string => "{$occurrence['expectation_id']}:{$occurrence['occurrence']}")
            ->values();
        $maximumPoints = $occurrences
            ->mapWithKeys(fn (array $occurrence): array => ["{$occurrence['expectation_id']}:{$occurrence['occurrence']}" => (float) $occurrence['points']]);
        $providedOccurrences = collect($data['items'])
            ->map(fn (array $item): string => "{$item['expectation_id']}:{$item['occurrence']}")
            ->values();

        if ($providedOccurrences->count() !== $expectedOccurrences->count()
            || $providedOccurrences->unique()->count() !== $providedOccurrences->count()
            || $providedOccurrences->diff($expectedOccurrences)->isNotEmpty()
            || $expectedOccurrences->diff($providedOccurrences)->isNotEmpty()) {
            throw ValidationException::withMessages(['items' => 'Für jede Erwartungsausprägung muss genau eine Bewertungszeile übermittelt werden.']);
        }

        foreach ($data['items'] as $index => $item) {
            $key = "{$item['expectation_id']}:{$item['occurrence']}";
            if ((float) $item['awarded_points'] > $maximumPoints->get($key)) {
                throw ValidationException::withMessages(["items.{$index}.awarded_points" => 'Die vergebenen Punkte dürfen die maximale Punktzahl dieser Erwartung nicht überschreiten.']);
            }
        }

        return DB::transaction(function () use ($booklet, $task, $data, $providedOccurrences): AssessmentTaskReview {
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

            $review->items->each(function ($item) use ($providedOccurrences): void {
                $key = "{$item->assessment_task_expectation_id}:{$item->occurrence}";
                if (! $providedOccurrences->contains($key)) {
                    $item->delete();
                }
            });

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
            $this->resultSync->handle($booklet->fresh(), $task);

            return $review;
        });
    }
}
