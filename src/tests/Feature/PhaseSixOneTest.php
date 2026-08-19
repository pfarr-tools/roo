<?php

use App\Models\Curriculum;
use App\Models\CurriculumTopic;
use App\Models\CurriculumTopicCompetency;
use App\Models\CurriculumVersion;
use App\Models\EducationPlan;
use App\Models\EducationPlanCompetenceArea;
use App\Models\EducationPlanCompetency;
use App\Models\EducationPlanVersion;
use App\Models\Lesson;
use App\Models\LessonTemplate;
use App\Models\Organization;
use App\Models\ScheduledLesson;
use App\Models\PhaseTemplate;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\SchoolPeriod;
use App\Models\SocialForm;
use App\Models\SchoolYear;
use App\Models\TeachingGroup;
use App\Models\TeachingUnit;
use App\Models\UnitTemplate;
use App\Models\User;
use App\Services\YearPlanningWorkspace;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([PreventRequestForgery::class]);
});

function phaseSixOneGroup(): array
{
    $organization = Organization::create(['name' => 'Phase 6.1 Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Arbeitsbereichschule']);
    $year = SchoolYear::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30']);
    $group = TeachingGroup::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4a Religion']);
    $period = SchoolPeriod::create(['school_id' => $school->id, 'period_number' => 1, 'starts_at' => '08:00', 'ends_at' => '08:45']);
    $group->schoolPeriods()->attach($period->id, ['weekday' => 2]);

    return [$user, $group, $school, $year];
}

it('übernimmt eine Curriculum-UE als unabhängige eigene UE mit Herkunft', function () {
    [$user, $group] = phaseSixOneGroup();
    $curriculum = Curriculum::create(['organization_id' => $user->organization_id, 'title' => 'Curriculum Religion']);
    $group->curricula()->attach($curriculum->id, ['role' => 'primary']);
    $version = CurriculumVersion::create(['curriculum_id' => $curriculum->id, 'external_identifier' => 'v1', 'is_editable' => false, 'is_complete' => true]);
    $topic = CurriculumTopic::create(['curriculum_version_id' => $version->id, 'title' => 'Nach Gott fragen', 'hours' => 2, 'position' => 1]);
    $plan = EducationPlan::create(['organization_id' => $user->organization_id, 'external_identifier' => 'BP', 'subject' => 'Religion', 'title' => 'Bildungsplan']);
    $planVersion = EducationPlanVersion::create(['education_plan_id' => $plan->id, 'external_identifier' => '2026', 'schema_version' => '1', 'title' => '2026', 'is_complete' => true, 'raw_payload' => []]);
    $area = EducationPlanCompetenceArea::create(['education_plan_version_id' => $planVersion->id, 'kind' => 'content', 'external_identifier' => 'religion', 'title' => 'Religion', 'position' => 1]);
    $competency = EducationPlanCompetency::create(['education_plan_competence_area_id' => $area->id, 'external_identifier' => 'K1', 'text' => 'Fragen stellen', 'position' => 1, 'is_active' => true]);
    CurriculumTopicCompetency::create(['curriculum_topic_id' => $topic->id, 'education_plan_competency_id' => $competency->id, 'competency_kind' => 'content', 'display' => 'Fragen stellen', 'position' => 1]);

    $this->actingAs($user)->post("/jahresplanung/{$group->id}/curriculum-themen/{$topic->id}/uebernehmen")->assertRedirect();

    $unit = TeachingUnit::firstOrFail();
    expect($unit->source_curriculum_topic_id)->toBe($topic->id)
        ->and($unit->lessons)->toHaveCount(2)
        ->and($unit->competencies->first()->education_plan_competency_id)->toBe($competency->id);
    $topic->refresh();
    expect($topic->title)->toBe('Nach Gott fragen')->and($topic->hours)->toBe(2);
});

it('erzeugt Slots ohne schulfreie Tage und plant eine mehrstündige Lesson', function () {
    [$user, $group, , $year] = phaseSixOneGroup();
    $year->days()->create(['date' => '2026-09-08', 'kind' => 'no_instruction', 'label' => 'Ferientag']);
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/eigene-einheiten", ['title' => 'Eigene UE'])->assertRedirect();
    $unit = TeachingUnit::firstOrFail();
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/eigene-einheiten/{$unit->id}/stunden", ['title' => 'Doppelstunde', 'duration' => 2])->assertRedirect();
    $lesson = $unit->lessons()->firstOrFail();

    $this->actingAs($user)->get("/jahresplanung/{$group->id}")->assertOk();
    expect(ScheduleSlot::where('date', '2026-09-08')->count())->toBe(0);
    $slot = ScheduleSlot::orderBy('date')->firstOrFail();
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/lessons/{$lesson->id}/einplanen", ['schedule_slot_id' => $slot->id])->assertRedirect();
    expect(ScheduledLesson::where('lesson_id', $lesson->id)->count())->toBe(2);
});

it('verschiebt eine geplante Lesson beim Ausfall auf den nächsten freien Slot', function () {
    [$user, $group] = phaseSixOneGroup();
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/eigene-einheiten", ['title' => 'Reflow UE']);
    $unit = TeachingUnit::firstOrFail();
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/eigene-einheiten/{$unit->id}/stunden", ['title' => 'Stunde', 'duration' => 1]);
    $lesson = Lesson::firstOrFail();
    $this->actingAs($user)->get("/jahresplanung/{$group->id}");
    $slot = ScheduleSlot::orderBy('date')->firstOrFail();
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/lessons/{$lesson->id}/einplanen", ['schedule_slot_id' => $slot->id]);
    $this->actingAs($user)->put("/jahresplanung/{$group->id}/slots/{$slot->id}", ['status' => 'absent'])->assertRedirect();

    expect(ScheduledLesson::firstOrFail()->schedule_slot_id)->not->toBe($slot->id)
        ->and($slot->fresh()->status)->toBe('absent');

    $this->actingAs($user)->post("/jahresplanung/{$group->id}/reflow/rueckgaengig")->assertRedirect();
    expect(ScheduledLesson::firstOrFail()->schedule_slot_id)->toBe($slot->id)
        ->and($slot->fresh()->status)->toBe('free');
});

it('ordnet den Jahresplan beim Sperren und Freigeben eines Slots neu', function () {
    [$user, $group] = phaseSixOneGroup();
    $unit = $group->teachingUnits()->create(['organization_id' => $user->organization_id, 'title' => 'Reflow UE', 'position' => 1]);
    $firstLesson = $unit->lessons()->create(['title' => 'Erste Stunde', 'position' => 1, 'duration' => 1]);
    $secondLesson = $unit->lessons()->create(['title' => 'Zweite Stunde', 'position' => 2, 'duration' => 1]);

    $this->actingAs($user)->get("/jahresplanung/{$group->id}");
    $slots = ScheduleSlot::orderBy('date')->get();
    app(YearPlanningWorkspace::class)->scheduleLesson($group, $firstLesson, $slots[0]);
    app(YearPlanningWorkspace::class)->scheduleLesson($group, $secondLesson, $slots[1]);

    $this->actingAs($user)->put("/jahresplanung/{$group->id}/slots/{$slots[0]->id}", ['status' => 'absent'])->assertRedirect();
    expect(ScheduledLesson::where('schedule_slot_id', $slots[1]->id)->value('lesson_id'))->toBe($firstLesson->id)
        ->and(ScheduledLesson::where('schedule_slot_id', $slots[2]->id)->value('lesson_id'))->toBe($secondLesson->id);

    $this->actingAs($user)->put("/jahresplanung/{$group->id}/slots/{$slots[0]->id}", ['status' => 'free'])->assertRedirect();
    expect(ScheduledLesson::where('schedule_slot_id', $slots[0]->id)->value('lesson_id'))->toBe($firstLesson->id)
        ->and(ScheduledLesson::where('schedule_slot_id', $slots[1]->id)->value('lesson_id'))->toBe($secondLesson->id);
});

it('verwaltet den Vorbereitungsstand einer konkreten Einplanung', function () {
    [$user, $group] = phaseSixOneGroup();
    $unit = $group->teachingUnits()->create(['organization_id' => $user->organization_id, 'title' => 'Status UE', 'position' => 1]);
    $lesson = $unit->lessons()->create(['title' => 'Statusstunde', 'position' => 1, 'duration' => 1]);
    $this->actingAs($user)->get("/jahresplanung/{$group->id}");
    $slot = ScheduleSlot::firstOrFail();
    app(YearPlanningWorkspace::class)->scheduleLesson($group, $lesson, $slot);
    $scheduled = ScheduledLesson::firstOrFail();
    $this->actingAs($user)->get("/unterricht/{$slot->id}")->assertOk();
    expect($scheduled->status)->toBe('assigned');
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/lessons/{$lesson->id}/phasen", ['title' => 'Einstieg'])->assertRedirect();
    expect($scheduled->fresh()->status)->toBe('planned');
    $phase = $lesson->phases()->firstOrFail();
    $this->actingAs($user)->put("/jahresplanung/{$group->id}/lessons/{$lesson->id}", ['title' => $lesson->title, 'duration' => 1, 'phases' => [['id' => $phase->id, 'title' => 'Einstieg', 'duration_minutes' => 15, 'social_form' => 'Plenum']]])->assertRedirect();
    expect(SocialForm::where('organization_id', $user->organization_id)->where('name', 'Plenum')->exists())->toBeTrue()
        ->and($phase->fresh()->duration_minutes)->toBe(15);
    $this->actingAs($user)->put("/jahresplanung/{$group->id}/geplante-stunden/{$scheduled->id}/status", ['status' => 'ready'])->assertRedirect();
    expect($scheduled->fresh()->status)->toBe('ready');
    $this->actingAs($user)->put("/unterricht/{$slot->id}/durchfuehrung", ['status' => 'conducted', 'actual_on' => '2026-09-02', 'execution_notes' => 'Gut verlaufen'])->assertRedirect();
    expect($scheduled->fresh()->actual_on->toDateString())->toBe('2026-09-02')->and($scheduled->fresh()->execution_notes)->toBe('Gut verlaufen');
    $this->actingAs($user)->put("/jahresplanung/{$group->id}/geplante-stunden/{$scheduled->id}/status", ['status' => 'unknown'])->assertSessionHasErrors('status');
});

it('legt Phasen aus Vorlagen an, sortiert sie und schützt fremde Phasen', function () {
    [$user, $group] = phaseSixOneGroup();
    $unit = $group->teachingUnits()->create(['organization_id' => $user->organization_id, 'title' => 'Phasen UE', 'position' => 1]);
    $lesson = $unit->lessons()->create(['title' => 'Phasenstunde', 'position' => 1, 'duration' => 1]);
    $unitTemplate = UnitTemplate::create(['organization_id' => $user->organization_id, 'title' => 'Vorlagen UE', 'expected_hours' => 1, 'version' => 1, 'is_active' => true]);
    $templateLesson = LessonTemplate::create(['organization_id' => $user->organization_id, 'unit_template_id' => $unitTemplate->id, 'title' => 'Vorlagenstunde', 'version' => 1, 'is_active' => true]);
    $template = PhaseTemplate::create(['organization_id' => $user->organization_id, 'lesson_template_id' => $templateLesson->id, 'title' => 'Ritual', 'version' => 1, 'is_active' => true]);

    $this->actingAs($user)->post("/jahresplanung/{$group->id}/lessons/{$lesson->id}/phasen", ['phase_template_id' => $template->id])->assertRedirect();
    $phase = $lesson->phases()->firstOrFail();
    expect($phase->title)->toBe('Ritual');
    $phase->update(['teacher_interaction' => 'Lehrkraft begrüßt die Gruppe.', 'learner_activity' => 'Die S:innen kommen an.', 'differentiation' => 'Bildkarten liegen bereit.', 'didactic_comment' => 'Ritualisierter Einstieg.', 'media' => 'Bildkarten']);
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/phasen/{$phase->id}/als-vorlage")->assertRedirect();
    expect(PhaseTemplate::where('title', 'Ritual')->count())->toBe(2)
        ->and(PhaseTemplate::where('title', 'Ritual')->latest('id')->value('teacher_interaction'))->toBe('Lehrkraft begrüßt die Gruppe.');

    $second = $lesson->phases()->create(['title' => 'Sicherung', 'position' => 2]);
    $this->actingAs($user)->put("/jahresplanung/{$group->id}/lessons/{$lesson->id}/phasen/reihenfolge", ['phase_ids' => [$second->id, $phase->id]])->assertRedirect();
    expect($phase->fresh()->position)->toBe(2);
});

it('ergänzt Gruppenrituale beim Einplanen automatisch als geplante Phasen', function () {
    [$user, $group] = phaseSixOneGroup();
    $unit = $group->teachingUnits()->create(['organization_id' => $user->organization_id, 'title' => 'Ritual UE', 'position' => 1]);
    $lesson = $unit->lessons()->create(['title' => 'Neue Stunde', 'position' => 1, 'duration' => 1]);
    $unitTemplate = UnitTemplate::create(['organization_id' => $user->organization_id, 'title' => 'Ritualvorlagen', 'expected_hours' => 1, 'version' => 1, 'is_active' => true]);
    $lessonTemplate = LessonTemplate::create(['organization_id' => $user->organization_id, 'unit_template_id' => $unitTemplate->id, 'title' => 'Ritualstunde', 'version' => 1, 'is_active' => true]);
    $template = PhaseTemplate::create(['organization_id' => $user->organization_id, 'lesson_template_id' => $lessonTemplate->id, 'title' => 'Ankommensritual', 'duration_minutes' => 5, 'version' => 1, 'is_active' => true]);
    $group->gradeLevels()->create(['grade_level' => '4']);
    $this->actingAs($user)->put("/unterrichtsgruppen/{$group->id}", [
        'school_id' => $group->school_id,
        'school_year_id' => $group->school_year_id,
        'name' => $group->name,
        'grade_levels' => ['4'],
        'phase_template_ids' => [$template->id],
    ])->assertRedirect();

    $this->actingAs($user)->get("/jahresplanung/{$group->id}");
    $firstSlot = ScheduleSlot::firstOrFail();
    app(YearPlanningWorkspace::class)->scheduleLesson($group, $lesson, $firstSlot);

    $phase = $lesson->phases()->firstOrFail();
    expect($phase->phase_template_id)->toBe($template->id)
        ->and($phase->duration_minutes)->toBe(5)
        ->and(ScheduledLesson::firstOrFail()->status)->toBe(ScheduledLesson::STATUS_PLANNED);

    $secondLesson = $unit->lessons()->create(['title' => 'Direkt anschließende Stunde', 'position' => 2, 'duration' => 1]);
    app(YearPlanningWorkspace::class)->scheduleLesson($group, $secondLesson, ScheduleSlot::orderBy('date')->orderBy('period_number')->skip(1)->firstOrFail());
    expect($secondLesson->phases()->count())->toBe(0);
});

it('behandelt das Rückgängigmachen ohne Verschiebung als folgenlose Anfrage', function () {
    [$user, $group] = phaseSixOneGroup();

    $this->actingAs($user)
        ->get("/jahresplanung/{$group->id}")
        ->assertInertia(fn ($page) => $page->where('canUndoReflow', false));

    $this->actingAs($user)
        ->post("/jahresplanung/{$group->id}/reflow/rueckgaengig")
        ->assertRedirect()
        ->assertSessionHas('warning', 'Keine rückgängig machbare Verschiebung vorhanden.');
});

it('verschiebt eine komplette eigene UE beim erneuten Einplanen ohne Doppelbelegung', function () {
    [$user, $group] = phaseSixOneGroup();
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/eigene-einheiten", ['title' => 'Verschiebbare UE']);
    $unit = TeachingUnit::firstOrFail();
    foreach (['Erste Stunde', 'Zweite Stunde'] as $title) {
        $this->actingAs($user)->post("/jahresplanung/{$group->id}/eigene-einheiten/{$unit->id}/stunden", ['title' => $title, 'duration' => 1]);
    }
    $this->actingAs($user)->get("/jahresplanung/{$group->id}");
    $slots = ScheduleSlot::orderBy('date')->get();
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/eigene-einheiten/{$unit->id}/einplanen", ['schedule_slot_id' => $slots[0]->id]);
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/eigene-einheiten/{$unit->id}/einplanen", ['schedule_slot_id' => $slots[2]->id]);

    expect(ScheduledLesson::whereIn('lesson_id', $unit->lessons()->pluck('id'))->count())->toBe(2)
        ->and(ScheduledLesson::where('schedule_slot_id', $slots[0]->id)->count())->toBe(0);
});

it('entfernt Lesson- und UE-Belegungen ohne die eigene Planung zu löschen', function () {
    [$user, $group] = phaseSixOneGroup();
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/eigene-einheiten", ['title' => 'Entfernbare UE']);
    $unit = TeachingUnit::firstOrFail();
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/eigene-einheiten/{$unit->id}/stunden", ['title' => 'Entfernbare Stunde', 'duration' => 1]);
    $lesson = $unit->lessons()->firstOrFail();
    $this->actingAs($user)->get("/jahresplanung/{$group->id}");
    $slot = ScheduleSlot::firstOrFail();
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/lessons/{$lesson->id}/einplanen", ['schedule_slot_id' => $slot->id]);
    $this->actingAs($user)->delete("/jahresplanung/{$group->id}/lessons/{$lesson->id}/einplanung")->assertRedirect();

    expect(ScheduledLesson::count())->toBe(0)->and(TeachingUnit::find($unit->id))->not->toBeNull();
});


it('zeigt im Jahresplan nur Curriculum-UEs der Gruppenjahrgänge', function () {
    [$user, $group] = phaseSixOneGroup();
    $group->gradeLevels()->create(['grade_level' => '4a']);
    $curriculum = Curriculum::create(['organization_id' => $user->organization_id, 'title' => 'Jahrgangs-Curriculum']);
    $group->curricula()->attach($curriculum->id, ['role' => 'primary']);
    $version = CurriculumVersion::create(['curriculum_id' => $curriculum->id, 'external_identifier' => 'v1', 'is_editable' => false, 'is_complete' => true]);
    CurriculumTopic::create(['curriculum_version_id' => $version->id, 'title' => 'Passende UE', 'year' => 4, 'position' => 1]);
    CurriculumTopic::create(['curriculum_version_id' => $version->id, 'title' => 'Fremde UE', 'year' => 5, 'position' => 2]);

    $this->actingAs($user)->get("/jahresplanung/{$group->id}")
        ->assertInertia(fn ($page) => $page
            ->has('workspace.curricula.0.versions.0.topics', 1)
            ->where('workspace.curricula.0.versions.0.topics.0.title', 'Passende UE'));
});

it('öffnet die zuletzt verwendete Jahresplanungsgruppe direkt', function () {
    [$user, $group] = phaseSixOneGroup();

    $this->actingAs($user)->get('/jahresplanung')->assertRedirect("/jahresplanung/{$group->id}");
    $this->actingAs($user)->get("/jahresplanung/{$group->id}")->assertOk();
    expect($user->fresh()->last_year_plan_teaching_group_id)->toBe($group->id);

    $secondGroup = TeachingGroup::create([
        'organization_id' => $user->organization_id,
        'school_id' => $group->school_id,
        'school_year_id' => $group->school_year_id,
        'name' => 'Zweite Gruppe',
    ]);
    $this->actingAs($user)->get("/jahresplanung/{$secondGroup->id}")->assertOk();
    $this->actingAs($user)->get('/jahresplanung')->assertRedirect("/jahresplanung/{$secondGroup->id}");
});

it('fügt Stunden in belegte Slots ein und kann getrennte Teile wieder zusammenführen', function () {
    [$user, $group] = phaseSixOneGroup();
    $unitA = $group->teachingUnits()->create(['organization_id' => $user->organization_id, 'title' => 'Zusammenhängende UE', 'position' => 1]);
    $lessonA = $unitA->lessons()->create(['title' => 'Dreiteilige Stunde', 'position' => 1, 'duration' => 3]);
    $unitB = $group->teachingUnits()->create(['organization_id' => $user->organization_id, 'title' => 'Einfüge-UE', 'position' => 2]);
    $lessonB = $unitB->lessons()->create(['title' => 'Zwischenstunde', 'position' => 1, 'duration' => 1]);

    $this->actingAs($user)->get("/jahresplanung/{$group->id}");
    $slots = ScheduleSlot::orderBy('date')->get();
    app(YearPlanningWorkspace::class)->scheduleLesson($group, $lessonA, $slots[0]);
    app(YearPlanningWorkspace::class)->scheduleLesson($group, $lessonB, $slots[3]);

    $this->actingAs($user)->post("/jahresplanung/{$group->id}/slots/{$slots[1]->id}/einfügen", ['type' => 'unit', 'source_id' => $unitB->id])->assertRedirect();
    expect(ScheduledLesson::where('schedule_slot_id', $slots[0]->id)->value('lesson_id'))->toBe($lessonA->id)
        ->and(ScheduledLesson::where('schedule_slot_id', $slots[1]->id)->value('lesson_id'))->toBe($lessonB->id)
        ->and(ScheduledLesson::where('schedule_slot_id', $slots[2]->id)->value('lesson_id'))->toBe($lessonA->id)
        ->and(ScheduledLesson::where('schedule_slot_id', $slots[3]->id)->value('lesson_id'))->toBe($lessonA->id);

    $this->actingAs($user)->post("/jahresplanung/{$group->id}/slots/{$slots[3]->id}/einfügen", ['type' => 'unit', 'source_id' => $unitB->id])->assertRedirect();
    expect(ScheduledLesson::where('schedule_slot_id', $slots[0]->id)->value('lesson_id'))->toBe($lessonA->id)
        ->and(ScheduledLesson::where('schedule_slot_id', $slots[1]->id)->value('lesson_id'))->toBe($lessonA->id)
        ->and(ScheduledLesson::where('schedule_slot_id', $slots[2]->id)->value('lesson_id'))->toBe($lessonA->id)
        ->and(ScheduledLesson::where('schedule_slot_id', $slots[3]->id)->value('lesson_id'))->toBe($lessonB->id);
});

it('nutzt freie Plätze ohne unnötiges Verschieben und unterstützt Nachrücken, Fixierungen und Entfernen', function () {
    [$user, $group] = phaseSixOneGroup();
    $unitA = $group->teachingUnits()->create(['organization_id' => $user->organization_id, 'title' => 'Bestehende UE', 'position' => 1]);
    $lessonA = $unitA->lessons()->create(['title' => 'Bestehende Stunde', 'position' => 1, 'duration' => 1]);
    $unitB = $group->teachingUnits()->create(['organization_id' => $user->organization_id, 'title' => 'Neue UE', 'position' => 2]);
    $lessonB = $unitB->lessons()->create(['title' => 'Neue Stunde', 'position' => 1, 'duration' => 1]);

    $this->actingAs($user)->get("/jahresplanung/{$group->id}");
    $slots = ScheduleSlot::orderBy('date')->get();
    app(YearPlanningWorkspace::class)->scheduleLesson($group, $lessonA, $slots[1]);
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/slots/{$slots[0]->id}/einfügen", ['type' => 'lesson', 'source_id' => $lessonB->id])->assertRedirect();
    expect(ScheduledLesson::where('schedule_slot_id', $slots[0]->id)->value('lesson_id'))->toBe($lessonB->id)
        ->and(ScheduledLesson::where('schedule_slot_id', $slots[1]->id)->value('lesson_id'))->toBe($lessonA->id);

    $this->actingAs($user)->delete("/jahresplanung/{$group->id}/lessons/{$lessonB->id}/einplanung", ['move_following' => true])->assertRedirect();
    expect(ScheduledLesson::where('schedule_slot_id', $slots[0]->id)->value('lesson_id'))->toBe($lessonA->id);

    $this->actingAs($user)->put("/jahresplanung/{$group->id}/slots/{$slots[0]->id}", ['status' => 'absent', 'reflow_mode' => 'remove'])->assertRedirect();
    expect(ScheduledLesson::where('lesson_id', $lessonA->id)->count())->toBe(0);

    $this->actingAs($user)->put("/jahresplanung/{$group->id}/slots/{$slots[1]->id}", ['status' => 'free', 'is_pinned' => true])->assertStatus(422);
    app(YearPlanningWorkspace::class)->scheduleLesson($group, $lessonB, $slots[1]);
    $this->actingAs($user)->put("/jahresplanung/{$group->id}/slots/{$slots[1]->id}", ['status' => 'free', 'is_pinned' => true])->assertRedirect();
    expect($slots[1]->fresh()->is_pinned)->toBeTrue();
    $this->actingAs($user)->put("/jahresplanung/{$group->id}/slots/{$slots[1]->id}", ['status' => 'free', 'is_pinned' => false])->assertRedirect();
    expect($slots[1]->fresh()->is_pinned)->toBeFalse();
});

it('verschiebt Inhalte um fixierte Stunden herum', function () {
    [$user, $group] = phaseSixOneGroup();
    $units = collect(['Erste UE', 'Fixierte UE', 'Zu verschiebende UE'])->map(function (string $title, int $index) use ($group, $user) {
        $unit = $group->teachingUnits()->create(['organization_id' => $user->organization_id, 'title' => $title, 'position' => $index + 1]);
        $lesson = $unit->lessons()->create(['title' => $title.' – Stunde', 'position' => 1, 'duration' => 1]);

        return $lesson;
    });
    $this->actingAs($user)->get("/jahresplanung/{$group->id}");
    $slots = ScheduleSlot::orderBy('date')->get();
    foreach ([0, 1, 3] as $index => $slotIndex) {
        app(YearPlanningWorkspace::class)->scheduleLesson($group, $units[$index], $slots[$slotIndex]);
    }
    $this->actingAs($user)->put("/jahresplanung/{$group->id}/slots/{$slots[1]->id}", ['status' => 'free', 'is_pinned' => true])->assertRedirect();
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/slots/{$slots[0]->id}/einfügen", ['type' => 'lesson', 'source_id' => $units[2]->id])->assertRedirect();

    expect(ScheduledLesson::where('lesson_id', $units[1]->id)->value('schedule_slot_id'))->toBe($slots[1]->id)
        ->and(ScheduledLesson::where('lesson_id', $units[2]->id)->value('schedule_slot_id'))->toBe($slots[0]->id)
        ->and(ScheduledLesson::where('lesson_id', $units[0]->id)->value('schedule_slot_id'))->toBe($slots[2]->id);
});

it('verschiebt eine UE um ihre fixierte Stunde herum', function () {
    [$user, $group] = phaseSixOneGroup();
    $fixedUnit = $group->teachingUnits()->create(['organization_id' => $user->organization_id, 'title' => 'Teilfixierte UE', 'position' => 1]);
    $fixedLesson = $fixedUnit->lessons()->create(['title' => 'Fixierte Stunde', 'position' => 1, 'duration' => 1]);
    $movableLesson = $fixedUnit->lessons()->create(['title' => 'Verschiebbare Stunde', 'position' => 2, 'duration' => 1]);
    $otherUnit = $group->teachingUnits()->create(['organization_id' => $user->organization_id, 'title' => 'Andere UE', 'position' => 2]);
    $otherLesson = $otherUnit->lessons()->create(['title' => 'Andere Stunde', 'position' => 1, 'duration' => 1]);
    $this->actingAs($user)->get("/jahresplanung/{$group->id}");
    $slots = ScheduleSlot::orderBy('date')->get();
    app(YearPlanningWorkspace::class)->scheduleLesson($group, $otherLesson, $slots[0]);
    app(YearPlanningWorkspace::class)->scheduleLesson($group, $fixedLesson, $slots[1]);
    app(YearPlanningWorkspace::class)->scheduleLesson($group, $movableLesson, $slots[3]);
    $this->actingAs($user)->put("/jahresplanung/{$group->id}/slots/{$slots[1]->id}", ['status' => 'free', 'is_pinned' => true])->assertRedirect();
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/slots/{$slots[0]->id}/einfügen", ['type' => 'unit', 'source_id' => $fixedUnit->id])->assertRedirect();

    expect(ScheduledLesson::where('lesson_id', $fixedLesson->id)->value('schedule_slot_id'))->toBe($slots[1]->id)
        ->and(ScheduledLesson::where('lesson_id', $movableLesson->id)->value('schedule_slot_id'))->toBe($slots[0]->id)
        ->and(ScheduledLesson::where('lesson_id', $otherLesson->id)->value('schedule_slot_id'))->toBe($slots[2]->id);
});

it('bestätigt und erlaubt Überlauf am Ende beim Einfügen', function () {
    [$user, $group] = phaseSixOneGroup();
    $existingUnit = $group->teachingUnits()->create(['organization_id' => $user->organization_id, 'title' => 'Bestehende UE', 'position' => 1]);
    $existingLesson = $existingUnit->lessons()->create(['title' => 'Letzte Stunde', 'position' => 1, 'duration' => 1]);
    $newUnit = $group->teachingUnits()->create(['organization_id' => $user->organization_id, 'title' => 'Neue UE', 'position' => 2]);
    $newLesson = $newUnit->lessons()->create(['title' => 'Mehrstündige neue Stunde', 'position' => 1, 'duration' => 2]);
    $this->actingAs($user)->get("/jahresplanung/{$group->id}");
    $slots = ScheduleSlot::orderBy('date')->get();
    $lastSlot = $slots->last();
    $targetSlot = $slots->get($slots->count() - 2);
    app(YearPlanningWorkspace::class)->scheduleLesson($group, $existingLesson, $lastSlot);

    $this->actingAs($user)->post("/jahresplanung/{$group->id}/slots/{$targetSlot->id}/einfügen", ['type' => 'unit', 'source_id' => $newUnit->id])->assertRedirect()->assertSessionHas('planning_overflow', 1);
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/slots/{$targetSlot->id}/einfügen", ['type' => 'unit', 'source_id' => $newUnit->id, 'allow_overflow' => true])->assertRedirect();
    expect(ScheduledLesson::where('lesson_id', $newLesson->id)->count())->toBe(2)
        ->and(ScheduledLesson::where('lesson_id', $newLesson->id)->where('schedule_slot_id', $targetSlot->id)->exists())->toBeTrue()
        ->and(ScheduledLesson::where('lesson_id', $existingLesson->id)->count())->toBe(0);
});

it('warnt beim Verschieben einer mehrstündigen UE auf den letzten Slot', function () {
    [$user, $group] = phaseSixOneGroup();
    $unit = $group->teachingUnits()->create(['organization_id' => $user->organization_id, 'title' => 'Letzte UE', 'position' => 1]);
    $lesson = $unit->lessons()->create(['title' => 'Zweistündige Stunde', 'position' => 1, 'duration' => 2]);
    $this->actingAs($user)->get("/jahresplanung/{$group->id}");
    $slots = ScheduleSlot::orderBy('date')->get();
    $firstSlot = $slots->get($slots->count() - 2);
    $lastSlot = $slots->last();
    app(YearPlanningWorkspace::class)->scheduleLesson($group, $lesson, $firstSlot);

    $this->actingAs($user)->post("/jahresplanung/{$group->id}/slots/{$lastSlot->id}/einfügen", ['type' => 'unit', 'source_id' => $unit->id])->assertRedirect()->assertSessionHas('planning_overflow', 1);
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/slots/{$lastSlot->id}/einfügen", ['type' => 'unit', 'source_id' => $unit->id, 'allow_overflow' => true])->assertRedirect();
    expect(ScheduledLesson::where('lesson_id', $lesson->id)->count())->toBe(1)
        ->and(ScheduledLesson::where('lesson_id', $lesson->id)->value('schedule_slot_id'))->toBe($lastSlot->id);
});

it('behält bewusst entfernte Stunden beim Verschieben einer UE entfernt', function () {
    [$user, $group] = phaseSixOneGroup();
    $unit = $group->teachingUnits()->create(['organization_id' => $user->organization_id, 'title' => 'Teilweise geplante UE', 'position' => 1]);
    $lessons = collect(range(1, 3))->map(fn (int $position) => $unit->lessons()->create(['title' => 'Stunde '.$position, 'position' => $position, 'duration' => 1]));
    $this->actingAs($user)->get("/jahresplanung/{$group->id}");
    $slots = ScheduleSlot::orderBy('date')->get();
    app(YearPlanningWorkspace::class)->scheduleLesson($group, $lessons[0], $slots[1]);
    app(YearPlanningWorkspace::class)->scheduleLesson($group, $lessons[2], $slots[3]);

    $this->actingAs($user)->post("/jahresplanung/{$group->id}/slots/{$slots[0]->id}/einfügen", ['type' => 'unit', 'source_id' => $unit->id])->assertRedirect();

    expect(ScheduledLesson::where('lesson_id', $lessons[0]->id)->count())->toBe(1)
        ->and(ScheduledLesson::where('lesson_id', $lessons[1]->id)->count())->toBe(0)
        ->and(ScheduledLesson::where('lesson_id', $lessons[2]->id)->count())->toBe(1)
        ->and(ScheduledLesson::where('lesson_id', $lessons[0]->id)->value('schedule_slot_id'))->toBe($slots[0]->id)
        ->and(ScheduledLesson::where('lesson_id', $lessons[2]->id)->value('schedule_slot_id'))->toBe($slots[1]->id);
});

it('verschiebt eine UE mit der tatsächlichen Reihenfolge ihrer geplanten Stunden', function () {
    [$user, $group, , $year] = phaseSixOneGroup();
    $year->update(['ends_on' => '2026-10-31']);
    $unit = $group->teachingUnits()->create(['organization_id' => $user->organization_id, 'title' => 'Umsortierte UE', 'position' => 1]);
    $lessons = collect(range(1, 3))->map(fn (int $position) => $unit->lessons()->create(['title' => 'Stunde '.$position, 'position' => $position, 'duration' => 1]));
    $this->actingAs($user)->get("/jahresplanung/{$group->id}");
    $slots = ScheduleSlot::orderBy('date')->get();
    app(YearPlanningWorkspace::class)->scheduleLesson($group, $lessons[1], $slots[0]);
    app(YearPlanningWorkspace::class)->scheduleLesson($group, $lessons[0], $slots[1]);
    app(YearPlanningWorkspace::class)->scheduleLesson($group, $lessons[2], $slots[2]);

    $this->actingAs($user)->post("/jahresplanung/{$group->id}/slots/{$slots[4]->id}/einfügen", [
        'type' => 'unit',
        'source_id' => $unit->id,
    ])->assertRedirect();

    expect(ScheduledLesson::where('lesson_id', $lessons[1]->id)->value('schedule_slot_id'))->toBe($slots[4]->id)
        ->and(ScheduledLesson::where('lesson_id', $lessons[0]->id)->value('schedule_slot_id'))->toBe($slots[5]->id)
        ->and(ScheduledLesson::where('lesson_id', $lessons[2]->id)->value('schedule_slot_id'))->toBe($slots[6]->id);
});

it('verschiebt eine mehrstündige Lesson auch bei überlappendem Zielbereich', function () {
    [$user, $group, , $year] = phaseSixOneGroup();
    $year->update(['ends_on' => '2026-10-31']);
    $unit = $group->teachingUnits()->create(['organization_id' => $user->organization_id, 'title' => 'Mehrstunden-UE', 'position' => 1]);
    $lesson = $unit->lessons()->create(['title' => 'Fünfstündige Lesson', 'position' => 1, 'duration' => 5]);
    $this->actingAs($user)->get("/jahresplanung/{$group->id}");
    $slots = ScheduleSlot::orderBy('date')->get();
    app(YearPlanningWorkspace::class)->scheduleLesson($group, $lesson, $slots[0]);

    $this->actingAs($user)->post("/jahresplanung/{$group->id}/slots/{$slots[2]->id}/einfügen", [
        'type' => 'lesson',
        'source_id' => $lesson->id,
    ])->assertRedirect();

    expect(ScheduledLesson::where('lesson_id', $lesson->id)->pluck('schedule_slot_id')->sort()->values()->all())
        ->toBe($slots->slice(2, 5)->pluck('id')->sort()->values()->all());
});

it('fragt beim Einfügen einer späteren Stunde nach dem Nachrücken und verschiebt den Rest nach oben', function () {
    [$user, $group, , $year] = phaseSixOneGroup();
    $year->update(['ends_on' => '2026-10-31']);
    $lessons = collect(range(1, 7))->map(function (int $number) use ($group, $user) {
        $unit = $group->teachingUnits()->create(['organization_id' => $user->organization_id, 'title' => 'UE '.$number, 'position' => $number]);

        return $unit->lessons()->create(['title' => 'Stunde '.$number, 'position' => 1, 'duration' => 1]);
    });
    $this->actingAs($user)->get("/jahresplanung/{$group->id}");
    $slots = ScheduleSlot::orderBy('date')->get();
    foreach ($lessons as $index => $lesson) {
        app(YearPlanningWorkspace::class)->scheduleLesson($group, $lesson, $slots[$index < 3 ? $index : $index + 1]);
    }

    $this->actingAs($user)->post("/jahresplanung/{$group->id}/slots/{$slots[3]->id}/einfügen", [
        'type' => 'lesson',
        'source_id' => $lessons[3]->id,
        'pull_following' => true,
    ])->assertRedirect();

    foreach ($lessons as $index => $lesson) {
        expect(ScheduledLesson::where('lesson_id', $lesson->id)->value('schedule_slot_id'))
            ->toBe($slots[$index]->id);
    }
});

it('speichert die UE-Reihenfolge und plant nicht geplante UEs automatisch danach ein', function () {
    [$user, $group] = phaseSixOneGroup();
    $units = collect(['Bereits geplant', 'Zweite UE', 'Dritte UE'])->map(function (string $title, int $index) use ($group, $user) {
        $unit = $group->teachingUnits()->create(['organization_id' => $user->organization_id, 'title' => $title, 'position' => $index + 1]);
        $unit->lessons()->create(['title' => $title.' – Stunde', 'position' => 1, 'duration' => $index === 1 ? 2 : 1]);

        return $unit;
    });
    $this->actingAs($user)->get("/jahresplanung/{$group->id}");
    $slots = ScheduleSlot::orderBy('date')->get();
    $plannedLesson = $units[0]->lessons()->first();
    app(YearPlanningWorkspace::class)->scheduleLesson($group, $plannedLesson, $slots[0]);

    $this->actingAs($user)->put("/jahresplanung/{$group->id}/eigene-einheiten/reihenfolge", ['unit_ids' => [$units[2]->id, $units[1]->id, $units[0]->id]])->assertRedirect();
    expect($units[2]->fresh()->position)->toBe(1);

    $this->actingAs($user)->post("/jahresplanung/{$group->id}/automatisch-einplanen", ['start_mode' => 'free', 'schedule_slot_id' => $slots[1]->id, 'keep_together' => true])->assertRedirect();
    expect(ScheduledLesson::whereIn('lesson_id', $units[1]->lessons()->pluck('id'))->count())->toBe(2)
        ->and(ScheduledLesson::whereIn('lesson_id', $units[2]->lessons()->pluck('id'))->count())->toBe(1);
});
