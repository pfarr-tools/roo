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

it('removes the page frame and header before cropping a task fragment', function () {
    $page = imagecreatetruecolor(2480, 3508);
    imagefill($page, 0, 0, imagecolorallocate($page, 255, 255, 255));
    imagefilledrectangle($page, 0, 0, 2479, 199, imagecolorallocate($page, 220, 20, 60));
    imagefilledrectangle($page, 236, 1000, 240, 1500, imagecolorallocate($page, 0, 0, 0));
    imagefilledrectangle($page, 2360, 1000, 2364, 1500, imagecolorallocate($page, 0, 0, 0));
    imagefilledrectangle($page, 300, 1180, 2300, 1300, imagecolorallocate($page, 20, 80, 220));
    ob_start();
    imagepng($page);
    $contents = ob_get_clean();
    imagedestroy($page);

    $image = imagecreatefromstring(app(AssessmentTemplateCropper::class)->taskFragment([$contents], 8, 12));

    expect(imagesx($image))->toBe(2079)
        ->and(imagesy($image))->toBeLessThan(900)
        ->and(imagecolorsforindex($image, imagecolorat($image, 800, 50))['blue'])->toBe(220)
        ->and(imagecolorsforindex($image, imagecolorat($image, 0, 50))['red'])->toBe(255)
        ->and(imagecolorsforindex($image, imagecolorat($image, 10, 10))['red'])->toBe(255);

    imagedestroy($image);
});

it('trims whitespace on each page piece before stitching a cross-page fragment', function () {
    $pages = [];
    foreach ([['top' => 1400, 'bottom' => 1700], ['top' => 1500, 'bottom' => 1800]] as $marks) {
        $page = imagecreatetruecolor(2480, 3508);
        imagefill($page, 0, 0, imagecolorallocate($page, 255, 255, 255));
        imagefilledrectangle($page, 800, $marks['top'], 1600, $marks['bottom'], imagecolorallocate($page, 20, 80, 220));
        imagefilledrectangle($page, 0, 3300, 2479, 3310, imagecolorallocate($page, 220, 20, 60));
        ob_start();
        imagepng($page);
        $pages[] = ob_get_clean();
        imagedestroy($page);
    }

    $image = imagecreatefromstring(app(AssessmentTemplateCropper::class)->taskFragment($pages, 8, 20));

    expect(imagesy($image))->toBeLessThan(1000)
        ->and(imagecolorsforindex($image, imagecolorat($image, 10, 0))['red'])->toBe(255)
        ->and(imagecolorsforindex($image, imagecolorat($image, 10, imagesy($image) - 1))['red'])->toBe(255);

    imagedestroy($image);
});
