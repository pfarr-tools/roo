<?php

namespace App\Services\AssessmentEvaluation;

use App\Models\AssessmentTask;
use Illuminate\Support\Collection;

final class ExpectationOccurrences
{
    /** @return Collection<int, array{expectation_id: int, occurrence: int, text: string, points: string}> */
    public static function forTask(AssessmentTask $task): Collection
    {
        return $task->expectations()
            ->get(['id', 'text', 'points', 'repetitions'])
            ->flatMap(function ($expectation): Collection {
                return collect(range(1, max(1, (int) $expectation->repetitions)))
                    ->map(fn (int $occurrence): array => [
                        'expectation_id' => $expectation->id,
                        'occurrence' => $occurrence,
                        'text' => $expectation->text,
                        'points' => number_format((float) $expectation->points, 2, '.', ''),
                    ]);
            })
            ->values();
    }
}
