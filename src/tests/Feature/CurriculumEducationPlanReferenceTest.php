<?php

use App\Models\Curriculum;
use App\Models\CurriculumTopic;
use App\Models\CurriculumTopicEducationPlanReference;
use App\Models\CurriculumVersion;
use App\Models\EducationPlan;
use App\Models\EducationPlanCompetenceArea;
use App\Models\EducationPlanCompetency;
use App\Models\EducationPlanVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('loads official competency data through a curriculum education plan reference', function () {
    $plan = EducationPlan::create(['title' => 'Bildungsplan', 'subject' => 'Religion', 'external_identifier' => 'BP-TEST']);
    $version = EducationPlanVersion::create(['education_plan_id' => $plan->id, 'external_identifier' => 'v1', 'schema_version' => '1.0', 'title' => 'Version 1', 'raw_payload' => []]);
    $area = EducationPlanCompetenceArea::create([
        'education_plan_version_id' => $version->id,
        'kind' => 'content',
        'external_identifier' => '3.1',
        'title' => 'Mensch',
        'position' => 1,
    ]);
    $competency = EducationPlanCompetency::create([
        'education_plan_competence_area_id' => $area->id,
        'external_identifier' => '3.1.1.1',
        'number' => 1,
        'text' => 'Der offizielle Kompetenztext',
        'position' => 1,
        'is_active' => true,
    ]);
    $curriculum = Curriculum::create(['title' => 'Curriculum']);
    $curriculumVersion = CurriculumVersion::create(['curriculum_id' => $curriculum->id, 'external_identifier' => 'v1']);
    $topic = CurriculumTopic::create([
        'curriculum_version_id' => $curriculumVersion->id,
        'title' => 'Thema',
        'position' => 1,
    ]);

    $reference = CurriculumTopicEducationPlanReference::create([
        'curriculum_topic_id' => $topic->id,
        'education_plan_competency_id' => $competency->id,
        'denomination' => 'evangelical',
        'competency_kind' => 'content',
        'position' => 1,
    ]);

    expect($topic->educationPlanReferences()->first()->educationPlanCompetency->text)
        ->toBe('Der offizielle Kompetenztext')
        ->and($reference->getAttributes())->not->toHaveKey('text')
        ->and($reference->getAttributes())->not->toHaveKey('raw_text')
        ->and($reference->getAttributes())->not->toHaveKey('display');
});
