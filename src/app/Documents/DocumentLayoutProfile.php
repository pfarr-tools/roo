<?php

namespace App\Documents;

final class DocumentLayoutProfile
{
    private function __construct(
        public readonly string $fontFamily,
        public readonly string $headingFontFamily,
        public readonly int $bodyFontSize,
        public readonly int $headingFontSize,
        public readonly int $headerFontSize,
        public readonly int $smallFontSize,
        public readonly int $solutionFontSize,
        public readonly bool $showAssessmentModuleBox,
        public readonly bool $pageFrame,
        public readonly bool $headerBand,
        public readonly float $taskMarkerOffsetCm,
        public readonly float $pageMarkerOffsetCm,
    ) {}

    public static function for(DocumentLayout $layout): self
    {
        return match ($layout) {
            DocumentLayout::PRIMARY_SCHOOL_LOWER_SECONDARY => new self('Atkinson Hyperlegible Next', 'Comic Neue', 14, 14, 24, 9, 14, false, true, false, -1.9, 0.5),
            DocumentLayout::SECONDARY => new self('Atkinson Hyperlegible Next', 'Atkinson Hyperlegible Next', 10, 13, 13, 8, 10, false, false, true, 0.3, 0.3),
        };
    }
}
