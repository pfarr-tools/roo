<?php

use App\Models\CustomProcessCompetence;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function customCompetenceUser(): User
{

    return User::factory()->create();
}

function customCompetenceSchool(User $user, string $name = 'Eigene Kompetenz Schule'): School
{
    return School::create(['user_id' => $user->id, 'name' => $name]);
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

it('updates the schools observation scale and custom competences together', function () {
    $user = customCompetenceUser();
    $school = customCompetenceSchool($user);
    $existing = CustomProcessCompetence::create(['school_id' => $school->id, 'text' => 'Alte Formulierung', 'position' => 1]);

    $this->actingAs($user)->put("/schulen/{$school->slug}/beobachtungsskala", [
        'observation_scale_interval_count' => 5,
        'competences' => [
            ['id' => $existing->id, 'text' => 'Neue Formulierung', 'position' => 2, 'is_active' => true],
            ['text' => 'Religiöse Fragen besprechen', 'position' => 1, 'is_active' => true],
        ],
    ])->assertRedirect();

    expect($school->fresh()->observation_scale_interval_count)->toBe(5)
        ->and($school->fresh()->customProcessCompetences->pluck('text')->all())->toBe(['Religiöse Fragen besprechen', 'Neue Formulierung']);
});

it('rejects an invalid observation scale configuration', function () {
    $user = customCompetenceUser();
    $school = customCompetenceSchool($user);

    $this->actingAs($user)->put("/schulen/{$school->slug}/beobachtungsskala", [
        'observation_scale_interval_count' => 1,
        'competences' => [['text' => '', 'position' => 1, 'is_active' => true]],
    ])->assertSessionHasErrors(['observation_scale_interval_count', 'competences.0.text']);
});

it('does not allow another organization to change a schools scale', function () {
    $user = customCompetenceUser();
    $foreignUser = customCompetenceUser();
    $school = customCompetenceSchool($user);

    $this->actingAs($foreignUser)->put("/schulen/{$school->slug}/beobachtungsskala", [
        'observation_scale_interval_count' => 5,
        'competences' => [],
    ])->assertForbidden();
});

it('shows the school scale configuration on the school page', function () {
    $user = customCompetenceUser();
    $school = customCompetenceSchool($user);
    CustomProcessCompetence::create(['school_id' => $school->id, 'text' => 'Religiöse Fragen besprechen', 'position' => 1]);

    $this->actingAs($user)->get("/schulen/{$school->slug}")->assertSuccessful()->assertInertia(fn ($page) => $page
        ->where('school.observation_scale_interval_count', 4)
        ->where('school.custom_process_competences.0.text', 'Religiöse Fragen besprechen'));
});
