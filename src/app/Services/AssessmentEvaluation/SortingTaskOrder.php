<?php

namespace App\Services\AssessmentEvaluation;

final class SortingTaskOrder
{
    /** @param array<string, mixed> $content */
    /** @param array<string, mixed>|null $previousContent */
    public function apply(array $content, ?array $previousContent = null): array
    {
        $ids = collect($content['questions'] ?? [])
            ->filter(fn ($question): bool => is_array($question) && trim((string) ($question['label'] ?? '')) !== '')
            ->map(fn (array $question, int $index): string => (string) ($question['id'] ?? 'sentence-'.($index + 1)))
            ->values()
            ->all();

        $previousOrder = collect($previousContent['sorting_order'] ?? [])
            ->filter(fn ($id): bool => in_array((string) $id, $ids, true))
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
        $newIds = array_values(array_diff($ids, $previousOrder));

        if ($previousOrder === []) {
            shuffle($newIds);
            $previousOrder = [];
        }

        shuffle($newIds);
        $content['sorting_order'] = [...$previousOrder, ...$newIds];

        return $content;
    }
}
