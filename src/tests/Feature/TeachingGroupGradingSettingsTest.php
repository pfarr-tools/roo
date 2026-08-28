<?php

use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\TeachingGroup;
use App\Models\TeachingGroupGradeComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function gradingSettingsGroup(): array
{
    $organization = Organization::create(['name' => 'Bewertungsorganisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Bewertungsschule']);
    $year = SchoolYear::create([
        'organization_id' => $organization->id,
        'school_id' => $school->id,
        'name' => '2026/27',
        'starts_on' => '2026-09-01',
        'ends_on' => '2027-07-31',
    ]);
    $group = TeachingGroup::create([
        'organization_id' => $organization->id,
        'school_id' => $school->id,
        'school_year_id' => $year->id,
        'name' => 'Bewertungsgruppe',
    ]);

    return [$user, $group];
}

it('speichert Bewertungsmodell und Notenmix der Unterrichtsgruppe', function () {
    [$user, $group] = gradingSettingsGroup();

    $this->actingAs($user)->put("/unterrichtsgruppen/{$group->id}/bewertungseinstellungen", [
        'grading_model' => 'competency_texts_and_grades',
        'numeric_grades_enabled' => true,
        'components' => [
            ['type' => 'observations', 'percentage' => 40],
            ['type' => 'written_assessments', 'percentage' => 50],
            ['type' => 'custom', 'label' => 'Mitarbeit', 'percentage' => 5],
            ['type' => 'custom', 'label' => 'Projekte', 'percentage' => 5],
        ],
    ])->assertRedirect("/unterrichtsgruppen/{$group->id}?tab=evaluations");

    expect($group->fresh()->grading_model)->toBe('competency_texts_and_grades')
        ->and($group->fresh()->numeric_grades_enabled)->toBeTrue();
    expect(TeachingGroupGradeComponent::where('teaching_group_id', $group->id)->orderBy('position')->get()->map(fn ($component) => [$component->type, $component->label, $component->percentage])->all())
        ->toBe([
            ['observations', 'Beobachtungen im Unterricht', 40],
            ['written_assessments', 'Schriftliche Leistungen', 50],
            ['custom', 'Mitarbeit', 5],
            ['custom', 'Projekte', 5],
        ]);
});

it('weist einen Notenmix zurück, dessen Summe nicht 100 Prozent beträgt', function () {
    [$user, $group] = gradingSettingsGroup();

    $this->actingAs($user)->put("/unterrichtsgruppen/{$group->id}/bewertungseinstellungen", [
        'grading_model' => 'grades_only',
        'numeric_grades_enabled' => true,
        'components' => [
            ['type' => 'observations', 'percentage' => 50],
            ['type' => 'written_assessments', 'percentage' => 40],
        ],
    ])->assertSessionHasErrors('components');
});

it('benötigt keinen Notenmix, wenn Kompetenztexte ohne numerische Noten verwendet werden', function () {
    [$user, $group] = gradingSettingsGroup();

    $this->actingAs($user)->put("/unterrichtsgruppen/{$group->id}/bewertungseinstellungen", [
        'grading_model' => 'competency_texts_and_grades',
        'numeric_grades_enabled' => false,
    ])->assertRedirect("/unterrichtsgruppen/{$group->id}?tab=evaluations");

    expect($group->fresh()->numeric_grades_enabled)->toBeFalse()
        ->and(TeachingGroupGradeComponent::where('teaching_group_id', $group->id)->count())->toBe(0);
});
