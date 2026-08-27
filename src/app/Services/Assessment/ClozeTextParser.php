<?php

namespace App\Services\Assessment;

use InvalidArgumentException;

final class ClozeTextParser
{
    /**
     * @param  list<array{id: string, solution: string, points: int}>  $previousBlanks
     * @return array{prompt: string, blanks: list<array{id: string, solution: string, points: int}>, fragments: list<array<string, mixed>>}
     */
    public function parse(string $prompt, array $previousBlanks = []): array
    {
        $blanks = [];
        $fragments = [];
        $offset = 0;
        $length = mb_strlen($prompt);

        while ($offset < $length) {
            $open = mb_strpos($prompt, '[', $offset);
            $close = mb_strpos($prompt, ']', $offset);

            if ($close !== false && ($open === false || $close < $open)) {
                throw $this->invalid('Eine schließende Klammer muss zu einer Lücke gehören.');
            }

            if ($open === false) {
                if ($offset < $length) {
                    $fragments[] = ['type' => 'text', 'text' => mb_substr($prompt, $offset)];
                }
                break;
            }

            if ($open > $offset) {
                $fragments[] = ['type' => 'text', 'text' => mb_substr($prompt, $offset, $open - $offset)];
            }

            $close = mb_strpos($prompt, ']', $open + 1);
            $nested = mb_strpos($prompt, '[', $open + 1);
            if ($close === false || ($nested !== false && $nested < $close)) {
                throw $this->invalid('Jede Lücke muss mit einer schließenden Klammer beendet werden.');
            }

            $solution = trim(mb_substr($prompt, $open + 1, $close - $open - 1));
            if ($solution === '') {
                throw $this->invalid('Lücken dürfen nicht leer sein.');
            }

            $id = 'blank-'.(count($blanks) + 1);
            $previous = collect($previousBlanks)->firstWhere('id', $id);
            $blanks[] = [
                'id' => $id,
                'solution' => $solution,
                'points' => max(1, (int) ($previous['points'] ?? 1)),
            ];
            $fragments[] = [
                'type' => 'blank',
                'id' => $id,
                'solution' => $solution,
                'words' => preg_split('/\s+/u', $solution, -1, PREG_SPLIT_NO_EMPTY) ?: [$solution],
            ];
            $offset = $close + 1;
        }

        if ($blanks === []) {
            throw $this->invalid('Der Lückentext muss mindestens eine Lücke enthalten.');
        }

        return ['prompt' => $prompt, 'blanks' => $blanks, 'fragments' => $fragments];
    }

    private function invalid(string $message): InvalidArgumentException
    {
        return new InvalidArgumentException($message);
    }
}
