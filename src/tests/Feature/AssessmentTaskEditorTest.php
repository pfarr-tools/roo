<?php

use App\Models\AssessmentTask;
use App\Models\EducationPlan;
use App\Models\EducationPlanCompetenceArea;
use App\Models\EducationPlanCompetency;
use App\Models\EducationPlanVersion;
use App\Models\ScheduledLesson;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\TeachingGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('öffnet die Prüfungsaufgabe als eigene Seite und schützt fremde Aufgaben', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $school = School::create(['user_id' => $user->id, 'name' => 'Editor Schule']);
    $year = SchoolYear::create(['user_id' => $user->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['user_id' => $user->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => 'Editorgruppe']);
    $unit = $group->teachingUnits()->create(['user_id' => $user->id, 'title' => 'Einheit', 'position' => 1]);
    $lesson = $unit->lessons()->create(['title' => 'Stunde', 'position' => 1]);
    $slot = ScheduleSlot::create(['teaching_group_id' => $group->id, 'date' => '2026-09-08', 'period_number' => 1, 'starts_at' => '08:00', 'ends_at' => '08:45']);
    ScheduledLesson::create(['lesson_id' => $lesson->id, 'schedule_slot_id' => $slot->id]);
    $competency = officialCompetency($unit, 'Kann erklären');
    $task = AssessmentTask::create(['user_id' => $user->id, 'education_plan_id' => $competency->area->version->education_plan_id, 'education_plan_competency_id' => $competency->id, 'title' => 'Erkläre']);
    $lesson->assessmentTasks()->attach($task);

    $this->actingAs($user)->get("/unterricht/{$slot->id}/pruefungsaufgaben/neu")
        ->assertInertia(fn ($page) => $page->component('AssessmentTask/Edit')->where('method', 'post'));
    $this->actingAs($user)->get("/unterricht/{$slot->id}/pruefungsaufgaben/{$task->id}/bearbeiten")
        ->assertInertia(fn ($page) => $page->component('AssessmentTask/Edit')->where('task.title', 'Erkläre'));
    $this->actingAs($otherUser)->get("/unterricht/{$slot->id}/pruefungsaufgaben/{$task->id}/bearbeiten")->assertForbidden();
});

it('erlaubt Erwartungslisten nur mit Prozesskompetenzen', function () {
    $user = User::factory()->create();
    $plan = EducationPlan::create(['user_id' => null, 'external_identifier' => 'BP', 'subject' => 'Religion', 'title' => 'Bildungsplan']);
    $version = EducationPlanVersion::create(['education_plan_id' => $plan->id, 'external_identifier' => '2026', 'schema_version' => '1', 'title' => '2026', 'is_complete' => true, 'raw_payload' => []]);
    $processArea = EducationPlanCompetenceArea::create(['education_plan_version_id' => $version->id, 'kind' => 'process', 'external_identifier' => '2.1', 'title' => 'Prozess', 'position' => 1]);
    $contentArea = EducationPlanCompetenceArea::create(['education_plan_version_id' => $version->id, 'kind' => 'content', 'external_identifier' => '3.1', 'title' => 'Inhalt', 'position' => 2]);
    $process = EducationPlanCompetency::create(['education_plan_competence_area_id' => $processArea->id, 'external_identifier' => '2.1.1', 'text' => 'Wahrnehmen', 'position' => 1, 'is_active' => true]);
    $content = EducationPlanCompetency::create(['education_plan_competence_area_id' => $contentArea->id, 'external_identifier' => '3.1.1', 'text' => 'Deuten', 'position' => 1, 'is_active' => true]);

    expect(fn () => AssessmentTask::create(['user_id' => $user->id, 'education_plan_id' => $plan->id, 'education_plan_competency_id' => $content->id, 'task_type' => 'expectation_list', 'title' => 'Ungültig']))
        ->toThrow(ValidationException::class);

    $task = AssessmentTask::create(['user_id' => $user->id, 'education_plan_id' => $plan->id, 'education_plan_competency_id' => $process->id, 'task_type' => 'expectation_list', 'title' => 'Ordnereinsicht']);
    $task->expectations()->create(['text' => 'Der Ordner ist vollständig.', 'points' => 1, 'repetitions' => 1, 'position' => 1]);

    expect($task->maximumPoints())->toBe(1);
});

it('liefert Kompetenz und Bildungsplan für eine neue Prüfungsaufgabe vor', function () {
    $user = User::factory()->create();
    $school = School::create(['user_id' => $user->id, 'name' => 'Vorauswahl Schule']);
    $year = SchoolYear::create(['user_id' => $user->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['user_id' => $user->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => 'Vorauswahlgruppe']);
    $unit = $group->teachingUnits()->create(['user_id' => $user->id, 'title' => 'Einheit', 'position' => 1]);
    $lesson = $unit->lessons()->create(['title' => 'Stunde', 'position' => 1]);
    $slot = ScheduleSlot::create(['teaching_group_id' => $group->id, 'date' => '2026-09-08', 'period_number' => 1, 'starts_at' => '08:00', 'ends_at' => '08:45']);
    ScheduledLesson::create(['lesson_id' => $lesson->id, 'schedule_slot_id' => $slot->id]);
    $plan = EducationPlan::create(['user_id' => null, 'external_identifier' => 'BP', 'subject' => 'Religion', 'title' => 'Bildungsplan']);
    $version = EducationPlanVersion::create(['education_plan_id' => $plan->id, 'external_identifier' => '2026', 'schema_version' => '1', 'title' => '2026', 'is_complete' => true, 'raw_payload' => []]);
    $area = EducationPlanCompetenceArea::create(['education_plan_version_id' => $version->id, 'kind' => 'content', 'external_identifier' => '3.1', 'title' => 'Inhalt', 'position' => 1]);
    $competency = EducationPlanCompetency::create(['education_plan_competence_area_id' => $area->id, 'external_identifier' => '3.1.1', 'text' => 'Kann unterscheiden', 'position' => 1, 'is_active' => true]);
    $unit->educationPlanCompetencies()->syncWithoutDetaching([$competency->id]);
    $lesson->educationPlanCompetencies()->attach($competency->id);

    $this->actingAs($user)->get("/unterricht/{$slot->id}/pruefungsaufgaben/neu?education_plan_id={$plan->id}&education_plan_competency_id={$competency->id}")
        ->assertInertia(fn ($page) => $page->component('AssessmentTask/Edit')
            ->where('initialEducationPlanId', $plan->id)
            ->where('initialCompetency.id', $competency->id)
            ->where('initialCompetency.text', 'Kann unterscheiden'));
});

it('speichert den Checkbox-Bewertungsmodus beim Aktualisieren einer Prüfungsaufgabe', function () {
    $user = User::factory()->create();
    $school = School::create(['user_id' => $user->id, 'name' => 'Bewertungsmodus Schule']);
    $year = SchoolYear::create(['user_id' => $user->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['user_id' => $user->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => 'Bewertungsmodusgruppe']);
    $unit = $group->teachingUnits()->create(['user_id' => $user->id, 'title' => 'Einheit', 'position' => 1]);
    $lesson = $unit->lessons()->create(['title' => 'Stunde', 'position' => 1]);
    $slot = ScheduleSlot::create(['teaching_group_id' => $group->id, 'date' => '2026-09-08', 'period_number' => 1, 'starts_at' => '08:00', 'ends_at' => '08:45']);
    ScheduledLesson::create(['lesson_id' => $lesson->id, 'schedule_slot_id' => $slot->id]);
    $plan = EducationPlan::create(['user_id' => $user->id, 'external_identifier' => 'BP', 'subject' => 'Religion', 'title' => 'Bildungsplan']);
    $version = EducationPlanVersion::create(['education_plan_id' => $plan->id, 'external_identifier' => '2026', 'schema_version' => '1', 'title' => '2026', 'is_complete' => true, 'raw_payload' => []]);
    $area = EducationPlanCompetenceArea::create(['education_plan_version_id' => $version->id, 'kind' => 'content', 'external_identifier' => '3.1', 'title' => 'Inhalt', 'position' => 1]);
    $competency = EducationPlanCompetency::create(['education_plan_competence_area_id' => $area->id, 'external_identifier' => '3.1.1', 'text' => 'Kann unterscheiden', 'position' => 1, 'is_active' => true]);
    $task = AssessmentTask::create(['user_id' => $user->id, 'education_plan_id' => $plan->id, 'education_plan_competency_id' => $competency->id, 'title' => 'Auswahl']);
    $lesson->assessmentTasks()->attach($task);

    $this->actingAs($user)->put(route('lessons.assessment-tasks.update', [$slot, $task]), [
        'education_plan_id' => $plan->id,
        'education_plan_competency_id' => $competency->id,
        'title' => 'Auswahl',
        'task_type' => 'checkbox',
        'content' => [
            'options' => collect(range(1, 5))->map(fn ($number) => ['id' => "option-{$number}", 'text' => "Option {$number}", 'correct' => $number === 1])->all(),
            'points_per_correct_answer' => 2,
            'checkbox_scoring_mode' => 'correct_states',
        ],
        'expectations' => [],
        'levels' => [],
    ])->assertRedirect();

    expect($task->fresh()->content['checkbox_scoring_mode'])->toBe('correct_states')
        ->and($task->fresh()->maximumPoints())->toBe(10);
});
