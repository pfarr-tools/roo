<?php

use App\Models\Assessment;
use App\Models\AssessmentTask;
use App\Models\EducationPlan;
use App\Models\EducationPlanCompetenceArea;
use App\Models\EducationPlanCompetency;
use App\Models\EducationPlanVersion;
use App\Models\Organization;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\ScheduledLesson;
use App\Models\TeachingGroup;
use App\Models\TeachingGroupGradeLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('legt eine Lernstandserhebung zunächst ohne Aufgaben an', function () {
    $organization = Organization::create(['name' => 'Aufgabenlose Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Aufgabenlose Schule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '5a']);

    $this->actingAs($user)->post("/unterrichtsgruppen/{$group->id}/lernstandserhebungen", ['title' => 'LSE ohne Aufgaben'])->assertRedirect();

    expect(Assessment::first()->tasks)->toBeEmpty();
});

it('legt eine Lernstandserhebung mit differenzierten Aufgaben an', function () {
    $organization = Organization::create(['name' => 'Bewertungsorganisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Bewertungsschule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '5a']);
    $unit = $group->teachingUnits()->create(['organization_id' => $organization->id, 'title' => 'Einheit', 'position' => 1]);
    $competency = $unit->competencies()->create(['local_wording' => 'Kann erklären']);

    $this->actingAs($user)->post("/unterrichtsgruppen/{$group->id}/lernstandserhebungen", ['title' => 'LSE Schöpfung', 'assessed_on' => '2026-11-12', 'tasks' => [['title' => 'Erkläre den Begriff', 'max_points' => 10, 'level' => 'M', 'competency_id' => $competency->id]]])->assertRedirect();

    expect(Assessment::first()->tasks)->toHaveCount(1)->and(Assessment::first()->tasks->first()->level)->toBe('M');
});

it('verwendet eine Bibliotheksaufgabe in mehreren Erhebungen und verlangt mehrere G/M/E-Niveaus', function () {
    $organization = Organization::create(['name' => 'Differenzierte Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Differenzierte Schule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '5 G/M/E']);
    TeachingGroupGradeLevel::create(['teaching_group_id' => $group->id, 'grade_level' => 'G/M/E']);
    $unit = $group->teachingUnits()->create(['organization_id' => $organization->id, 'title' => 'Einheit', 'position' => 1]);
    $competency = $unit->competencies()->create(['local_wording' => 'Kann vergleichen']);
    $task = AssessmentTask::create(['organization_id' => $organization->id, 'teaching_unit_competency_id' => $competency->id, 'title' => 'Vergleiche', 'max_points' => 6]);

    $payload = ['title' => 'LSE', 'tasks' => [['task_id' => $task->id, 'levels' => ['G', 'M']]]];
    $this->actingAs($user)->post("/unterrichtsgruppen/{$group->id}/lernstandserhebungen", $payload)->assertRedirect();
    $this->actingAs($user)->post("/unterrichtsgruppen/{$group->id}/lernstandserhebungen", $payload)->assertRedirect();

    expect($task->fresh()->assessments)->toHaveCount(2)->and($task->fresh()->levels->pluck('level')->all())->toBe(['G', 'M']);
});

it('liefert alle inhaltsbezogenen Kompetenzen des relevanten Zeitraums auch ohne Aufgabe', function () {
    $organization = Organization::create(['name' => 'Kompetenzgruppenorganisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Kompetenzgruppenschule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '5a']);
    $plan = EducationPlan::create(['organization_id' => $organization->id, 'external_identifier' => 'BP', 'subject' => 'Religion', 'title' => 'Bildungsplan']);
    $version = EducationPlanVersion::create(['education_plan_id' => $plan->id, 'external_identifier' => '2026', 'schema_version' => '1', 'title' => '2026', 'is_complete' => true, 'raw_payload' => []]);
    $area = EducationPlanCompetenceArea::create(['education_plan_version_id' => $version->id, 'kind' => 'content', 'external_identifier' => '3.1', 'title' => 'Inhalt', 'position' => 1]);
    $withoutTask = EducationPlanCompetency::create(['education_plan_competence_area_id' => $area->id, 'external_identifier' => '3.1.1', 'text' => 'Ohne Aufgabe', 'position' => 1, 'is_active' => true]);
    $withTask = EducationPlanCompetency::create(['education_plan_competence_area_id' => $area->id, 'external_identifier' => '3.1.2', 'text' => 'Mit Aufgabe', 'position' => 2, 'is_active' => true]);
    $unit = $group->teachingUnits()->create(['organization_id' => $organization->id, 'title' => 'Einheit', 'position' => 1]);
    $lesson = $unit->lessons()->create(['title' => 'Stunde', 'position' => 1, 'duration' => 1]);
    $lesson->competencies()->attach([
        $unit->competencies()->create(['education_plan_competency_id' => $withoutTask->id])->id,
        $unit->competencies()->create(['education_plan_competency_id' => $withTask->id])->id,
    ]);
    $task = AssessmentTask::create(['organization_id' => $organization->id, 'education_plan_id' => $plan->id, 'education_plan_competency_id' => $withTask->id, 'title' => 'Aufgabe']);
    $lesson->assessmentTasks()->attach($task);
    $assessment = Assessment::create(['organization_id' => $organization->id, 'teaching_group_id' => $group->id, 'title' => 'LSE', 'assessed_on' => '2026-10-01']);
    $slot = ScheduleSlot::create(['teaching_group_id' => $group->id, 'assessment_id' => $assessment->id, 'date' => '2026-10-01', 'period_number' => 1, 'starts_at' => '08:00', 'ends_at' => '08:45', 'status' => 'lse']);
    ScheduledLesson::create(['lesson_id' => $lesson->id, 'schedule_slot_id' => $slot->id]);

    $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}/lernstandserhebungen/{$assessment->id}/bearbeiten")
        ->assertInertia(fn ($page) => $page
            ->has('assessmentCompetencies', 2)
            ->where('assessmentCompetencies.0.title', '3.1.1 – Ohne Aufgabe')
            ->where('assessmentCompetencies.1.title', '3.1.2 – Mit Aufgabe'));
});
