<?php

namespace App\Services\AssessmentScan;

final class AssessmentScanGrouper
{
    /** @param iterable<RooMarker> $markers */
    public function group(iterable $markers): AssessmentScanResult
    {
        $booklets = [];
        $warnings = [];
        $currentBooklet = null;
        $openTasks = [];

        foreach ($markers as $marker) {
            if ($marker->kind === 'PAGE') {
                $booklets[] = [
                    'number' => count($booklets) + 1,
                    'start_page' => $marker->page,
                    'markers' => [$marker->toArray()],
                ];
                $currentBooklet = array_key_last($booklets);
                $openTasks = [];

                continue;
            }

            if ($currentBooklet === null) {
                $warnings[] = "Marker vor dem ersten Booklet auf Seite {$marker->page}.";

                continue;
            }

            $booklets[$currentBooklet]['markers'][] = $marker->toArray();
            if ($marker->kind === 'START') {
                $openTasks[$marker->taskId] = true;
            } elseif (! isset($openTasks[$marker->taskId])) {
                $warnings[] = "Aufgabenende ohne Aufgabenbeginn für {$marker->taskId} auf Seite {$marker->page}.";
            } else {
                unset($openTasks[$marker->taskId]);
            }
        }

        foreach (array_keys($openTasks) as $taskId) {
            $warnings[] = "Aufgabenbeginn ohne Aufgabenende für {$taskId}.";
        }

        return new AssessmentScanResult($booklets, $warnings);
    }
}
