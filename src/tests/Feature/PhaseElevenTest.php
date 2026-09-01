<?php

use App\Models\Organization;
use App\Models\CustomProcessCompetence;
use App\Models\ReportPeriod;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEvaluationObservationScale;
use App\Models\TeachingGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
it('legt einen Bewertungszeitraum an und trennt den Entwurf vom Bestätigungsstatus', function () {
    $org = Organization::create(['name' => 'Evaluation']);
    $user = User::factory()->create(['organization_id' => $org->id]);
    $school = School::create(['organization_id' => $org->id, 'name' => 'Schule']);
    $year = SchoolYear::create(['organization_id' => $org->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $org->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4a']);
    $this->actingAs($user)->post("/unterrichtsgruppen/{$group->id}/bewertungen/zeiträume", ['label' => '1. Halbjahr', 'starts_on' => '2026-09-01', 'ends_on' => '2027-02-01'])->assertRedirect();
    expect(ReportPeriod::first()->label)->toBe('1. Halbjahr');
});

it('uses live school scale definitions in drafts and snapshots them on confirmation', function () {
    $org = Organization::create(['name' => 'Skalenbewertung']);
    $user = User::factory()->create(['organization_id' => $org->id]);
    $school = School::create(['organization_id' => $org->id, 'name' => 'Skalenschule']);
    $year = SchoolYear::create(['organization_id' => $org->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $org->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4a', 'grading_model' => 'observation_scales']);
    $student = Student::create(['organization_id' => $org->id, 'school_id' => $school->id, 'first_name' => 'Mia', 'last_name' => 'Muster', 'class_name' => '4a']);
    $group->students()->attach($student->id);
    $competence = CustomProcessCompetence::create(['school_id' => $school->id, 'text' => 'Religiöse Fragen besprechen', 'position' => 1]);

    $this->actingAs($user)->post("/unterrichtsgruppen/{$group->id}/bewertungen/zeiträume", ['label' => '1. Halbjahr', 'starts_on' => '2026-09-01', 'ends_on' => '2027-02-01'])->assertRedirect();
    $evaluation = ReportPeriod::first()->evaluations()->first();

    $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}/bewertungen/{$evaluation->id}/bearbeiten")->assertInertia(fn ($page) => $page
        ->where('customProcessCompetences.0.text', 'Religiöse Fragen besprechen')
        ->where('customProcessCompetenceScaleIntervalCount', 4));

    $school->update(['observation_scale_interval_count' => 5]);
    $competence->update(['text' => 'Religiöse Fragen reflektieren']);
    $this->actingAs($user)->put("/unterrichtsgruppen/{$group->id}/bewertungen/{$evaluation->id}", [
        'draft_text' => 'Bewertungstext',
        'teacher_note' => '',
        'status' => 'confirmed',
        'observation_scales' => [['custom_process_competence_id' => $competence->id, 'custom_scale_level' => 4]],
    ])->assertRedirect();

    expect(StudentEvaluationObservationScale::first()->competence_text_snapshot)->toBe('Religiöse Fragen reflektieren')
        ->and(StudentEvaluationObservationScale::first()->interval_count_snapshot)->toBe(5)
        ->and(StudentEvaluationObservationScale::first()->custom_scale_level)->toBe(4);
});

it('rejects a level that no longer exists after the school scale is reduced', function () {
    $org = Organization::create(['name' => 'Skalenänderung']);
    $user = User::factory()->create(['organization_id' => $org->id]);
    $school = School::create(['organization_id' => $org->id, 'name' => 'Änderungsschule', 'observation_scale_interval_count' => 4]);
    $year = SchoolYear::create(['organization_id' => $org->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $org->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4a', 'grading_model' => 'observation_scales']);
    $student = Student::create(['organization_id' => $org->id, 'school_id' => $school->id, 'first_name' => 'Mia', 'last_name' => 'Muster', 'class_name' => '4a']);
    $group->students()->attach($student->id);
    $competence = CustomProcessCompetence::create(['school_id' => $school->id, 'text' => 'Wahrnehmen', 'position' => 1]);
    $this->actingAs($user)->post("/unterrichtsgruppen/{$group->id}/bewertungen/zeiträume", ['label' => '1. Halbjahr', 'starts_on' => '2026-09-01', 'ends_on' => '2027-02-01']);
    $evaluation = ReportPeriod::first()->evaluations()->first();
    $school->update(['observation_scale_interval_count' => 3]);

    $this->actingAs($user)->put("/unterrichtsgruppen/{$group->id}/bewertungen/{$evaluation->id}", [
        'status' => 'confirmed',
        'observation_scales' => [['custom_process_competence_id' => $competence->id, 'custom_scale_level' => 4]],
    ])->assertStatus(422);
});
