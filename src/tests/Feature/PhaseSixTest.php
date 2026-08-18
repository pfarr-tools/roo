<?php

use App\Models\GroupYearPlan;
use App\Models\LessonOccurrence;
use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolPeriod;
use App\Models\SchoolYear;
use App\Models\SchoolYearDay;
use App\Models\TeachingGroup;
use App\Models\UnitTemplate;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([PreventRequestForgery::class]);
});

function phaseSixGroup(): array
{
    $organization = Organization::create(['name' => 'Phase 6 Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Planungsschule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '2a Religion']);
    $period = SchoolPeriod::create(['school_id' => $school->id, 'period_number' => 1, 'starts_at' => '08:00', 'ends_at' => '08:45']);
    $group->schoolPeriods()->attach($period->id, ['weekday' => 2]);

    return [$user, $year, $group];
}

it('creates a scoped year plan and planned unit from a template', function () {
    [$user, $year, $group] = phaseSixGroup();
    $template = UnitTemplate::create(['organization_id' => $user->organization_id, 'title' => 'Schöpfung', 'expected_hours' => 2]);

    $this->actingAs($user)->post("/jahresplanung/{$group->id}/einheiten", [
        'title' => 'Schöpfung bewahren', 'unit_template_id' => $template->id,
        'starts_on' => '2026-09-01', 'ends_on' => '2026-09-15', 'planned_hours' => 2,
    ])->assertRedirect();

    $this->assertDatabaseHas('group_year_plans', ['teaching_group_id' => $group->id, 'school_year_id' => $year->id]);
    $this->assertDatabaseHas('planned_units', ['title' => 'Schöpfung bewahren', 'unit_template_id' => $template->id]);
    $this->assertDatabaseHas('plan_revisions', ['action' => 'unit_created']);
});

it('does not allow planning outside the groups school year', function () {
    [$user, , $group] = phaseSixGroup();

    $this->actingAs($user)->post("/jahresplanung/{$group->id}/einheiten", [
        'title' => 'Außerhalb', 'starts_on' => '2026-08-31', 'ends_on' => '2026-09-02', 'planned_hours' => 1,
    ])->assertStatus(422);
});

it('generates lessons on regular timetable days and skips no-instruction days', function () {
    [$user, $year, $group] = phaseSixGroup();
    SchoolYearDay::create(['school_year_id' => $year->id, 'date' => '2026-09-08', 'kind' => 'no_instruction', 'label' => 'Ferien']);
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/einheiten", ['title' => 'Woche', 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30', 'planned_hours' => 2]);

    $this->actingAs($user)->post("/jahresplanung/{$group->id}/stunden-erzeugen")->assertRedirect();

    $this->assertDatabaseCount('lesson_occurrences', 2);
    $this->assertDatabaseMissing('lesson_occurrences', ['planned_on' => '2026-09-08']);
    expect(GroupYearPlan::first()->revision)->toBe(3);
});

it('keeps actual lesson status separate from planning and protects organizations', function () {
    [$user, , $group] = phaseSixGroup();
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/einheiten", ['title' => 'Stunde', 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-10', 'planned_hours' => 1]);
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/stunden-erzeugen");
    $occurrence = LessonOccurrence::firstOrFail();

    $this->actingAs($user)->put("/jahresplanung/{$group->id}/vorkommnisse/{$occurrence->id}", ['status' => 'conducted', 'actual_on' => '2026-09-02'])->assertRedirect();
    expect($occurrence->fresh()->planned_on->toDateString())->toBe('2026-09-01')
        ->and($occurrence->fresh()->actual_on->toDateString())->toBe('2026-09-02')
        ->and($occurrence->fresh()->status)->toBe('conducted');

    $otherOrganization = Organization::create(['name' => 'Fremd']);
    $otherUser = User::factory()->create(['organization_id' => $otherOrganization->id]);
    $this->actingAs($otherUser)->get("/jahresplanung/{$group->id}")->assertForbidden();
});
