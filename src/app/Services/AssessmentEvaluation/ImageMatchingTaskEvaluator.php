<?php

namespace App\Services\AssessmentEvaluation;

use App\Models\AssessmentTask;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class ImageMatchingTaskEvaluator
{
    public function supports(AssessmentTask $task): bool
    {
        return $task->task_type === 'image_matching';
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
        $definitionIds = $this->definitions($task)->pluck('identifier')->filter()->values();
        $providedIds = collect($options)->pluck('id')->values();
        $points = $task->content['points_per_correct_answer'] ?? null;

        if ($definitionIds->count() !== $task->images->count() || $definitionIds->unique()->count() !== $definitionIds->count()) {
            throw new InvalidArgumentException('Die Bildzuordnungen benötigen eindeutige IDs.');
        }
        if ($points !== null && (! is_numeric($points) || (float) $points < 0)) {
            throw new InvalidArgumentException('Die Punktzahl pro korrekte Zuordnung ist ungültig.');
        }
        if ($providedIds->count() !== $definitionIds->count()
            || $providedIds->unique()->count() !== $providedIds->count()
            || $providedIds->diff($definitionIds)->isNotEmpty()
            || $definitionIds->diff($providedIds)->isNotEmpty()
            || collect($options)->contains(fn (array $option): bool => ! is_bool($option['selected'] ?? null))) {
            throw new InvalidArgumentException('Für jede Bildzuordnung muss genau ein Auswahlstatus übermittelt werden.');
        }
    }

    private function definitions(AssessmentTask $task): Collection
    {
        return $task->images->values();
    }
}
