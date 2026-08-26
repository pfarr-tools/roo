<?php

namespace App\Services\AssessmentEvaluation;

use App\Models\AssessmentTask;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class MatchingTableTaskEvaluator
{
    public function supports(AssessmentTask $task): bool
    {
        return $task->task_type === 'matching_table';
    }

    /** @param list<array{id: string, selected: bool}> $options */
    public function score(AssessmentTask $task, array $options): float
    {
        $this->validate($task, $options);

        return collect($options)->where('selected', true)->count() * $task->pointsPerCorrectAnswer();
    }

    /** @param list<array{id: string, selected: bool}> $options */
    public function validate(AssessmentTask $task, array $options): void
    {
        $definitions = $this->definitions($task);
        $definitionIds = $definitions->pluck('id')->values();
        $providedIds = collect($options)->pluck('id')->values();

        if ($definitionIds->isEmpty()
            || $definitionIds->unique()->count() !== $definitionIds->count()
            || $providedIds->count() !== $definitionIds->count()
            || $providedIds->unique()->count() !== $providedIds->count()
            || $providedIds->diff($definitionIds)->isNotEmpty()
            || $definitionIds->diff($providedIds)->isNotEmpty()
            || collect($options)->contains(fn (array $option): bool => ! is_bool($option['selected'] ?? null))) {
            throw new InvalidArgumentException('Für jede Zuordnung muss genau ein Auswahlstatus übermittelt werden.');
        }
    }

    /** @return Collection<int, array{id: string}> */
    private function definitions(AssessmentTask $task): Collection
    {
        $categories = collect($task->content['categories'] ?? [])->filter(fn ($category): bool => is_array($category));
        $rows = collect($task->content['rows'] ?? [])->filter(fn ($row): bool => is_array($row));

        if (($task->content['matching_scoring_mode'] ?? 'per_category') === 'complete_row') {
            return $rows->map(fn (array $row): array => ['id' => (string) ($row['id'] ?? '')])->values();
        }

        return $rows->flatMap(fn (array $row): array => collect($row['category_ids'] ?? [])
            ->filter(fn ($categoryId): bool => $categories->contains(fn (array $category): bool => (string) ($category['id'] ?? '') === (string) $categoryId))
            ->map(fn ($categoryId): array => ['id' => (string) ($row['id'] ?? '').':'.(string) $categoryId])
            ->all())->values();
    }
}
