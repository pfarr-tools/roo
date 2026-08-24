<?php

namespace App\Services\AssessmentEvaluation;

use App\Models\AssessmentTask;
use InvalidArgumentException;

final class CheckboxTaskEvaluator
{
    public function supports(AssessmentTask $task): bool
    {
        return $task->task_type === 'checkbox';
    }

    /** @param list<array{id: string, selected: bool}> $options */
    public function score(AssessmentTask $task, array $options): float
    {
        $this->validate($task, $options);
        $definitions = $this->definitions($task)->keyBy('id');
        $points = (float) $task->checkboxPointsPerCorrectAnswer();

        return collect($options)
            ->filter(function (array $option) use ($definitions, $task): bool {
                $selected = (bool) $option['selected'];
                $correct = (bool) ($definitions->get($option['id'])['correct'] ?? false);

                return $task->checkboxScoringMode() === 'correct_states'
                    ? $selected === $correct
                    : $selected && $correct;
            })
            ->count() * $points;
    }

    /** @param list<array{id: string, selected: bool}> $options */
    public function validate(AssessmentTask $task, array $options): void
    {
        $definitions = $this->definitions($task);
        $definitionIds = $definitions->pluck('id')->filter(fn ($id): bool => is_string($id) && $id !== '')->values();
        $providedIds = array_map(fn (array $option): mixed => $option['id'] ?? null, $options);
        $points = $task->content['points_per_correct_answer'] ?? null;

        if ($definitionIds->count() !== $definitions->count() || $definitionIds->unique()->count() !== $definitionIds->count()) {
            throw $this->invalid('Die Checkbox-Optionen benötigen eindeutige IDs.');
        }

        if ($points !== null && (! is_numeric($points) || (float) $points < 0)) {
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

    private function definitions(AssessmentTask $task): \Illuminate\Support\Collection
    {
        return collect($task->content['options'] ?? [])->values()->map(
            fn (array $option, int $index): array => [
                ...$option,
                'id' => is_string($option['id'] ?? null) && $option['id'] !== ''
                    ? $option['id']
                    : 'option-'.($index + 1),
            ],
        );
    }
}
