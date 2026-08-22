<?php

namespace App\Services\AssessmentScan;

final class RooMarkerParser
{
    public function parse(string $payload, int $page, float $yCm): ?RooMarker
    {
        $parts = explode('|', $payload);
        if (($parts[0] ?? null) !== 'ROO1') {
            return null;
        }

        $values = [];
        foreach (array_slice($parts, 1) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, null);
            if ($key !== null && $value !== null) {
                $values[$key] = $value;
            }
        }

        $kind = $values['K'] ?? null;
        if (! in_array($kind, ['PAGE', 'START', 'END'], true)) {
            return null;
        }
        if (($kind === 'PAGE' && blank($values['A'] ?? null)) || ($kind !== 'PAGE' && blank($values['T'] ?? null))) {
            return null;
        }

        return new RooMarker(
            kind: $kind,
            page: $page,
            yCm: $yCm,
            assessmentId: $values['A'] ?? null,
            taskId: $values['T'] ?? null,
            level: $values['L'] ?? null,
            payload: $payload,
        );
    }
}
