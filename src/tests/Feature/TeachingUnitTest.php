<?php

use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\TeachingGroup;
use App\Models\TeachingUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists canonical teaching units and imports a recursive independent copy', function () {
    $organization = Organization::create(['name' => 'Unterrichtseinheiten Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Schule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $sourceGroup = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4a']);
    $targetGroup = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4b']);
    $source = $sourceGroup->teachingUnits()->create(['organization_id' => $organization->id, 'title' => 'Schöpfung bewahren', 'position' => 1, 'notes' => 'Quelle']);
    $competency = $source->competencies()->create(['local_wording' => 'Verantwortung übernehmen']);
    $lesson = $source->lessons()->create(['title' => 'Einstieg', 'position' => 1, 'duration' => 1]);
    $lesson->competencies()->attach($competency->id);
    $lesson->phases()->create(['title' => 'Gespräch', 'position' => 1]);

    $this->actingAs($user)->get('/unterrichtseinheiten')->assertInertia(fn ($page) => $page->component('TeachingUnits/Index')->has('units', 1));
    $this->actingAs($user)->post("/jahresplanung/{$targetGroup->id}/eigene-einheiten/importieren", ['source_id' => $source->id])->assertRedirect();

    $copy = TeachingUnit::where('teaching_group_id', $targetGroup->id)->firstOrFail();
    expect($copy->copied_from_id)->toBe($source->id)
        ->and($copy->lessons)->toHaveCount(1)
        ->and($copy->lessons->first()->phases)->toHaveCount(1)
        ->and($copy->lessons->first()->competencies)->toHaveCount(1);

    $source->update(['title' => 'Geänderte Quelle']);
    expect($copy->fresh()->title)->toBe('Schöpfung bewahren');
});
