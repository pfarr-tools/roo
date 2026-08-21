<?php

use App\Models\Organization;
use App\Models\ReportPeriod;
use App\Models\School;
use App\Models\SchoolYear;
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
