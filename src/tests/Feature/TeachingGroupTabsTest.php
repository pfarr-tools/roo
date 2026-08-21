<?php

use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolYear;
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
    $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}/bewertungen/neu")
        ->assertInertia(fn ($page) => $page->component('Evaluations/PeriodForm'));
});
