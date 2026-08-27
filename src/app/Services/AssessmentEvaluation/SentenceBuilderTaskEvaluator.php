<?php

namespace App\Services\AssessmentEvaluation;

use App\Models\AssessmentTask;

final class SentenceBuilderTaskEvaluator
{
    public function __construct(private readonly SentenceBuilderWordOrder $wordOrder) {}

    public function supports(AssessmentTask $task): bool
    {
        return $task->task_type === 'sentence_builder';
    }

    public function score(AssessmentTask $task, string $studentSentence): float
    {
        $totalPairs = $this->totalPairs($task);
        if ($totalPairs === 0) return 0.0;

        return (float) ($task->max_points ?? 0) * $this->correctPairs($task, $studentSentence) / $totalPairs;
    }

    public function percentage(AssessmentTask $task, string $studentSentence): float
    {
        $totalPairs = $this->totalPairs($task);
        if ($totalPairs === 0) return 0.0;

        return $this->correctPairs($task, $studentSentence) / $totalPairs * 100;
    }

    private function totalPairs(AssessmentTask $task): int
    {
        $count = count($this->correctWords($task));

        return (int) ($count * ($count - 1) / 2);
    }

    private function correctPairs(AssessmentTask $task, string $studentSentence): int
    {
        $correctWords = $this->correctWords($task);
        $allowedWords = array_map(
            fn (string $word): string => $this->wordOrder->normalize($word),
            $this->wordOrder->words((string) ($task->content['words'] ?? '')),
        );
        $studentWords = array_values(array_filter(
            $this->tokens($studentSentence),
            fn (string $word): bool => in_array($word, $allowedWords, true),
        ));
        $available = $correctWords;
        $studentOrder = [];

        foreach ($studentWords as $word) {
            $index = array_search($word, $available, true);
            if ($index === false) continue;
            $studentOrder[$index] = count($studentOrder) + 1;
            unset($available[$index]);
        }

        $correctPairs = 0;
        foreach ($correctWords as $left => $_word) {
            foreach (array_keys(array_slice($correctWords, $left + 1, null, true)) as $right) {
                if (isset($studentOrder[$left], $studentOrder[$right]) && $studentOrder[$left] < $studentOrder[$right]) {
                    $correctPairs++;
                }
            }
        }

        return $correctPairs;
    }

    /** @return list<string> */
    private function correctWords(AssessmentTask $task): array
    {
        return $this->tokens((string) ($task->solution ?? ''));
    }

    /** @return list<string> */
    private function tokens(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]+(?:[\x{2019}\'-][\p{L}\p{N}]+)*/u', mb_strtolower($text, 'UTF-8'), $matches);

        return array_values($matches[0] ?? []);
    }
}
