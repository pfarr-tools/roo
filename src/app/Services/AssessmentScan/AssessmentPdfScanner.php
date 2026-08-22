<?php

namespace App\Services\AssessmentScan;

final class AssessmentPdfScanner
{
    public function __construct(
        private readonly DataMatrixDecoder $decoder,
        private readonly PdfPageRenderer $pageRenderer,
        private readonly RooMarkerParser $parser = new RooMarkerParser,
        private readonly AssessmentScanGrouper $grouper = new AssessmentScanGrouper,
    ) {}

    public function scan(string $pdfPath): AssessmentScanResult
    {
        $markers = [];

        foreach ($this->pageRenderer->render($pdfPath) as $page) {
            foreach ($this->decoder->decode($page['image_path']) as $decoded) {
                $marker = $this->parser->parse(
                    payload: $decoded['payload'],
                    page: $page['page'],
                    yCm: round(((float) $decoded['y_px'] / 300) * 2.54, 2),
                );
                if ($marker !== null) {
                    $markers[] = $marker;
                }
            }
        }

        usort($markers, static fn (RooMarker $left, RooMarker $right): int => [$left->page, $left->yCm] <=> [$right->page, $right->yCm]);

        return $this->grouper->group($markers);
    }
}
