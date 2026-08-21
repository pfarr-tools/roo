<?php

use App\Models\Organization;
use App\Models\Assessment;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\ScheduleSlot;
use App\Models\TeachingGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
    $this->actingAs($user)->put("/unterrichtsgruppen/{$group->id}/lernstandserhebungen/{$assessment->id}", ['title' => 'LSE Schöpfung', 'return_to' => 'year-plan'])
        ->assertRedirect("/jahresplanung/{$group->id}");
    $this->actingAs($user)->put("/unterrichtsgruppen/{$group->id}/lernstandserhebungen/{$assessment->id}", ['title' => 'LSE Schöpfung aktualisiert', 'return_tab' => 'assessments'])
        ->assertRedirect("/unterrichtsgruppen/{$group->id}?tab=assessments");

    $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}/bewertungen/neu")
        ->assertInertia(fn ($page) => $page->component('Evaluations/PeriodForm'));
});
