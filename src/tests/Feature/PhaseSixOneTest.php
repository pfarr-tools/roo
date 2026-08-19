<?php

use App\Models\Curriculum;
use App\Models\CurriculumTopic;
use App\Models\CurriculumTopicCompetency;
use App\Models\CurriculumVersion;
use App\Models\EducationPlan;
use App\Models\EducationPlanCompetenceArea;
use App\Models\EducationPlanCompetency;
use App\Models\EducationPlanVersion;
use App\Models\Organization;
use App\Models\ScheduledLesson;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\SchoolPeriod;
use App\Models\SchoolYear;
use App\Models\TeachingGroup;
use App\Models\TeachingUnit;
use App\Models\User;
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
