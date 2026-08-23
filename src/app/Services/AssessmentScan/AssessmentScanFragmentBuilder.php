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
}
