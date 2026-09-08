<?php

use App\Models\Assessment;
use App\Models\Organization;
use App\Models\ReportPeriod;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\TeachingGroup;
use App\Models\TeachingUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('zeigt die sechs Bereiche der Unterrichtsgruppe als Tabs', function () {
    $organization = Organization::create(['name' => 'Tab Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Tab Schule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => 'Tabgruppe']);
    $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}")->assertInertia(fn ($page) => $page
        ->component('TeachingGroups/Show')
        ->where('group.name', 'Tabgruppe')
        ->has('assessments')
        ->has('reportPeriods'));

    $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}/lernstandserhebungen/neu")
        ->assertInertia(fn ($page) => $page->component('Assessments/Form'));

    $assessment = Assessment::create(['organization_id' => $organization->id, 'teaching_group_id' => $group->id, 'title' => 'LSE Schöpfung', 'assessed_on' => '2026-11-12']);
    ScheduleSlot::create(['teaching_group_id' => $group->id, 'assessment_id' => $assessment->id, 'date' => '2026-11-12', 'period_number' => 2, 'starts_at' => '09:00', 'ends_at' => '09:45', 'status' => 'lse']);

    $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}/lernstandserhebungen/{$assessment->id}/bearbeiten?return_tab=assessments")
        ->assertInertia(fn ($page) => $page->component('Assessments/Form')->where('slot.date', '2026-11-12')->where('returnTab', 'assessments'));
    $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}/lernstandserhebungen/{$assessment->id}/bearbeiten?return_to=year-plan")
        ->assertInertia(fn ($page) => $page->component('Assessments/Form')->where('returnTo', 'year-plan'));
    $this->actingAs($user)->put("/unterrichtsgruppen/{$group->id}/lernstandserhebungen/{$assessment->id}", ['title' => 'LSE Schöpfung', 'grade_component_id' => null, 'return_to' => 'year-plan'])
        ->assertRedirect("/jahresplanung/{$group->id}");
    $this->actingAs($user)->put("/unterrichtsgruppen/{$group->id}/lernstandserhebungen/{$assessment->id}", ['title' => 'LSE Schöpfung aktualisiert', 'grade_component_id' => null, 'return_tab' => 'assessments'])
        ->assertRedirect("/unterrichtsgruppen/{$group->id}?tab=assessments");

    $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}/bewertungen/neu")
        ->assertInertia(fn ($page) => $page->component('Evaluations/PeriodForm'));
});

it('zeigt Bewertungen in einer eigenen Ansicht für die ausgewählte Gruppe', function () {
    $organization = Organization::create(['name' => 'Bewertungsnavigation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Bewertungsschule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $firstGroup = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4a']);
    $secondGroup = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '5a']);
    $period = ReportPeriod::create(['organization_id' => $organization->id, 'teaching_group_id' => $secondGroup->id, 'label' => '1. Halbjahr', 'starts_on' => '2026-09-01', 'ends_on' => '2027-02-01']);

    $this->actingAs($user)->get('/bewertungen?group='.$secondGroup->id)
        ->assertInertia(fn ($page) => $page
            ->component('Evaluations/Index')
            ->where('group.id', $secondGroup->id)
            ->where('group.name', '5a')
            ->where('reportPeriods.0.label', '1. Halbjahr')
            ->where('groups.0.id', $firstGroup->id)
            ->where('groups.1.id', $secondGroup->id)
        );

    $this->actingAs($user)->get("/unterrichtsgruppen/{$secondGroup->id}/bewertungen")
        ->assertInertia(fn ($page) => $page
            ->component('Evaluations/Index')
            ->where('group.id', $secondGroup->id)
        );

    $this->actingAs($user)->get("/unterrichtsgruppen/{$secondGroup->id}/bewertungen/zeiträume/{$period->id}/bearbeiten")
        ->assertInertia(fn ($page) => $page->component('Evaluations/PeriodForm')->where('period.id', $period->id));

    $this->actingAs($user)->put("/unterrichtsgruppen/{$secondGroup->id}/bewertungen/zeiträume/{$period->id}", [
        'label' => 'Ganzes Jahr',
        'starts_on' => '2026-09-01',
        'ends_on' => '2027-07-31',
        'whole_grades' => true,
        'include_full_school_year' => true,
    ])->assertRedirect("/unterrichtsgruppen/{$secondGroup->id}?tab=evaluations");
    expect($period->fresh()->whole_grades)->toBeTrue()
        ->and($period->fresh()->include_full_school_year)->toBeTrue();

    $student = Student::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'class_name' => '5a']);
    $this->actingAs($user)->post("/unterrichtsgruppen/{$secondGroup->id}/mitglieder", ['student_id' => $student->id])
        ->assertRedirect();
    $this->assertDatabaseHas('student_evaluations', ['report_period_id' => $period->id, 'student_id' => $student->id]);

    $this->actingAs($user)->delete("/unterrichtsgruppen/{$secondGroup->id}/bewertungen/zeiträume/{$period->id}")
        ->assertRedirect("/unterrichtsgruppen/{$secondGroup->id}?tab=evaluations");
    expect($period->fresh())->toBeNull();
});

it('liefert geplante Einheiten mit signierten Elternseiten im Gruppeneditor', function () {
    $organization = Organization::create(['name' => 'Unit Tab Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Unit Tab Schule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => 'Unit Tab Gruppe']);
    $unit = TeachingUnit::create(['organization_id' => $organization->id, 'created_by_user_id' => $user->id, 'teaching_group_id' => $group->id, 'title' => 'Schöpfung', 'position' => 1]);

    $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}")->assertInertia(fn ($page) => $page
        ->component('TeachingGroups/Show')
        ->where('teachingUnits.0.id', $unit->id)
        ->where('teachingUnits.0.title', 'Schöpfung')
        ->where('teachingUnits.0.public_url', URL::signedRoute('public.teaching-units.show', ['teachingUnit' => $unit])));
});

it('löscht eine Lernstandserhebung nur innerhalb ihrer Unterrichtsgruppe', function () {
    $organization = Organization::create(['name' => 'Delete Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Delete Schule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => 'Löschgruppe']);
    $otherGroup = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => 'Andere Gruppe']);
    $assessment = Assessment::create(['organization_id' => $organization->id, 'teaching_group_id' => $group->id, 'title' => 'LSE löschen']);
    $otherAssessment = Assessment::create(['organization_id' => $organization->id, 'teaching_group_id' => $otherGroup->id, 'title' => 'Andere LSE']);

    $this->actingAs($user)->delete("/unterrichtsgruppen/{$group->id}/lernstandserhebungen/{$otherAssessment->id}")->assertNotFound();
    expect($otherAssessment->fresh())->not->toBeNull();

    $this->actingAs($user)->delete("/unterrichtsgruppen/{$group->id}/lernstandserhebungen/{$assessment->id}")
        ->assertRedirect("/unterrichtsgruppen/{$group->id}?tab=assessments");
    expect($assessment->fresh())->toBeNull();
});
