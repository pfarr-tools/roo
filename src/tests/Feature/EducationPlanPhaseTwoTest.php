<?php

use App\Models\EducationPlan;
use App\Models\EducationPlanCompetenceArea;
use App\Models\EducationPlanCompetency;
use App\Models\EducationPlanVersion;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function phaseTwoPlanContext(): array
{
    $organization = Organization::create(['name' => 'Test Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $plan = EducationPlan::create(['external_identifier' => 'PLAN', 'subject' => 'Evangelische Religionslehre', 'title' => 'Bildungsplan']);

    return [$user, $plan];
}

it('searches education plans by competency text', function () {
    [$user, $plan] = phaseTwoPlanContext();
    $version = EducationPlanVersion::create(['education_plan_id' => $plan->id, 'external_identifier' => '2026', 'schema_version' => '2.0.0', 'title' => 'Fassung 2026', 'raw_payload' => []]);
    $area = EducationPlanCompetenceArea::create(['education_plan_version_id' => $version->id, 'kind' => 'process', 'external_identifier' => '2.1', 'title' => 'Deuten', 'position' => 0]);
    EducationPlanCompetency::create(['education_plan_competence_area_id' => $area->id, 'external_identifier' => '2.1.1', 'text' => 'Menschenwürde wahrnehmen', 'position' => 0]);

    $this->actingAs($user)->get('/bildungsplaene?q=Menschenwürde')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->has('educationPlans', 1)->where('search', 'Menschenwürde'));
});

it('can deactivate a competence without changing the whole plan', function () {
    [$user, $plan] = phaseTwoPlanContext();
    $version = EducationPlanVersion::create(['education_plan_id' => $plan->id, 'external_identifier' => '2026', 'schema_version' => '2.0.0', 'title' => 'Fassung 2026', 'raw_payload' => []]);
    $area = EducationPlanCompetenceArea::create(['education_plan_version_id' => $version->id, 'kind' => 'process', 'external_identifier' => '2.1', 'title' => 'Deuten', 'position' => 0]);
    $competency = EducationPlanCompetency::create(['education_plan_competence_area_id' => $area->id, 'external_identifier' => '2.1.1', 'text' => 'Kompetenz', 'position' => 0]);

    $this->actingAs($user)->from('/bildungsplaene/'.$plan->id)->post('/bildungsplaene/'.$plan->id.'/kompetenzen/'.$competency->id.'/status', ['_token' => csrf_token(), 'is_active' => 0])->assertRedirect();
    expect($competency->refresh()->is_active)->toBeFalse();
});

it('compares competencies between two plan versions', function () {
    [$user, $plan] = phaseTwoPlanContext();
    $older = EducationPlanVersion::create(['education_plan_id' => $plan->id, 'external_identifier' => '2025', 'schema_version' => '2.0.0', 'title' => 'Fassung 2025', 'raw_payload' => []]);
    $newer = EducationPlanVersion::create(['education_plan_id' => $plan->id, 'external_identifier' => '2026', 'schema_version' => '2.0.0', 'title' => 'Fassung 2026', 'raw_payload' => []]);
    $oldArea = EducationPlanCompetenceArea::create(['education_plan_version_id' => $older->id, 'kind' => 'process', 'external_identifier' => '2.1', 'title' => 'Deuten', 'position' => 0]);
    $newArea = EducationPlanCompetenceArea::create(['education_plan_version_id' => $newer->id, 'kind' => 'process', 'external_identifier' => '2.1', 'title' => 'Deuten', 'position' => 0]);
    EducationPlanCompetency::create(['education_plan_competence_area_id' => $oldArea->id, 'external_identifier' => '2.1.1', 'text' => 'Alter Text', 'position' => 0]);
    EducationPlanCompetency::create(['education_plan_competence_area_id' => $newArea->id, 'external_identifier' => '2.1.1', 'text' => 'Neuer Text', 'position' => 0]);

    $this->actingAs($user)->get('/bildungsplaene/'.$plan->id.'?version='.$newer->id.'&compare='.$older->id)
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('comparisonVersion.external_identifier', '2025')->where('comparisonRows.0.status', 'changed'));
});
