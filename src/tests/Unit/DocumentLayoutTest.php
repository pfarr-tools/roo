<?php

use App\Documents\AssessmentDocument;
use App\Documents\AssessmentResultDocument;
use App\Documents\DocumentLayout;
use App\Documents\DocumentLayoutProfile;
use App\Documents\ParentLetterDocument;

it('provides the requested German labels and typography for each document layout', function () {
    expect(DocumentLayout::PRIMARY_SCHOOL_LOWER_SECONDARY->label())->toBe('Grundschule, Unterstufe')
        ->and(DocumentLayout::SECONDARY->label())->toBe('Sekundarstufe')
        ->and(DocumentLayoutProfile::for(DocumentLayout::SECONDARY)->fontFamily)->toBe('Atkinson Hyperlegible Next')
        ->and(DocumentLayoutProfile::for(DocumentLayout::SECONDARY)->showAssessmentModuleBox)->toBeFalse();
});

it('shares one layout-bearing document abstraction across document families', function () {
    $layout = DocumentLayout::SECONDARY;

    expect(new AssessmentDocument('LSE', [], layout: $layout)->layout)->toBe($layout)
        ->and(new AssessmentResultDocument('Ergebnis', [], layout: $layout)->layout)->toBe($layout)
        ->and(new ParentLetterDocument('Brief', '7a', 'Schule', 'Lehrkraft', '', [], [], [], layout: $layout)->layout)->toBe($layout);
});

it('uses the secondary layout sizes and marker positions', function () {
    $profile = DocumentLayoutProfile::for(DocumentLayout::SECONDARY);

    expect($profile->bodyFontSize)->toBe(10)
        ->and($profile->headingFontSize)->toBe(13)
        ->and($profile->taskMarkerOffsetCm)->toBe(0.3)
        ->and($profile->pageMarkerOffsetCm)->toBe(0.3);
});
