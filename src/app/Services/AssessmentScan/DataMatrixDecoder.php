<?php

namespace App\Services\AssessmentScan;

interface DataMatrixDecoder
{
    /** @return iterable<array{payload:string, x_px:float, y_px:float, width_px:float, height_px:float}> */
    public function decode(string $imagePath): iterable;
}
