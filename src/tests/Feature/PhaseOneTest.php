<?php

use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([
        PreventRequestForgery::class,
    ]);
});

function phaseOneUser(): User
{
    $organization = Organization::create(['name' => 'Test Organisation']);

    return User::factory()->create(['organization_id' => $organization->id]);
}

it('allows a teacher to create a school in their organization', function () {
    $user = phaseOneUser();

    $this->actingAs($user)->post('/schulen', ['_token' => csrf_token(), 'name' => 'Grundschule am Bach', 'city' => 'Ulm'])->assertRedirect('/schulen');

    $this->assertDatabaseHas('schools', ['organization_id' => $user->organization_id, 'name' => 'Grundschule am Bach']);
});

it('does not expose another organizations schools', function () {
    $user = phaseOneUser();
    $other = Organization::create(['name' => 'Andere Organisation']);
    $school = School::create(['organization_id' => $other->id, 'name' => 'Fremde Schule']);

    $this->actingAs($user)->get('/schulen')->assertSuccessful()->assertInertia(fn ($page) => $page->has('schools', 0));
    $this->actingAs($user)->get('/schuljahre/fremdjahr')->assertNotFound();
});

it('includes school year slugs for the school overview links', function () {
    $user = phaseOneUser();
    $school = School::create(['organization_id' => $user->organization_id, 'name' => 'Schule']);
    SchoolYear::create([
        'organization_id' => $user->organization_id,
        'school_id' => $school->id,
        'name' => '2026/27',
        'starts_on' => '2026-09-01',
        'ends_on' => '2027-07-31',
        'timezone' => 'Europe/Berlin',
    ]);

    $this->actingAs($user)->get('/schulen')->assertSuccessful()->assertInertia(fn ($page) => $page->where('schools.0.school_years.0.slug', '202627')
    );
});

it('creates calendar days and keeps local exceptions after importing BW holidays', function () {
    Http::fake([
        'https://ferien-api.de/api/v1/holidays/BW/2026' => Http::response([['slug' => 'osterferien-2026-BW', 'name' => 'osterferien', 'start' => '2026-03-29T22:00', 'end' => '2026-04-10T22:00']]),
        'https://ferien-api.de/api/v1/holidays/BW/2027' => Http::response([]),
    ]);
    $user = phaseOneUser();
    $school = School::create(['organization_id' => $user->organization_id, 'name' => 'Schule']);

    $this->actingAs($user)->post('/schuljahre', ['_token' => csrf_token(), 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31', 'timezone' => 'Europe/Berlin'])->assertRedirect();
    $year = SchoolYear::firstOrFail();
    $this->actingAs($user)->post('/schuljahre/'.$year->slug.'/ferien', ['_token' => csrf_token(), 'name' => 'Schulfest', 'starts_on' => '2026-09-10', 'ends_on' => '2026-09-10', 'change_reason' => 'Schulinterner Termin'])->assertRedirect();
    $this->actingAs($user)->post('/schuljahre/'.$year->slug.'/ferien/importieren', ['_token' => csrf_token()])->assertRedirect();

    $this->assertDatabaseHas('holiday_periods', ['school_year_id' => $year->id, 'name' => 'Schulfest', 'data_source_id' => null]);
    $this->assertDatabaseHas('school_year_days', ['school_year_id' => $year->id, 'date' => '2026-09-10', 'kind' => 'holiday']);
    Http::assertSentCount(2);
});
