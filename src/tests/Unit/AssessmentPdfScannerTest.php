<?php

use App\Services\AssessmentScan\AssessmentPdfScanner;
use App\Services\AssessmentScan\DataMatrixDecoder;
use App\Services\AssessmentScan\DmtxReadDecoder;
use App\Services\AssessmentScan\PdfPageRenderer;
use Tests\TestCase;

uses(TestCase::class);

final class FakeAssessmentDataMatrixDecoder implements DataMatrixDecoder
{
    public function __construct(private array $markers) {}

    public function decode(string $imagePath): iterable
    {
        yield from $this->markers;
    }
}

final class FakeAssessmentPdfPageRenderer implements PdfPageRenderer
{
    public function render(string $pdfPath): iterable
    {
        yield ['page' => 1, 'image_path' => 'page-1.png'];
    }
}

it('converts decoded marker coordinates from 300 dpi pixels to centimetres', function () {
    $scanner = new AssessmentPdfScanner(
        decoder: new FakeAssessmentDataMatrixDecoder([
            ['payload' => 'ROO1|A=42|L=M|K=PAGE', 'y_px' => 295.275],
            ['payload' => 'ROO1|T=7|K=START', 'y_px' => 1181.1],
        ]),
        pageRenderer: new FakeAssessmentPdfPageRenderer,
    );

    $markers = $scanner->scan('/tmp/input.pdf')->booklets[0]['markers'];

    expect($markers[0]['y_cm'])->toBe(2.5)
        ->and($markers[1]['y_cm'])->toBe(10.0)
        ->and($markers[1]['task_id'])->toBe('7');
});

it('parses dmtxread corner prefixes and uses the top-most y coordinate', function () {
    $decoder = new DmtxReadDecoder;

    expect($decoder->parseOutput('10,100:30,100:30,120:10,120:ROO1|A=42|L=M|K=PAGE')[0])
        ->toMatchArray(['payload' => 'ROO1|A=42|L=M|K=PAGE', 'y_px' => 100.0]);
});

it('limits the decoder scan time for each rendered page', function () {
    $decoder = new DmtxReadDecoder;

    $arguments = $decoder->commandArguments('/tmp/page-1.png');

    expect($arguments)
        ->toContain('-m', '10000')
        ->and($arguments[array_key_last($arguments)])->toBe('/tmp/page-1.png');
});
