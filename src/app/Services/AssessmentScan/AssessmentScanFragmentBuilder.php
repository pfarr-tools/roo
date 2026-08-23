<?php

namespace App\Services\AssessmentScan;

use App\Services\AssessmentEvaluation\AssessmentTemplateCropper;

final class AssessmentScanFragmentBuilder
{
    public function __construct(
        private readonly AssessmentScanSessionStore $sessions,
        private readonly AssessmentTemplateCropper $cropper,
    ) {}

    /** @return list<string> */
    public function build(string $sessionId): array
    {
        $fragmentIds = [];
        foreach ($this->booklets($sessionId) as $bookletNumber => $booklet) {
            foreach ($booklet['fragments'] as $fragment) {
                $contents = $this->cropper->taskFragment(
                    array_map(
                        fn (int $page): string => $this->sessions->pageContents($sessionId, $page),
                        range($fragment['page'], $fragment['end_page']),
                    ),
                    $fragment['start_y_cm'],
                    $fragment['end_y_cm'],
                );
                $stored = $this->sessions->storeGeneratedFragment($sessionId, [
                    'page' => $fragment['page'],
                    'end_page' => $fragment['end_page'],
                    'booklet' => $bookletNumber + 1,
                    'task_id' => $fragment['task_id'],
                    'start_y_cm' => $fragment['start_y_cm'],
                    'end_y_cm' => $fragment['end_y_cm'],
                ], $contents);
                $fragmentIds[] = $stored['fragment_id'];
            }
        }

        return $fragmentIds;
    }

    /**
     * @return list<array{start_page:int,fragments:list<array{task_id:string,page:int,end_page:int,start_y_cm:float,end_y_cm:float}>}>
     */
    public function booklets(string $sessionId): array
    {
        return $this->analysis($sessionId, null)['booklets'];
    }

    /**
     * @return array{booklets:list<array{start_page:int,fragments:list<array{task_id:string,page:int,end_page:int,start_y_cm:float,end_y_cm:float}>}>,warnings:list<string>}
     */
    public function analysis(string $sessionId, ?int $assessmentId): array
    {
        $booklets = [];
        $warnings = [];
        $currentBooklet = null;
        $openTasks = [];
        $pages = $this->sessions->pages($sessionId);
        usort($pages, fn (array $left, array $right): int => $left['page'] <=> $right['page']);

        foreach ($pages as $page) {
            $markers = $page['markers'];
            usort($markers, fn (array $left, array $right): int => $left['y_px'] <=> $right['y_px']);
            foreach ($markers as $marker) {
                if ($marker['kind'] === 'PAGE') {
                    foreach ($openTasks as $taskId => $start) {
                        $warnings[] = "Seite {$start['page']}: START-Marker für Aufgabe {$taskId} ohne END-Marker.";
                    }
                    if ($assessmentId !== null && (int) ($marker['assessment_id'] ?? 0) !== $assessmentId) {
                        $warnings[] = "Seite {$page['page']}: Der PAGE-Marker gehört zu einer anderen Lernstandserhebung und wurde ignoriert.";
                        $currentBooklet = null;
                        $openTasks = [];

                        continue;
                    }
                    $booklets[] = ['start_page' => $page['page'], 'fragments' => []];
                    $currentBooklet = array_key_last($booklets);
                    $openTasks = [];

                    continue;
                }
                if ($currentBooklet === null) {
                    $warnings[] = "Seite {$page['page']}: {$marker['kind']}-Marker für Aufgabe {$marker['task_id']} ohne passendes Booklet.";

                    continue;
                }
                if ($marker['kind'] === 'START') {
                    if (isset($openTasks[$marker['task_id']])) {
                        $previous = $openTasks[$marker['task_id']];
                        $warnings[] = "Seite {$previous['page']}: START-Marker für Aufgabe {$marker['task_id']} ohne END-Marker.";
                    }
                    $openTasks[$marker['task_id']] = ['page' => $page['page'], 'marker' => $marker];

                    continue;
                }
                if ($marker['kind'] !== 'END') {
                    continue;
                }
                if (! isset($openTasks[$marker['task_id']])) {
                    $warnings[] = "Seite {$page['page']}: END-Marker für Aufgabe {$marker['task_id']} ohne START-Marker.";

                    continue;
                }

                $start = $openTasks[$marker['task_id']];
                if ($start['page'] < $page['page'] || (float) $marker['y_cm'] > (float) $start['marker']['y_cm']) {
                    $booklets[$currentBooklet]['fragments'][] = [
                        'task_id' => (string) $marker['task_id'],
                        'page' => $start['page'],
                        'end_page' => $page['page'],
                        'start_y_cm' => (float) $start['marker']['y_cm'],
                        'end_y_cm' => (float) $marker['y_cm'],
                    ];
                } else {
                    $warnings[] = "Seite {$page['page']}: END-Marker für Aufgabe {$marker['task_id']} liegt vor dem START-Marker.";
                }
                unset($openTasks[$marker['task_id']]);
            }
        }

        foreach ($openTasks as $taskId => $start) {
            $warnings[] = "Seite {$start['page']}: START-Marker für Aufgabe {$taskId} ohne END-Marker.";
        }
        if ($booklets === []) {
            $warnings[] = 'Es wurden keine passenden Booklet-Marker für diese Lernstandserhebung erkannt.';
        }

        return ['booklets' => $booklets, 'warnings' => array_values(array_unique($warnings))];
    }
}
