<?php

use App\Models\EducationPlan;
use App\Models\EducationPlanCompetenceArea;
use App\Models\EducationPlanCompetency;
use App\Models\EducationPlanStage;
use App\Models\EducationPlanVersion;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the selected education plan version with its hierarchy', function () {
    $organization = Organization::create(['name' => 'Test Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $plan = EducationPlan::create(['external_identifier' => 'PLAN', 'subject' => 'Evangelische Religionslehre', 'title' => 'Bildungsplan']);
    $version = EducationPlanVersion::create(['education_plan_id' => $plan->id, 'external_identifier' => '2026', 'schema_version' => '2.0.0', 'title' => 'Fassung 2026', 'is_complete' => true, 'raw_payload' => []]);
    $stage = EducationPlanStage::create(['education_plan_version_id' => $version->id, 'external_identifier' => '3.1', 'label' => 'Klassen 5/6', 'position' => 0]);
    $area = EducationPlanCompetenceArea::create(['education_plan_version_id' => $version->id, 'education_plan_stage_id' => $stage->id, 'kind' => 'content', 'external_identifier' => '3.1.1', 'title' => 'Mensch', 'position' => 0]);
    EducationPlanCompetency::create(['education_plan_competence_area_id' => $area->id, 'external_identifier' => '3.1.1.1', 'number' => 1, 'text' => 'Kompetenztext', 'position' => 0]);

    $this->actingAs($user)->get('/bildungsplaene/'.$plan->id)
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('selectedVersion.external_identifier', '2026')
            ->where('selectedVersion.stages.0.competence_areas.0.competencies.0.text', 'Kompetenztext'));
});
