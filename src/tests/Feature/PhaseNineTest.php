<?php

use App\Models\AttendanceRecord;
use App\Models\Observation;
use App\Models\Organization;
use App\Models\ScheduleSlot;
use App\Models\ScheduledLesson;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\TeachingGroup;
use App\Models\User;
use App\Models\ObservationType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function observationFixture(): array
{
    $organization = Organization::create(['name' => 'Beobachtungsorganisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Beobachtungsschule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4a']);
    $student = Student::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'first_name' => 'Mia', 'last_name' => 'Muster', 'class_name' => '4a']);
    $group->students()->attach($student->id);
    $lesson = $group->teachingUnits()->create(['organization_id' => $organization->id, 'title' => 'Beobachtungsstunde', 'position' => 1])->lessons()->create(['title' => 'Stunde', 'position' => 1]);
    $slot = ScheduleSlot::create(['teaching_group_id' => $group->id, 'date' => '2026-09-08', 'period_number' => 1, 'starts_at' => '08:00', 'ends_at' => '08:45']);
    $scheduledLesson = ScheduledLesson::create(['lesson_id' => $lesson->id, 'schedule_slot_id' => $slot->id]);
    $type = ObservationType::create(['organization_id' => $organization->id, 'label' => 'Material fehlt', 'symbol' => 'M']);

    return compact('user', 'group', 'student', 'slot', 'scheduledLesson', 'type');
}

it('zeigt Schüler:innen und konfigurierbare Beobachtungstypen im Stundenarbeitsraum', function () {
    $fixture = observationFixture();

    $this->actingAs($fixture['user'])->get("/unterricht/{$fixture['slot']->id}")->assertInertia(fn ($page) => $page
        ->where('observationStudents.0.id', $fixture['student']->id)
        ->where('observationTypes.0.label', 'Material fehlt'));
});

it('speichert Anwesenheit und Beobachtungen nur für Schüler:innen der Gruppe', function () {
    $fixture = observationFixture();

    $this->actingAs($fixture['user'])->put("/unterricht/{$fixture['slot']->id}/beobachtungen", [
        'students' => [[
            'student_id' => $fixture['student']->id,
            'attendance' => 'late',
            'note' => 'Kam nach dem Ritual.',
            'observation_type_ids' => [$fixture['type']->id],
        ]],
    ])->assertRedirect();

    expect(AttendanceRecord::first()->status)->toBe('late')
        ->and(AttendanceRecord::first()->note)->toBe('Kam nach dem Ritual.')
        ->and(Observation::first()->observation_type_id)->toBe($fixture['type']->id);
});

it('verhindert Beobachtungen für fremde Gruppen', function () {
    $fixture = observationFixture();
    $foreignStudent = Student::create(['organization_id' => $fixture['user']->organization_id, 'school_id' => $fixture['group']->school_id, 'first_name' => 'Fremd', 'last_name' => 'Kind', 'class_name' => '4b']);

    $this->actingAs($fixture['user'])->put("/unterricht/{$fixture['slot']->id}/beobachtungen", [
        'students' => [['student_id' => $foreignStudent->id, 'attendance' => 'present']],
    ])->assertStatus(422);
    expect(AttendanceRecord::count())->toBe(0);
});

it('speichert den konfigurierbaren Beginn des zweiten Halbjahres', function () {
    $fixture = observationFixture();

    $this->actingAs($fixture['user'])->put("/schulen/{$fixture['group']->school->slug}/{$fixture['group']->schoolYear->slug}", [
        'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31', 'second_half_start_on' => '2027-02-01', 'timezone' => 'Europe/Berlin',
    ])->assertRedirect();

    expect($fixture['group']->schoolYear->fresh()->second_half_start_on->toDateString())->toBe('2027-02-01');
});
