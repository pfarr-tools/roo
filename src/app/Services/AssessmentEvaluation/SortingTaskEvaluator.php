<?php

namespace App\Services\AssessmentEvaluation;

use App\Models\AssessmentTask;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class SortingTaskEvaluator
{
    public function supports(AssessmentTask $task): bool
    {
        return $task->task_type === 'sorting';
    }

    /** @param array<string, int|string> $sequence */
    public function score(AssessmentTask $task, array $sequence): float
    {
        $this->validate($task, $sequence);
        $questions = $this->questions($task)->all();
        $totalPairs = count($questions) * (count($questions) - 1) / 2;
        if ($totalPairs === 0) {
            return 0.0;
        }

        $correctPairs = 0;
        foreach ($questions as $leftIndex => $left) {
            foreach (array_slice($questions, $leftIndex + 1) as $right) {
                if ($this->isCorrectPair($sequence[$left['id']] ?? null, $sequence[$right['id']] ?? null, count($questions))) {
                    $correctPairs++;
                }
            }
        }

        return count($questions) * $this->pointsPerSentence($task) * $correctPairs / $totalPairs;
    }

    /** @param array<string, int|string> $sequence */
    public function percentage(AssessmentTask $task, array $sequence): float
    {
        $questions = $this->questions($task)->all();
        $totalPairs = count($questions) * (count($questions) - 1) / 2;
        if ($totalPairs === 0) {
            return 0.0;
        }

        $correctPairs = 0;
        foreach ($questions as $leftIndex => $left) {
            foreach (array_slice($questions, $leftIndex + 1) as $right) {
                if ($this->isCorrectPair($sequence[$left['id']] ?? null, $sequence[$right['id']] ?? null, count($questions))) {
                    $correctPairs++;
                }
            }
        }

        return $correctPairs / $totalPairs * 100;
    }

    /** @param array<string, int|string> $sequence */
    public function validate(AssessmentTask $task, array $sequence): void
    {
        $questions = $this->questions($task);
        $ids = $questions->pluck('id')->all();
        $providedIds = array_keys($sequence);
        $positions = array_values($sequence);

        if (array_diff($providedIds, $ids) !== []
            || collect($positions)->contains(fn ($position): bool => $position !== null && filter_var($position, FILTER_VALIDATE_INT) === false)) {
            throw new InvalidArgumentException('Für jeden Satz muss genau eine eindeutige Reihenfolge angegeben werden.');
        }
    }

    private function isCorrectPair(mixed $left, mixed $right, int $questionCount): bool
    {
        $left = filter_var($left, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        $right = filter_var($right, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

        return $left !== null
            && $right !== null
            && $left >= 1
            && $left <= $questionCount
            && $right >= 1
            && $right <= $questionCount
            && $left !== $right
            && $left < $right;
    }

    public function pointsPerSentence(AssessmentTask $task): float
    {
        $points = $task->content['points_per_sentence'] ?? 1;

        return is_numeric($points) && (float) $points >= 0 ? (float) $points : 1.0;
    }

    /** @return Collection<int, array{id: string, label: string}> */
    public function questions(AssessmentTask $task): Collection
    {
        $questionsById = collect($task->content['questions'] ?? [])
            ->filter(fn ($question): bool => is_array($question) && trim((string) ($question['label'] ?? '')) !== '')
            ->values()
            ->map(fn (array $question, int $index): array => [
                'id' => (string) ($question['id'] ?? 'sentence-'.($index + 1)),
                'label' => (string) $question['label'],
            ])
            ->keyBy('id');
        return $questionsById->values();
    }

    /** @return Collection<int, array{id: string, label: string}> */
    public function displayQuestions(AssessmentTask $task): Collection
    {
        $questionsById = $this->questions($task)->keyBy('id');
        $sortingOrder = collect($task->content['sorting_order'] ?? [])
            ->map(fn ($id): string => (string) $id)
            ->filter(fn (string $id): bool => $questionsById->has($id));

        return $sortingOrder
            ->map(fn (string $id): array => $questionsById->get($id))
            ->concat($questionsById->except($sortingOrder->all())->values())
            ->values();
    }
}
