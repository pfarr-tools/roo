<?php

use App\Models\Organization;
use App\Models\ResourceReference;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\TeachingGroup;
use App\Models\User;
use App\Services\TeachingUnitPublicViewResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('shows immediate phase material but hides lesson-timed material before its first concrete slot', function () {
    $organization = Organization::create(['name' => 'Public View Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id, 'name' => 'Erika Beispiel']);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Public View Schule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4a']);
    $unit = $group->teachingUnits()->create(['organization_id' => $organization->id, 'created_by_user_id' => $user->id, 'title' => 'Wasser des Lebens', 'position' => 1, 'introduction_text' => 'Wir arbeiten gemeinsam.']);
    $lesson = $unit->lessons()->create(['title' => 'Wasserbilder', 'position' => 1, 'duration' => 1]);
    $phase = $lesson->phases()->create(['title' => 'Arbeitsphase', 'position' => 1]);
    $immediate = ResourceReference::create(['organization_id' => $organization->id, 'teaching_unit_id' => $unit->id, 'lesson_id' => $lesson->id, 'original_name' => 'Sofort.pdf', 'storage_path' => 'units/sofort.pdf', 'security_status' => 'approved']);
    $timed = ResourceReference::create(['organization_id' => $organization->id, 'teaching_unit_id' => $unit->id, 'lesson_id' => $lesson->id, 'original_name' => 'Später.pdf', 'storage_path' => 'units/spaeter.pdf', 'security_status' => 'approved']);
    $phase->resources()->attach($immediate, ['publication_status' => 'shared_immediately']);
    $phase->resources()->attach($timed, ['publication_status' => 'shared_with_lesson']);
    $slot = ScheduleSlot::create(['teaching_group_id' => $group->id, 'date' => '2026-09-10', 'period_number' => 1, 'starts_at' => '08:00', 'ends_at' => '08:45', 'status' => 'occupied']);
    $lesson->scheduledLessons()->create(['schedule_slot_id' => $slot->id, 'status' => 'planned']);

    $before = app(TeachingUnitPublicViewResolver::class)->resolve($unit, CarbonImmutable::parse('2026-09-10 07:59', 'Europe/Berlin'));
    $after = app(TeachingUnitPublicViewResolver::class)->resolve($unit, CarbonImmutable::parse('2026-09-10 08:00', 'Europe/Berlin'));

    expect($before->visiblePhaseResources->pluck('original_name')->all())->toBe(['Sofort.pdf'])
        ->and($after->visiblePhaseResources->pluck('original_name')->all())->toBe(['Sofort.pdf', 'Später.pdf'])
        ->and($after->creator->is($user))->toBeTrue()
        ->and($after->scheduledLessons)->toHaveCount(1);
});

it('uses the earliest non-cancelled concrete slot and excludes direct unit materials', function () {
    $organization = Organization::create(['name' => 'Slot Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Slot Schule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4b']);
    $unit = $group->teachingUnits()->create(['organization_id' => $organization->id, 'created_by_user_id' => $user->id, 'title' => 'Slotprüfung', 'position' => 1]);
    $direct = ResourceReference::create(['organization_id' => $organization->id, 'teaching_unit_id' => $unit->id, 'original_name' => 'Direkt.pdf', 'storage_path' => 'units/direkt.pdf', 'security_status' => 'approved', 'publication_status' => 'shared_immediately']);
    $lesson = $unit->lessons()->create(['title' => 'Doppelstunde', 'position' => 1, 'duration' => 2]);
    $phase = $lesson->phases()->create(['title' => 'Einstieg', 'position' => 1]);
    $timed = ResourceReference::create(['organization_id' => $organization->id, 'teaching_unit_id' => $unit->id, 'lesson_id' => $lesson->id, 'original_name' => 'Phasenmaterial.pdf', 'storage_path' => 'units/phase.pdf', 'security_status' => 'approved']);
    $phase->resources()->attach($timed, ['publication_status' => 'shared_with_lesson']);
    $cancelledSlot = ScheduleSlot::create(['teaching_group_id' => $group->id, 'date' => '2026-09-10', 'period_number' => 1, 'starts_at' => '08:00', 'ends_at' => '08:45', 'status' => 'occupied']);
    $validSlot = ScheduleSlot::create(['teaching_group_id' => $group->id, 'date' => '2026-09-12', 'period_number' => 1, 'starts_at' => '09:00', 'ends_at' => '09:45', 'status' => 'occupied']);
    $lesson->scheduledLessons()->create(['schedule_slot_id' => $cancelledSlot->id, 'status' => 'cancelled']);
    $lesson->scheduledLessons()->create(['schedule_slot_id' => $validSlot->id, 'status' => 'planned']);

    $view = app(TeachingUnitPublicViewResolver::class)->resolve($unit, CarbonImmutable::parse('2026-09-12 08:59', 'Europe/Berlin'));
    $after = app(TeachingUnitPublicViewResolver::class)->resolve($unit, CarbonImmutable::parse('2026-09-12 09:00', 'Europe/Berlin'));

    expect($view->visiblePhaseResources)->toBeEmpty()
        ->and($after->visiblePhaseResources->pluck('original_name')->all())->toBe(['Phasenmaterial.pdf'])
        ->and($after->visiblePhaseResources->pluck('original_name')->contains($direct->original_name))->toBeFalse();
});
