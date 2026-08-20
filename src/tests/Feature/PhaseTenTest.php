<?php

use App\Models\Assessment;
use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\TeachingGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
