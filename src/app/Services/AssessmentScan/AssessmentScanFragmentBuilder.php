<?php

namespace App\Services\AssessmentScan;

use Illuminate\Support\Facades\Storage;

final class AssessmentScanFragmentBuilder
{
    public function __construct(private readonly AssessmentScanSessionStore $sessions) {}

    /** @return list<string> */
    public function build(string $sessionId): array
    {
        $fragmentIds = [];
        $booklet = 0;
        foreach ($this->sessions->pages($sessionId) as $page) {
            $markers = $page['markers'];
            foreach ($markers as $marker) {
                if ($marker['kind'] === 'PAGE') {
                    $booklet++;
                }
            }
            $image = imagecreatefrompng(Storage::disk('temporary')->path($page['path']));
            if ($image === false) {
                continue;
            }
            $open = [];
            foreach ($markers as $marker) {
                if ($marker['kind'] === 'START') {
                    $open[$marker['task_id']] = $marker;

                    continue;
                }
                if ($marker['kind'] !== 'END' || ! isset($open[$marker['task_id']])) {
                    continue;
                }
                $start = $open[$marker['task_id']];
                $y = max(0, (int) round($start['y_px']));
                $endY = min(imagesy($image), (int) round($marker['y_px']));
                if ($endY > $y) {
                    $crop = imagecrop($image, ['x' => 0, 'y' => $y, 'width' => imagesx($image), 'height' => $endY - $y]);
                    if ($crop !== false) {
                        ob_start();
                        imagepng($crop);
                        $contents = ob_get_clean();
                        imagedestroy($crop);
                        $stored = $this->sessions->storeGeneratedFragment($sessionId, [
                            'page' => $page['page'],
                            'booklet' => max(1, $booklet),
                            'task_id' => $marker['task_id'],
                            'start_y_cm' => $start['y_cm'],
                            'end_y_cm' => $marker['y_cm'],
                        ], $contents);
                        $fragmentIds[] = $stored['fragment_id'];
                    }
                }
                unset($open[$marker['task_id']]);
            }
            imagedestroy($image);
        }

        return $fragmentIds;
    }

    /**
     * @return list<array{start_page:int,fragments:list<array{task_id:string,page:int,start_y_cm:float,end_y_cm:float}>}>
     */
    public function booklets(string $sessionId): array
    {
        $booklets = [];
        $currentBooklet = null;
        $openTasks = [];
        $pages = $this->sessions->pages($sessionId);
        usort($pages, fn (array $left, array $right): int => $left['page'] <=> $right['page']);

        foreach ($pages as $page) {
            $markers = $page['markers'];
            usort($markers, fn (array $left, array $right): int => $left['y_px'] <=> $right['y_px']);
            foreach ($markers as $marker) {
                if ($marker['kind'] === 'PAGE') {
                    $booklets[] = ['start_page' => $page['page'], 'fragments' => []];
                    $currentBooklet = array_key_last($booklets);
                    $openTasks = [];

                    continue;
                }
                if ($currentBooklet === null) {
                    continue;
                }
                if ($marker['kind'] === 'START') {
                    $openTasks[$marker['task_id']] = ['page' => $page['page'], 'marker' => $marker];

                    continue;
                }
                if ($marker['kind'] !== 'END' || ! isset($openTasks[$marker['task_id']])) {
                    continue;
                }

                $start = $openTasks[$marker['task_id']];
                if ($start['page'] === $page['page'] && (float) $marker['y_cm'] > (float) $start['marker']['y_cm']) {
                    $booklets[$currentBooklet]['fragments'][] = [
                        'task_id' => (string) $marker['task_id'],
                        'page' => $page['page'],
                        'start_y_cm' => (float) $start['marker']['y_cm'],
                        'end_y_cm' => (float) $marker['y_cm'],
                    ];
                }
                unset($openTasks[$marker['task_id']]);
            }
        }

        return $booklets;
    }
}
