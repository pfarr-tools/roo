<?php

use App\Models\CustomProcessCompetence;
use App\Models\Organization;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function customCompetenceUser(): User
{
    $organization = Organization::create(['name' => 'Eigene Kompetenz Organisation']);

    return User::factory()->create(['organization_id' => $organization->id]);
}

function customCompetenceSchool(User $user, string $name = 'Eigene Kompetenz Schule'): School
{
    return School::create(['organization_id' => $user->organization_id, 'name' => $name]);
}

it('defaults schools to four observation intervals', function () {
    $user = customCompetenceUser();
    $school = customCompetenceSchool($user);

    expect($school->fresh()->observation_scale_interval_count)->toBe(4);
});

it('stores school-owned custom process competences in position order', function () {
    $user = customCompetenceUser();
    $school = customCompetenceSchool($user);
    $second = CustomProcessCompetence::create(['school_id' => $school->id, 'text' => 'Mit anderen sprechen', 'position' => 2]);
    $first = CustomProcessCompetence::create(['school_id' => $school->id, 'text' => 'Wahrnehmen und beschreiben', 'position' => 1]);

    expect($school->fresh()->customProcessCompetences->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($first->fresh()->is_active)->toBeTrue()
        ->and($first->fresh()->school_id)->toBe($school->id);
});

it('authorizes custom process competences only within the schools organization', function () {
    $user = customCompetenceUser();
    $foreignUser = customCompetenceUser();
    $school = customCompetenceSchool($user);
    $competence = CustomProcessCompetence::create(['school_id' => $school->id, 'text' => 'Eigene Kompetenz', 'position' => 1]);

    expect($user->can('view', $competence))->toBeTrue()
        ->and($foreignUser->can('view', $competence))->toBeFalse();
});
