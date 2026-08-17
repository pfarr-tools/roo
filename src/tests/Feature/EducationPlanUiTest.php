<?php

use App\Models\EducationPlan;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows global and own education plans but not another organizations plan', function () {
    $organization = Organization::create(['name' => 'Test Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    EducationPlan::create(['external_identifier' => 'GLOBAL', 'subject' => 'Evangelische Religionslehre', 'title' => 'Globaler Bildungsplan']);
    EducationPlan::create(['organization_id' => $organization->id, 'external_identifier' => 'OWN', 'subject' => 'Evangelische Religionslehre', 'title' => 'Eigener Bildungsplan']);
    EducationPlan::create(['organization_id' => Organization::create(['name' => 'Andere Organisation'])->id, 'external_identifier' => 'FOREIGN', 'subject' => 'Evangelische Religionslehre', 'title' => 'Fremder Bildungsplan']);

    $this->actingAs($user)->get('/bildungsplaene')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('educationPlans', 2)
            ->where('educationPlans.0.title', 'Eigener Bildungsplan')
            ->where('educationPlans.1.title', 'Globaler Bildungsplan'));
});
