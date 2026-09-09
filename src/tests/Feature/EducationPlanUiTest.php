<?php

use App\Models\EducationPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows global and own education plans but not another organizations plan', function () {
    $user = User::factory()->create();
    EducationPlan::create(['external_identifier' => 'GLOBAL', 'subject' => 'Evangelische Religionslehre', 'title' => 'Globaler Bildungsplan']);
    EducationPlan::create(['user_id' => $user->id, 'external_identifier' => 'OWN', 'subject' => 'Evangelische Religionslehre', 'title' => 'Eigener Bildungsplan']);
    EducationPlan::create(['user_id' => User::factory()->create()->id, 'external_identifier' => 'FOREIGN', 'subject' => 'Evangelische Religionslehre', 'title' => 'Fremder Bildungsplan']);

    $this->actingAs($user)->get('/bildungsplaene')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('educationPlans', 2)
            ->where('educationPlans.0.title', 'Eigener Bildungsplan')
            ->where('educationPlans.1.title', 'Globaler Bildungsplan'));
});
