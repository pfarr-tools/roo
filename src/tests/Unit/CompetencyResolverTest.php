<?php

use App\Models\EducationPlanCompetenceVariant;
use App\Models\EducationPlanCompetency;
use App\Services\CompetencyResolver;
use Tests\TestCase;

uses(TestCase::class);

it('liefert die vier Kompetenzformate mit korrekt formatierten Nummern', function () {
    $competency = new EducationPlanCompetency([
        'external_identifier' => '3.2.1.4',
        'text' => 'die Sprache (im Alltag) wahrnehmen und (deuten)',
    ]);
    $competency->setRelation('variants', collect());

    $resolver = new CompetencyResolver;

    expect($resolver->number($competency))->toBe('3.2.1 (4)')
        ->and($resolver->numberAndText($competency))->toBe('3.2.1 (4) – die Sprache (im Alltag) wahrnehmen und (deuten)')
        ->and($resolver->textOnly($competency))->toBe('die Sprache (im Alltag) wahrnehmen und (deuten)')
        ->and($resolver->duKannst($competency))->toBe('Du kannst die Sprache wahrnehmen und (3.2.1 (4))')
        ->and($resolver->duKannst($competency, false, false))->toBe('Du kannst die Sprache (im Alltag) wahrnehmen und (deuten)');
});

it('verwendet Varianten als Kompetenztext', function () {
    $competency = new EducationPlanCompetency(['external_identifier' => '3.2.1.4', 'text' => null]);
    $competency->setRelation('variants', collect([
        new EducationPlanCompetenceVariant(['text' => 'wahrnehmen und deuten']),
    ]));

    expect((new CompetencyResolver)->duKannst($competency))->toBe('Du kannst wahrnehmen und deuten (3.2.1 (4))');
});
