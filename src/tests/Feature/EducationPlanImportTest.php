<?php

use App\Actions\EducationPlans\ImportEducationPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('imports the structured education plan hierarchy and preserves incomplete conversions', function () {
    $result = app(ImportEducationPlan::class)->execute(base_path('../data/bildungsplaene/plans/DE_BW_BILDUNGSPLAENE_GEN2X_BPBW_ALLG_GYM_REV_V3.0.json'));

    expect($result['version']->is_complete)->toBeFalse();
    $this->assertDatabaseHas('education_plans', ['external_identifier' => 'DE_BW_BILDUNGSPLAENE_GEN2X_BPBW_ALLG_GYM_REV(V3.0)']);
    $this->assertDatabaseHas('education_plan_stages', ['external_identifier' => '3.1', 'label' => 'Klassen 5/6']);
    $this->assertDatabaseHas('education_plan_school_types', ['label' => 'Gymnasium']);
    $this->assertDatabaseHas('education_plan_competence_areas', ['kind' => 'process', 'external_identifier' => '2.1']);
    $this->assertDatabaseHas('education_plan_import_runs', ['status' => 'completed', 'education_plan_version_id' => $result['version']->id]);
    expect($result['version']->raw_payload['metadata']['conversion']['status'])->toBe('structure_only');
});

it('imports differentiated variants and raw relations from a complete plan', function () {
    $result = app(ImportEducationPlan::class)->execute(base_path('../data/bildungsplaene/plans/BP2016BW_ALLG_SEK1_REV.json'));

    expect($result['version']->is_complete)->toBeTrue();
    $this->assertDatabaseHas('education_plan_levels', ['external_identifier' => 'G']);
    $this->assertDatabaseHas('education_plan_competence_variants', ['education_plan_level_id' => DB::table('education_plan_levels')->where('external_identifier', 'G')->value('id')]);
    expect(DB::table('education_plan_competence_relations')->count())->toBeGreaterThan(0);
});
