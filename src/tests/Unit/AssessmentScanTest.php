<?php

use App\Services\AssessmentScan\AssessmentScanGrouper;
use App\Services\AssessmentScan\RooMarkerParser;
use Tests\TestCase;

uses(TestCase::class);

it('parses page and task markers and ignores non-roo payloads', function () {
    $parser = new RooMarkerParser;

    expect($parser->parse('ROO1|A=42|L=M|K=PAGE', 1, 2.5)?->toArray())
        ->toMatchArray(['page' => 1, 'y_cm' => 2.5, 'kind' => 'PAGE', 'assessment_id' => '42', 'task_id' => null, 'level' => 'M'])
        ->and($parser->parse('ROO1|T=17|K=START', 2, 8.25)?->toArray())
        ->toMatchArray(['page' => 2, 'y_cm' => 8.25, 'kind' => 'START', 'task_id' => '17'])
        ->and($parser->parse('not-a-roo-code', 1, 1.0))->toBeNull();
});

it('starts anonymous booklets at page markers and warns about markers before a booklet', function () {
    $parser = new RooMarkerParser;
    $grouper = new AssessmentScanGrouper;

    $markers = collect([
        $parser->parse('ROO1|T=9|K=START', 1, 10),
        $parser->parse('ROO1|A=42|L=M|K=PAGE', 1, 2),
        $parser->parse('ROO1|T=9|K=END', 1, 20),
        $parser->parse('ROO1|A=42|L=M|K=PAGE', 3, 2),
    ])->filter();

    $result = $grouper->group($markers);

    expect($result->booklets[0]['number'])->toBe(1)
        ->and($result->booklets[0]['start_page'])->toBe(1)
        ->and($result->booklets[0]['markers'][0]['kind'])->toBe('PAGE')
        ->and($result->booklets[0]['markers'][1]['task_id'])->toBe('9')
        ->and($result->booklets[1]['number'])->toBe(2)
        ->and($result->booklets[1]['start_page'])->toBe(3)
        ->and($result->warnings)->toContain('Marker vor dem ersten Booklet auf Seite 1.');
});
