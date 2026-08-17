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

it('registers a user and redirects to the dashboard', function () {
    $this->withoutMiddleware();

    $response = $this->post('/register', [
        '_token' => csrf_token(),
        'name' => 'Erika Mustermann',
        'email' => 'erika@example.test',
        'password' => 'Ein-sicheres-Passwort-123!',
        'password_confirmation' => 'Ein-sicheres-Passwort-123!',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'erika@example.test']);
});

it('protects the dashboard and allows an authenticated user to log out', function () {
    $user = User::factory()->create();

    $this->get('/dashboard')->assertRedirect('/login');

    $this->actingAs($user)->get('/dashboard')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard'));

    $this->post('/logout', ['_token' => csrf_token()])->assertRedirect('/');
    $this->assertGuest();
});
