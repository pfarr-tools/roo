<?php

use App\Services\AssessmentEvaluation\AssessmentTemplateCropper;
use Tests\TestCase;

uses(TestCase::class);

it('crops the fixed name rectangle from a 300 dpi assessment page', function () {
    $page = imagecreatetruecolor(2480, 3508);
    imagefill($page, 0, 0, imagecolorallocate($page, 255, 255, 255));
    imagefilledrectangle($page, 1675, 118, 2330, 295, imagecolorallocate($page, 220, 20, 60));
    ob_start();
    imagepng($page);
    $contents = ob_get_clean();
    imagedestroy($page);

    $crop = app(AssessmentTemplateCropper::class)->nameFragment($contents);
    $image = imagecreatefromstring($crop);
    $pixel = imagecolorsforindex($image, imagecolorat($image, 20, 20));

    expect(imagesx($image))->toBe(661)
        ->and(imagesy($image))->toBe(189)
        ->and($pixel['red'])->toBe(220)
        ->and($pixel['green'])->toBe(20)
        ->and($pixel['blue'])->toBe(60);

    imagedestroy($image);
});
