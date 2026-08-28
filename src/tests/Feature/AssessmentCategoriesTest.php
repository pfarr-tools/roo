<?php

use App\Models\Assessment;
use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\TeachingGroup;
use App\Models\TeachingGroupGradeComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function assessmentCategoryFixture(): array
{
    $organization = Organization::create(['name' => 'Kategorieorganisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Kategorieschule']);
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
        'name' => 'Kategoriegruppe',
    ]);
    $component = TeachingGroupGradeComponent::create([
        'teaching_group_id' => $group->id,
        'type' => 'custom',
        'label' => 'Ordner',
        'percentage' => 10,
        'position' => 3,
    ]);
    $assessment = Assessment::create([
        'organization_id' => $organization->id,
        'teaching_group_id' => $group->id,
        'title' => 'Ordnereinsicht',
    ]);

    return compact('user', 'group', 'component', 'assessment');
}

it('keeps an assessment category readable after the category is deactivated', function () {
    $fixture = assessmentCategoryFixture();
    $fixture['assessment']->update([
        'grade_component_id' => $fixture['component']->id,
        'grade_component_label' => $fixture['component']->label,
    ]);
    $fixture['component']->update(['is_active' => false]);

    expect($fixture['assessment']->fresh()->gradeComponentLabel)->toBe('Ordner')
        ->and($fixture['assessment']->fresh()->gradeComponent)->toBeNull();
});

it('distinguishes manually created booklets from scanned booklets', function () {
    $fixture = assessmentCategoryFixture();
    $booklet = $fixture['assessment']->booklets()->create([
        'student_id' => null,
        'number' => 1,
        'status' => 'open',
        'source' => 'manual',
    ]);

    expect($booklet->source)->toBe('manual')->and($booklet->fragments)->toBeEmpty();
});
