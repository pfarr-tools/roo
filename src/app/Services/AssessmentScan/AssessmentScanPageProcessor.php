<?php

namespace App\Services\AssessmentScan;

use Illuminate\Support\Facades\Storage;

final class AssessmentScanPageProcessor
{
    public function __construct(
        private readonly DataMatrixDecoder $decoder,
        private readonly RooMarkerParser $parser = new RooMarkerParser,
    ) {}

    /** @return list<array<string,mixed>> */
    public function markers(string $path, int $page): array
    {
        $markers = [];
        foreach ($this->decoder->decode(Storage::disk('temporary')->path($path)) as $decoded) {
            $marker = $this->parser->parse($decoded['payload'], $page, round(((float) $decoded['y_px'] / 300) * 2.54, 2));
            if ($marker === null) {
                continue;
            }
            $markers[] = array_merge($marker->toArray(), [
                'x_px' => $decoded['x_px'],
                'y_px' => $decoded['y_px'],
                'width_px' => $decoded['width_px'],
                'height_px' => $decoded['height_px'],
            ]);
        }
        usort($markers, fn (array $left, array $right): int => $left['y_px'] <=> $right['y_px']);

        return $markers;
    }
}
