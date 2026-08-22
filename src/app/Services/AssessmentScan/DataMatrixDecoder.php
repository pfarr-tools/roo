<?php

namespace App\Services\AssessmentScan;

interface DataMatrixDecoder
{
    /** @return iterable<array{payload:string, y_px:float}> */
    public function decode(string $imagePath): iterable;
}
