<?php

use App\Models\Lesson;
use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\ScheduleSlot;
use App\Models\ScheduledLesson;
use App\Models\TeachingGroup;
use App\Models\TeachingUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('provides a signed lesson calendar feed in the profile', function () {
    $organization = Organization::create(['name' => 'Kalenderorganisation']);
    $user = User::factory()->create(['organization_id' => $organization->id, 'name' => 'Max Mustermann']);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Roo-Schule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31', 'timezone' => 'Europe/Berlin']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '7a']);
    $unit = TeachingUnit::create(['organization_id' => $organization->id, 'teaching_group_id' => $group->id, 'title' => 'Schöpfung']);
    $lesson = Lesson::create(['teaching_unit_id' => $unit->id, 'title' => 'Die Welt als Geschenk', 'duration' => 1, 'position' => 1]);
    $slot = ScheduleSlot::create(['teaching_group_id' => $group->id, 'date' => '2026-09-15', 'period_number' => 1, 'starts_at' => '08:00', 'ends_at' => '08:45']);
    ScheduledLesson::create(['lesson_id' => $lesson->id, 'schedule_slot_id' => $slot->id, 'status' => ScheduledLesson::STATUS_PLANNED]);

    $this->actingAs($user)->get('/profil')->assertInertia(fn ($page) => $page
        ->where('user.name', 'Max Mustermann')
        ->where('calendarUrl', fn (string $url): bool => str_contains($url, '/kalender/'.$user->id.'/unterricht.ics') && str_contains($url, 'signature=')));

    $response = $this->get(URL::signedRoute('calendar.lessons', ['user' => $user->id]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=UTF-8')
        ->assertSee('7a')
        ->assertSee('Die Welt als Geschenk')
        ->assertSee('Schöpfung')
        ->assertSee('Unterricht in 7a');
});

it('rejects an unsigned lesson calendar feed', function () {
    $organization = Organization::create(['name' => 'Kalenderorganisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);

    $this->get('/kalender/'.$user->id.'/unterricht.ics')->assertForbidden();
});
