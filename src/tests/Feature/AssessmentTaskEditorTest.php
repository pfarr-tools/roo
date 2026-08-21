<?php

use App\Models\AssessmentTask;
use App\Models\Organization;
use App\Models\ScheduleSlot;
use App\Models\ScheduledLesson;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\TeachingGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('öffnet die Prüfungsaufgabe als eigene Seite und schützt fremde Aufgaben', function () {
    $organization = Organization::create(['name' => 'Editor Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $otherUser = User::factory()->create(['organization_id' => Organization::create(['name' => 'Andere Organisation'])->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Editor Schule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => 'Editorgruppe']);
    $unit = $group->teachingUnits()->create(['organization_id' => $organization->id, 'title' => 'Einheit', 'position' => 1]);
    $lesson = $unit->lessons()->create(['title' => 'Stunde', 'position' => 1]);
    $slot = ScheduleSlot::create(['teaching_group_id' => $group->id, 'date' => '2026-09-08', 'period_number' => 1, 'starts_at' => '08:00', 'ends_at' => '08:45']);
    ScheduledLesson::create(['lesson_id' => $lesson->id, 'schedule_slot_id' => $slot->id]);
    $task = AssessmentTask::create(['organization_id' => $organization->id, 'teaching_unit_competency_id' => $unit->competencies()->create(['local_wording' => 'Kann erklären'])->id, 'title' => 'Erkläre']);
    $lesson->assessmentTasks()->attach($task);

    $this->actingAs($user)->get("/unterricht/{$slot->id}/pruefungsaufgaben/neu")
        ->assertInertia(fn ($page) => $page->component('AssessmentTask/Edit')->where('method', 'post'));
    $this->actingAs($user)->get("/unterricht/{$slot->id}/pruefungsaufgaben/{$task->id}/bearbeiten")
        ->assertInertia(fn ($page) => $page->component('AssessmentTask/Edit')->where('task.title', 'Erkläre'));
    $this->actingAs($otherUser)->get("/unterricht/{$slot->id}/pruefungsaufgaben/{$task->id}/bearbeiten")->assertForbidden();
});
