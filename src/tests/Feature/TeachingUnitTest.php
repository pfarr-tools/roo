<?php

use App\Models\Organization;
use App\Models\ResourceLink;
use App\Models\ResourceReference;
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
    $competency = officialCompetency($source, 'Verantwortung übernehmen');
    $lesson = $source->lessons()->create(['title' => 'Einstieg', 'position' => 1, 'duration' => 1]);
    $lesson->educationPlanCompetencies()->attach($competency->id);
    $lesson->phases()->create(['title' => 'Gespräch', 'position' => 1]);

    $this->actingAs($user)->get('/unterrichtseinheiten')->assertInertia(fn ($page) => $page->component('TeachingUnits/Index')->has('units', 1));
    $this->actingAs($user)->post("/jahresplanung/{$targetGroup->id}/eigene-einheiten/importieren", ['source_id' => $source->id])->assertRedirect();

    $copy = TeachingUnit::where('teaching_group_id', $targetGroup->id)->firstOrFail();
    expect($copy->copied_from_id)->toBe($source->id)
        ->and($copy->lessons)->toHaveCount(1)
        ->and($copy->lessons->first()->phases)->toHaveCount(1)
        ->and($copy->lessons->first()->educationPlanCompetencies)->toHaveCount(1);

    $source->update(['title' => 'Geänderte Quelle']);
    expect($copy->fresh()->title)->toBe('Schöpfung bewahren');
});

it('persists the public introduction, creator, and material publication statuses', function () {
    $organization = Organization::create(['name' => 'Öffentliche Einheit Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Öffentliche Schule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4a']);
    $unit = $group->teachingUnits()->create([
        'organization_id' => $organization->id,
        'title' => 'Schöpfung bewahren',
        'position' => 1,
        'introduction_text' => 'Wir untersuchen, wie Menschen Verantwortung übernehmen.',
        'created_by_user_id' => $user->id,
    ]);
    $resource = ResourceReference::create(['organization_id' => $organization->id, 'teaching_unit_id' => $unit->id, 'original_name' => 'Arbeitsblatt.pdf', 'storage_path' => 'teaching-units/1/arbeitsblatt.pdf', 'publication_status' => 'not_shared']);
    $link = ResourceLink::create(['organization_id' => $organization->id, 'teaching_unit_id' => $unit->id, 'title' => 'Weiterlesen', 'url' => 'https://example.test/weiterlesen', 'publication_status' => 'shared_immediately']);
    $lesson = $unit->lessons()->create(['title' => 'Einstieg', 'position' => 1, 'duration' => 1]);
    $phase = $lesson->phases()->create(['title' => 'Gespräch', 'position' => 1]);
    $phase->resources()->attach($resource, ['publication_status' => 'shared_with_lesson']);
    $phase->resourceLinks()->attach($link, ['publication_status' => 'shared_immediately']);

    $freshUnit = $unit->fresh(['creator']);
    expect($freshUnit->introduction_text)->toBe('Wir untersuchen, wie Menschen Verantwortung übernehmen.')
        ->and($freshUnit->creator->is($user))->toBeTrue()
        ->and($resource->fresh()->publication_status->value)->toBe('not_shared')
        ->and($link->fresh()->publication_status->value)->toBe('shared_immediately')
        ->and($phase->fresh()->resources->first()->pivot->publication_status->value)->toBe('shared_with_lesson')
        ->and($phase->fresh()->resourceLinks->first()->pivot->publication_status->value)->toBe('shared_immediately');
});

it('updates the introduction and direct URL publication status through the unit editor', function () {
    $organization = Organization::create(['name' => 'Einheiteneditor Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Einheiteneditor Schule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4a']);
    $unit = $group->teachingUnits()->create(['organization_id' => $organization->id, 'title' => 'Alte Einheit', 'position' => 1]);
    $link = ResourceLink::create(['organization_id' => $organization->id, 'teaching_unit_id' => $unit->id, 'title' => 'Quelle', 'url' => 'https://example.test/alt']);

    $this->actingAs($user)->put('/unterrichtseinheiten/'.$unit->id, [
        'title' => 'Neue Einheit',
        'keyword' => '',
        'notes' => '',
        'introduction_text' => 'Das ist die Einführung für die Familien.',
        'resource_links' => [[
            'id' => $link->id,
            'title' => 'Quelle aktuell',
            'url' => 'https://example.test/neu',
            'publication_status' => 'shared_immediately',
        ]],
    ])->assertRedirect();

    expect($unit->fresh()->introduction_text)->toBe('Das ist die Einführung für die Familien.')
        ->and($link->fresh()->title)->toBe('Quelle aktuell')
        ->and($link->fresh()->publication_status->value)->toBe('shared_immediately');
});

it('rejects lesson-timed publication for a direct unit URL', function () {
    $organization = Organization::create(['name' => 'Statusvalidierung Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Statusvalidierung Schule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4a']);
    $unit = $group->teachingUnits()->create(['organization_id' => $organization->id, 'title' => 'Einheit', 'position' => 1]);
    $link = ResourceLink::create(['organization_id' => $organization->id, 'teaching_unit_id' => $unit->id, 'title' => 'Quelle', 'url' => 'https://example.test/alt']);

    $this->actingAs($user)->put('/unterrichtseinheiten/'.$unit->id, [
        'title' => 'Einheit',
        'resource_links' => [[
            'id' => $link->id,
            'title' => 'Quelle',
            'url' => 'https://example.test/alt',
            'publication_status' => 'shared_with_lesson',
        ]],
    ])->assertSessionHasErrors('resource_links.0.publication_status');
});

it('persists publication statuses on concrete phase assignments', function () {
    $organization = Organization::create(['name' => 'Phasenfreigabe Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Phasenfreigabe Schule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4a']);
    $unit = $group->teachingUnits()->create(['organization_id' => $organization->id, 'title' => 'Einheit', 'position' => 1]);
    $lesson = $unit->lessons()->create(['title' => 'Stunde', 'position' => 1, 'duration' => 1]);
    $phase = $lesson->phases()->create(['title' => 'Arbeitsphase', 'position' => 1]);
    $resource = ResourceReference::create(['organization_id' => $organization->id, 'teaching_unit_id' => $unit->id, 'lesson_id' => $lesson->id, 'original_name' => 'Arbeitsblatt.pdf', 'storage_path' => 'teaching-units/1/arbeitsblatt.pdf']);
    $link = ResourceLink::create(['organization_id' => $organization->id, 'teaching_unit_id' => $unit->id, 'lesson_id' => $lesson->id, 'title' => 'Erklärung', 'url' => 'https://example.test/erklaerung']);

    $this->actingAs($user)->put('/jahresplanung/'.$group->id.'/lessons/'.$lesson->id, [
        'title' => $lesson->title,
        'duration' => 1,
        'phases' => [[
            'id' => $phase->id,
            'title' => $phase->title,
            'resource_ids' => [$resource->id],
            'resource_link_ids' => [$link->id],
            'resource_publication_statuses' => [$resource->id => 'shared_with_lesson'],
            'resource_link_publication_statuses' => [$link->id => 'shared_immediately'],
        ]],
    ])->assertRedirect();

    $savedPhase = $phase->fresh(['resources', 'resourceLinks']);
    expect($savedPhase->resources->first()->pivot->publication_status->value)->toBe('shared_with_lesson')
        ->and($savedPhase->resourceLinks->first()->pivot->publication_status->value)->toBe('shared_immediately');
});
