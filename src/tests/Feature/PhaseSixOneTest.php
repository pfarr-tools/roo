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
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\SchoolPeriod;
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

it('bearbeitet UEs, speichert UE und Stunde als Vorlage und entfernt Stunden', function () {
    [$user, $group] = phaseSixOneGroup();
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/eigene-einheiten", ['title' => 'Vorlagen UE', 'notes' => 'Hinweis']);
    $unit = TeachingUnit::firstOrFail();
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/eigene-einheiten/{$unit->id}/stunden", ['title' => 'Vorlagen Stunde', 'duration' => 2]);
    $lesson = $unit->lessons()->firstOrFail();

    $this->actingAs($user)->put("/jahresplanung/{$group->id}/eigene-einheiten/{$unit->id}", ['title' => 'Bearbeitete UE', 'notes' => 'Aktualisiert'])->assertRedirect();
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/eigene-einheiten/{$unit->id}/vorlage")->assertRedirect();
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/lessons/{$lesson->id}/vorlage")->assertRedirect();

    expect(UnitTemplate::count())->toBe(1)
        ->and(LessonTemplate::count())->toBe(1)
        ->and($unit->fresh()->unit_template_id)->toBe(UnitTemplate::firstOrFail()->id)
        ->and($lesson->fresh()->lesson_template_id)->toBe(LessonTemplate::firstOrFail()->id);

    $this->actingAs($user)->delete("/jahresplanung/{$group->id}/lessons/{$lesson->id}")->assertRedirect();
    expect(Lesson::find($lesson->id))->toBeNull();
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
