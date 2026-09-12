<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('shows the German login page', function () {
    $response = $this->get('/login');

    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
});

it('redirects authenticated users from the home page to the timetable', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/')->assertRedirect('/stundenplan');
});

it('registers a user and redirects to the timetable', function () {
    $this->withoutMiddleware();

    $response = $this->post('/register', [
        '_token' => csrf_token(),
        'name' => 'Erika Mustermann',
        'email' => 'erika@example.test',
        'password' => 'Ein-sicheres-Passwort-123!',
        'password_confirmation' => 'Ein-sicheres-Passwort-123!',
    ]);

    $response->assertRedirect('/stundenplan');
    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'erika@example.test']);

    $this->post('/logout', ['_token' => csrf_token()])->assertRedirect('/');
    $this->assertGuest();

    $this->post('/login', [
        '_token' => csrf_token(),
        'email' => 'erika@example.test',
        'password' => 'Ein-sicheres-Passwort-123!',
    ])->assertRedirect('/stundenplan');

    $this->assertAuthenticated();
});

it('protects the timetable and allows an authenticated user to log out', function () {
    $user = User::factory()->create();

    $this->get('/stundenplan')->assertRedirect('/login');

    $this->actingAs($user)->get('/stundenplan')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard'));

    $this->actingAs($user)->get('/dashboard')->assertRedirect('/stundenplan');

    $this->post('/logout', ['_token' => csrf_token()])->assertRedirect('/');
    $this->assertGuest();
});

it('shows and updates the profile without exposing integration keys', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/profil')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Show')
            ->where('user.public_phone', null)
            ->where('user.email', $user->email)
            ->where('integrations.openai', false)
            ->where('integrations.flux', false)
            ->missing('user.openai_api_key')
            ->missing('user.flux_api_key'));

    $this->actingAs($user)->put('/profil', [
        'name' => 'Erika Neu',
        'email' => 'erika-neu@example.test',
        'public_phone' => '+49 170 1234567',
        'openai_api_key' => 'openai-secret',
        'flux_api_key' => 'flux-secret',
    ])->assertRedirect('/profil');

    $user->refresh();
    expect($user->public_phone)->toBe('+49 170 1234567')
        ->and($user->openai_api_key)->toBe('openai-secret')
        ->and($user->flux_api_key)->toBe('flux-secret');
    $this->assertDatabaseMissing('users', ['openai_api_key' => 'openai-secret']);
});
