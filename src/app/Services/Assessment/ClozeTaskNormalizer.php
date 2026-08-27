<?php

namespace App\Services\Assessment;

use InvalidArgumentException;

final class ClozeTaskNormalizer
{
    public function __construct(private readonly ClozeTextParser $parser) {}

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $previousContent
     * @return array{content: array<string, mixed>, expectations: list<array{subtask_key: string, text: string, points: int, repetitions: int}>}
     */
    public function normalize(array $content, array $previousContent = []): array
    {
        $parsed = $this->parser->parse(
            (string) ($content['prompt'] ?? ''),
            is_array($previousContent['blanks'] ?? null) ? $previousContent['blanks'] : [],
        );

        $normalized = [
            'prompt' => $parsed['prompt'],
            'show_solutions' => (bool) ($content['show_solutions'] ?? false),
            'lineated' => (bool) ($content['lineated'] ?? false),
            'split_blank_words' => (bool) ($content['split_blank_words'] ?? false),
            'blanks' => $parsed['blanks'],
        ];
        $submittedPoints = collect($content['blanks'] ?? [])
            ->filter(fn (mixed $blank): bool => is_array($blank))
            ->keyBy(fn (array $blank): string => (string) ($blank['id'] ?? ''));
        $normalized['blanks'] = collect($parsed['blanks'])->map(function (array $blank) use ($submittedPoints): array {
            $submitted = $submittedPoints->get($blank['id']);
            $points = is_array($submitted) ? (int) ($submitted['points'] ?? $blank['points']) : $blank['points'];

            if ($points < 1 || $points > 10000) {
                throw new InvalidArgumentException('Die Punktzahl jeder Lücke muss zwischen 1 und 10000 liegen.');
            }

            return [...$blank, 'points' => $points];
        })->all();

        return [
            'content' => $normalized,
            'expectations' => collect($normalized['blanks'])->map(fn (array $blank): array => [
                'subtask_key' => $blank['id'],
                'text' => 'Du hast korrekt ausgefüllt: '.$blank['solution'],
                'points' => $blank['points'],
                'repetitions' => 1,
            ])->all(),
        ];
    }
}
