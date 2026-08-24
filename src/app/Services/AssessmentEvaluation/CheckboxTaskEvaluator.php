<?php

namespace App\Services\AssessmentEvaluation;

use App\Models\AssessmentTask;
use InvalidArgumentException;

final class CheckboxTaskEvaluator
{
    public function supports(AssessmentTask $task): bool
    {
        return $task->task_type === 'checkbox'
            && ($task->content['evaluation_mode'] ?? null) !== 'legacy_checkbox';
    }

    /** @param list<array{id: string, selected: bool}> $options */
    public function score(AssessmentTask $task, array $options): float
    {
        $this->validate($task, $options);
        $definitions = collect($task->content['options'] ?? [])->keyBy('id');
        $points = (float) ($task->content['points_per_correct_answer'] ?? 0);

        return collect($options)
            ->filter(fn (array $option): bool => (bool) $option['selected'] && (bool) ($definitions->get($option['id'])['correct'] ?? false))
            ->count() * $points;
    }

    /** @param list<array{id: string, selected: bool}> $options */
    public function validate(AssessmentTask $task, array $options): void
    {
        $definitions = collect($task->content['options'] ?? []);
        $definitionIds = $definitions->pluck('id')->filter(fn ($id): bool => is_string($id) && $id !== '')->values();
        $providedIds = array_map(fn (array $option): mixed => $option['id'] ?? null, $options);
        $points = $task->content['points_per_correct_answer'] ?? null;

        if ($definitionIds->count() !== $definitions->count() || $definitionIds->unique()->count() !== $definitionIds->count()) {
            throw $this->invalid('Die Checkbox-Optionen benötigen eindeutige IDs.');
        }

        if (! is_numeric($points) || (float) $points < 0) {
            throw $this->invalid('Die Punktzahl pro korrekte Antwort ist ungültig.');
        }

        $definitionIdValues = $definitionIds->all();

        if (count($providedIds) !== count($definitionIdValues)
            || count(array_unique($providedIds)) !== count($providedIds)
            || array_diff($providedIds, $definitionIdValues) !== []
            || array_diff($definitionIdValues, $providedIds) !== []
            || collect($options)->contains(fn (array $option): bool => ! is_bool($option['selected'] ?? null))) {
            throw $this->invalid('Für jede Checkbox-Option muss genau ein Auswahlstatus übermittelt werden.');
        }
    }

    private function invalid(string $message): InvalidArgumentException
    {
        return new InvalidArgumentException($message);
    }
}
