<?php

namespace App\Services\AssessmentScan;

interface PdfPageRenderer
{
    /** @return iterable<array{page:int, image_path:string}> */
    public function render(string $pdfPath): iterable;
}
