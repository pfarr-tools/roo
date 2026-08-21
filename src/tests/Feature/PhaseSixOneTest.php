<?php

use App\Models\Curriculum;
use App\Models\CurriculumTopic;
use App\Models\CurriculumTopicCompetency;
use App\Models\CurriculumEducationPlanBinding;
use App\Models\CurriculumVersion;
use App\Models\EducationPlan;
use App\Models\EducationPlanCompetenceArea;
use App\Models\EducationPlanCompetency;
use App\Models\EducationPlanCompetenceVariant;
use App\Models\EducationPlanVersion;
use App\Models\Lesson;
use App\Models\LessonTemplate;
use App\Models\MaterialItem;
use App\Models\Organization;
use App\Models\ScheduledLesson;
use App\Models\PhaseTemplate;
use App\Models\ScheduleSlot;
use App\Models\ResourceLink;
use App\Models\School;
use App\Models\SchoolPeriod;
use App\Models\SocialForm;
use App\Models\SchoolYear;
use App\Models\TeachingGroup;
use App\Models\TeachingUnit;
use App\Models\TeachingUnitCompetency;
use App\Models\UnitTemplate;
use App\Models\User;
use App\Services\YearPlanningWorkspace;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

it('speichert Kompetenzen aus dem Picker einer UE und kann sie wieder entfernen', function () {
    [$user, $group] = phaseSixOneGroup();
    $curriculum = Curriculum::create(['organization_id' => $user->organization_id, 'title' => 'Picker-Curriculum']);
    $group->curricula()->attach($curriculum->id, ['role' => 'primary']);
    $version = CurriculumVersion::create(['curriculum_id' => $curriculum->id, 'external_identifier' => 'v1', 'is_editable' => false, 'is_complete' => true]);
    $plan = EducationPlan::create(['organization_id' => $user->organization_id, 'external_identifier' => 'BP', 'subject' => 'Religion', 'title' => 'Bildungsplan']);
    $planVersion = EducationPlanVersion::create(['education_plan_id' => $plan->id, 'external_identifier' => '2026', 'schema_version' => '1', 'title' => '2026', 'is_complete' => true, 'raw_payload' => []]);
    CurriculumEducationPlanBinding::create(['curriculum_version_id' => $version->id, 'education_plan_id' => $plan->id]);
    $area = EducationPlanCompetenceArea::create(['education_plan_version_id' => $planVersion->id, 'kind' => 'process', 'external_identifier' => '2.1', 'title' => 'Wahrnehmen', 'position' => 1]);
    $competency = EducationPlanCompetency::create(['education_plan_competence_area_id' => $area->id, 'external_identifier' => '2.1.1.1', 'text' => 'Wahrnehmen und beschreiben', 'position' => 1, 'is_active' => true]);
    $secondCompetency = EducationPlanCompetency::create(['education_plan_competence_area_id' => $area->id, 'external_identifier' => '2.1.1.2', 'text' => 'Darstellen und gestalten', 'position' => 2, 'is_active' => true]);
    $group->gradeLevels()->create(['grade_level' => '4']);
    $unit = $group->teachingUnits()->create(['organization_id' => $user->organization_id, 'title' => 'Picker UE', 'position' => 1]);

    $this->actingAs($user)->get("/jahresplanung/{$group->id}/kompetenzen/picker")
        ->assertJsonPath('competencies.0.competency_presentation.kind', 'process');
    $this->actingAs($user)->get("/jahresplanung/{$group->id}")
        ->assertInertia(fn ($page) => $page
            ->where('workspace.coverage.required_covered', 0)
            ->where('workspace.coverage.required_total', 2));
    $this->actingAs($user)->put("/jahresplanung/{$group->id}/eigene-einheiten/{$unit->id}", [
        'title' => $unit->title,
        'competency_ids' => [],
        'education_plan_competency_ids' => [$competency->id, $secondCompetency->id],
    ])->assertRedirect();
    expect($unit->fresh()->competencies->pluck('education_plan_competency_id')->all())->toBe([$competency->id, $secondCompetency->id]);

    $this->actingAs($user)->put("/jahresplanung/{$group->id}/eigene-einheiten/{$unit->id}", [
        'title' => $unit->title,
        'competency_ids' => [],
        'education_plan_competency_ids' => [$competency->id],
    ])->assertRedirect();
    expect($unit->fresh()->competencies->pluck('education_plan_competency_id')->all())->toBe([$competency->id]);

    $this->actingAs($user)->put("/jahresplanung/{$group->id}/eigene-einheiten/{$unit->id}", [
        'title' => $unit->title,
        'competency_ids' => [],
        'education_plan_competency_ids' => [],
    ])->assertRedirect();
    expect($unit->fresh()->competencies)->toBeEmpty();
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

it('ordnet Dateien, Ressourcen und MaterialItems einer Phase zu', function () {
    [$user, $group] = phaseSixOneGroup();
    $unit = $group->teachingUnits()->create(['organization_id' => $user->organization_id, 'title' => 'Ressourcen UE', 'position' => 1]);
    $lesson = $unit->lessons()->create(['title' => 'Ressourcenstunde', 'position' => 1, 'duration' => 1]);
    $phase = $lesson->phases()->create(['title' => 'Arbeitsphase', 'position' => 1]);
    $file = $unit->resources()->create(['organization_id' => $user->organization_id, 'original_name' => 'Arbeitsblatt.pdf', 'storage_path' => 'test/arbeitsblatt.pdf', 'mime_type' => 'application/pdf', 'size' => 100]);
    $lessonFile = $lesson->resources()->create(['organization_id' => $user->organization_id, 'original_name' => 'Stundenbild.pdf', 'storage_path' => 'test/stundenbild.pdf', 'mime_type' => 'application/pdf', 'size' => 100]);
    $phaseFile = $lesson->resources()->create(['organization_id' => $user->organization_id, 'original_name' => 'Phasenbild.pdf', 'storage_path' => 'test/phasenbild.pdf', 'mime_type' => 'application/pdf', 'size' => 100]);
    $phaseFile->update(['teaching_unit_id' => null, 'lesson_id' => null]);
    $phase->resources()->attach($phaseFile->id);
    $link = ResourceLink::create(['organization_id' => $user->organization_id, 'title' => 'Erklärvideo', 'url' => 'https://example.test/video']);
    $materialItem = MaterialItem::create(['organization_id' => $user->organization_id, 'name' => 'Bibel']);

    $response = $this->actingAs($user)->put("/jahresplanung/{$group->id}/lessons/{$lesson->id}", [
        'title' => $lesson->title,
        'duration' => 1,
        'resource_links' => [['id' => $link->id, 'title' => $link->title, 'url' => $link->url]],
        'material_items' => [['id' => $materialItem->id, 'name' => $materialItem->name]],
        'phases' => [['id' => $phase->id, 'title' => $phase->title, 'resource_ids' => [$file->id, $lessonFile->id, $phaseFile->id], 'resource_link_ids' => [$link->id], 'material_item_ids' => [$materialItem->id]]],
    ]);
    $response->assertRedirect();

    expect($phase->fresh()->resources->pluck('id')->all())->toBe([$file->id, $lessonFile->id, $phaseFile->id])
        ->and($phase->fresh()->resourceLinks->pluck('id')->all())->toBe([$link->id])
        ->and($phase->fresh()->materialItems->pluck('id')->all())->toBe([$materialItem->id])
        ->and($lesson->fresh()->materialItems->pluck('id')->all())->toBe([$materialItem->id]);
});

it('speichert eine neue Ressource direkt mit ihrer Phasenzuordnung', function () {
    [$user, $group] = phaseSixOneGroup();
    $unit = $group->teachingUnits()->create(['organization_id' => $user->organization_id, 'title' => 'Neue Ressource UE', 'position' => 1]);
    $lesson = $unit->lessons()->create(['title' => 'Neue Ressourcenstunde', 'position' => 1, 'duration' => 1]);
    $phase = $lesson->phases()->create(['title' => 'Einstieg', 'position' => 1]);

    $response = $this->actingAs($user)->put("/jahresplanung/{$group->id}/lessons/{$lesson->id}", [
        'title' => $lesson->title,
        'duration' => 1,
        'resource_links' => [['local_key' => 'new-link-1', 'title' => 'Neue Quelle', 'url' => 'https://example.test/neu']],
        'phases' => [['id' => $phase->id, 'title' => $phase->title, 'resource_link_ids' => ['new-link-1']]],
    ]);

    $response->assertRedirect();
    expect($phase->fresh()->resourceLinks)->toHaveCount(1);
});

it('ordnet Bibliotheksressourcen und MaterialItems sofort einer Stunde zu', function () {
    [$user, $group] = phaseSixOneGroup();
    $unit = $group->teachingUnits()->create(['organization_id' => $user->organization_id, 'title' => 'Bibliotheks UE', 'position' => 1]);
    $lesson = $unit->lessons()->create(['title' => 'Bibliotheksstunde', 'position' => 1, 'duration' => 1]);
    $link = ResourceLink::create(['organization_id' => $user->organization_id, 'title' => 'Bibliothekslink', 'url' => 'https://example.test/bibliothek']);
    $material = MaterialItem::create(['organization_id' => $user->organization_id, 'name' => 'Bibliotheksmaterial']);

    $this->actingAs($user)->post("/jahresplanung/{$group->id}/ressourcen/resource/{$link->id}/zuordnen", ['target_type' => 'lesson', 'target_id' => $lesson->id])->assertRedirect();
    $this->actingAs($user)->post("/jahresplanung/{$group->id}/ressourcen/material/{$material->id}/zuordnen", ['target_type' => 'lesson', 'target_id' => $lesson->id])->assertRedirect();

    expect($link->fresh()->lesson_id)->toBe($lesson->id)
        ->and($lesson->fresh()->materialItems->pluck('id')->all())->toBe([$material->id]);
});

it('liefert Kompetenzart und Text zentral normalisiert an den Stundenarbeitsraum', function () {
    [$user, $group] = phaseSixOneGroup();
    $plan = EducationPlan::create(['organization_id' => $user->organization_id, 'external_identifier' => 'BP', 'subject' => 'Religion', 'title' => 'Bildungsplan']);
    $planVersion = EducationPlanVersion::create(['education_plan_id' => $plan->id, 'external_identifier' => '2026', 'schema_version' => '1', 'title' => '2026', 'is_complete' => true, 'raw_payload' => []]);
    $area = EducationPlanCompetenceArea::create(['education_plan_version_id' => $planVersion->id, 'kind' => 'process', 'external_identifier' => '2.1', 'title' => 'Wahrnehmen', 'position' => 1]);
    $educationCompetency = EducationPlanCompetency::create(['education_plan_competence_area_id' => $area->id, 'external_identifier' => '2.1.1.1', 'text' => '2.1.1 Wahrnehmen und beschreiben', 'position' => 1, 'is_active' => true]);
    $contentArea = EducationPlanCompetenceArea::create(['education_plan_version_id' => $planVersion->id, 'kind' => 'content', 'external_identifier' => '3.2.3', 'title' => 'Biblische Bildworte', 'position' => 2]);
    $contentCompetency = EducationPlanCompetency::create(['education_plan_competence_area_id' => $contentArea->id, 'external_identifier' => '3.2.3.4', 'text' => null, 'position' => 1, 'is_active' => true]);
    EducationPlanCompetenceVariant::create(['education_plan_competency_id' => $contentCompetency->id, 'text' => 'die Sprache der biblischen Bildworte wahrnehmen und deuten', 'position' => 1]);
    $unit = $group->teachingUnits()->create(['organization_id' => $user->organization_id, 'title' => 'Kompetenz UE', 'position' => 1]);
    $link = $unit->competencies()->create(['education_plan_competency_id' => $educationCompetency->id]);
    $contentLink = $unit->competencies()->create(['education_plan_competency_id' => $contentCompetency->id]);
    $lesson = $unit->lessons()->create(['title' => 'Kompetenzstunde', 'position' => 1, 'duration' => 1]);
    $lesson->competencies()->attach([$link->id, $contentLink->id]);
    $slot = ScheduleSlot::create(['teaching_group_id' => $group->id, 'date' => '2026-09-08', 'period_number' => 1, 'starts_at' => '08:00', 'ends_at' => '08:45', 'status' => 'planned']);
    ScheduledLesson::create(['lesson_id' => $lesson->id, 'schedule_slot_id' => $slot->id, 'status' => 'planned']);

    $this->actingAs($user)->get("/unterricht/{$slot->id}")
        ->assertInertia(fn ($page) => $page
            ->where('targetCompetencies.process.0.kind', 'process')
            ->where('targetCompetencies.process.0.text', 'Wahrnehmen und beschreiben')
            ->where('targetCompetencies.process.0.label', '2.1.1 (1) – Wahrnehmen und beschreiben')
            ->where('targetCompetencies.content.0.text', 'die Sprache der biblischen Bildworte wahrnehmen und deuten')
            ->where('targetCompetencies.content.0.label', '3.2.3 (4) – die Sprache der biblischen Bildworte wahrnehmen und deuten')
            ->where('unit.competencies.0.competency_presentation.kind', 'process')
            ->where('unit.competencies.0.competency_presentation.text', 'Wahrnehmen und beschreiben'));
});

it('entfernt abgewählte sekundäre Kompetenzen vollständig aus der Stunde', function () {
    [$user, $group] = phaseSixOneGroup();
    $plan = EducationPlan::create(['organization_id' => $user->organization_id, 'external_identifier' => 'BP', 'subject' => 'Religion', 'title' => 'Bildungsplan']);
    $planVersion = EducationPlanVersion::create(['education_plan_id' => $plan->id, 'external_identifier' => '2026', 'schema_version' => '1', 'title' => '2026', 'is_complete' => true, 'raw_payload' => []]);
    $area = EducationPlanCompetenceArea::create(['education_plan_version_id' => $planVersion->id, 'kind' => 'content', 'external_identifier' => '3.1', 'title' => 'Inhalt', 'position' => 1]);
    $educationCompetency = EducationPlanCompetency::create(['education_plan_competence_area_id' => $area->id, 'external_identifier' => '3.1.1', 'text' => 'Eine Kompetenz', 'position' => 1, 'is_active' => true]);
    $unit = $group->teachingUnits()->create(['organization_id' => $user->organization_id, 'title' => 'Kompetenz UE', 'position' => 1]);
    $lesson = $unit->lessons()->create(['title' => 'Kompetenzstunde', 'position' => 1, 'duration' => 1]);
    $unitCompetency = $unit->competencies()->create(['education_plan_competency_id' => $educationCompetency->id, 'is_secondary' => true]);
    $lesson->competencies()->attach($unitCompetency->id);

    $this->actingAs($user)->put("/jahresplanung/{$group->id}/lessons/{$lesson->id}", [
        'title' => $lesson->title,
        'duration' => 1,
        'competency_ids' => [],
        'education_plan_competency_ids' => [],
    ])->assertRedirect();

    expect($lesson->fresh()->competencies)->toBeEmpty()
        ->and(TeachingUnitCompetency::find($unitCompetency->id))->toBeNull();
});

it('lädt UE-Anhänge hoch und erzeugt den vorgeschriebenen Downloadnamen', function () {
    Storage::fake('local');
    [$user, $group] = phaseSixOneGroup();
    $group->update(['aktenzeichen' => '62.53']);
    $group->gradeLevels()->create(['grade_level' => '4']);
    $unit = $group->teachingUnits()->create(['organization_id' => $user->organization_id, 'title' => 'Gottesbilder', 'keyword' => 'Gottesbilder', 'position' => 1]);
    $lesson = $unit->lessons()->create(['title' => 'Reich Gottes', 'position' => 1, 'duration' => 1]);
    $archivePath = tempnam(sys_get_temp_dir(), 'wscdoc-');
    $archive = new ZipArchive();
    $archive->open($archivePath);
    $archive->addFromString('info.json', json_encode(['Statistics' => ['PageCount' => 3]]));
    $archive->addFromString('preview.jpg', 'preview');
    $archive->addFromString('ws2.abd', '');
    $archive->addFromString('FallbackContentSource.json', json_encode(['content' => []]));
    $archive->addFromString('1.0.version', '');
    $archive->close();
    $file = UploadedFile::fake()->createWithContent('Arbeitsblatt.wscdoc', file_get_contents($archivePath));
    unlink($archivePath);

    $this->actingAs($user)->post("/jahresplanung/{$group->id}/eigene-einheiten/{$unit->id}/anhaenge", ['resource' => $file, 'lesson_id' => $lesson->id])->assertRedirect();
    $resource = $unit->resources()->firstOrFail();
    Storage::disk('local')->assertExists($resource->storage_path);
    expect($resource->page_count)->toBe(3);

    $this->actingAs($user)->get("/jahresplanung/{$group->id}/eigene-einheiten/{$unit->id}/anhaenge/{$resource->id}/download")
        ->assertDownload('62.53_4 Gottesbilder 01 Arbeitsblatt.wscdoc');
    $this->actingAs($user)->get("/jahresplanung/{$group->id}/eigene-einheiten/{$unit->id}/anhaenge/{$resource->id}/preview")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');
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

it('speichert alle Phasenfelder auch über die UE-Phasenvorlagenverwaltung', function () {
    [$user, $group] = phaseSixOneGroup();
    $unitTemplate = UnitTemplate::create(['organization_id' => $user->organization_id, 'title' => 'Vorlagen UE', 'expected_hours' => 1, 'version' => 1, 'is_active' => true]);
    $lessonTemplate = LessonTemplate::create(['organization_id' => $user->organization_id, 'unit_template_id' => $unitTemplate->id, 'title' => 'Vorlagenstunde', 'version' => 1, 'is_active' => true]);

    $this->actingAs($user)->post('/unterrichtseinheiten/phasen-vorlagen', [
        'lesson_template_id' => $lessonTemplate->id,
        'title' => 'Gesprächsrunde',
        'duration_minutes' => 10,
        'social_form' => 'Sitzkreis',
        'teacher_interaction' => 'Frageimpuls',
        'learner_activity' => 'Erzählen',
        'differentiation' => 'Satzstarter',
        'didactic_comment' => 'Erfahrungen sammeln',
        'material' => 'Karten',
        'media' => 'Dokumentenkamera',
    ])->assertRedirect();

    $template = PhaseTemplate::where('title', 'Gesprächsrunde')->firstOrFail();
    expect($template->socialForm->name)->toBe('Sitzkreis')
        ->and($template->teacher_interaction)->toBe('Frageimpuls')
        ->and($template->learner_activity)->toBe('Erzählen')
        ->and($template->differentiation)->toBe('Satzstarter')
        ->and($template->didactic_comment)->toBe('Erfahrungen sammeln')
        ->and($template->material)->toBe('Karten')
        ->and($template->media)->toBe('Dokumentenkamera');
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
