<?php

namespace App\Services\AssessmentEvaluation;

final class SentenceBuilderWordOrder
{
    /** @param array<string, mixed> $content */
    /** @param array<string, mixed>|null $previousContent */
    public function apply(array $content, ?array $previousContent = null): array
    {
        $words = $this->words($content['words'] ?? '');
        $previous = collect($previousContent['shuffled_words'] ?? [])
            ->map(fn ($word): string => (string) $word)
            ->filter(fn (string $word): bool => in_array($this->normalize($word), array_map($this->normalize(...), $words), true))
            ->values()
            ->all();

        if ($previous === []) {
            $shuffled = $words;
            shuffle($shuffled);
        } else {
            $shuffled = $previous;
            $remaining = array_values(array_filter($words, fn (string $word): bool => ! in_array($this->normalize($word), array_map($this->normalize(...), $shuffled), true)));
            shuffle($remaining);
            $shuffled = [...$shuffled, ...$remaining];
        }

        $content['shuffled_words'] = $shuffled;

        return $content;
    }

    /** @return list<string> */
    public function words(string $words): array
    {
        preg_match_all('/[\p{L}\p{N}]+(?:[\x{2019}\'-][\p{L}\p{N}]+)*/u', $words, $matches);

        return array_values($matches[0] ?? []);
    }

    public function normalize(string $word): string
    {
        preg_match('/[\p{L}\p{N}]+(?:[\x{2019}\'-][\p{L}\p{N}]+)*/u', mb_strtolower(trim($word), 'UTF-8'), $matches);

        return $matches[0] ?? '';
    }
}
