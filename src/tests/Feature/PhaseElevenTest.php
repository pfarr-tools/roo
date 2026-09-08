<?php

use App\Models\Assessment;
use App\Models\AssessmentTask;
use App\Models\CompetenceEvidence;
use App\Models\CustomProcessCompetence;
use App\Models\EducationPlan;
use App\Models\EducationPlanCompetenceArea;
use App\Models\EducationPlanCompetenceVariant;
use App\Models\EducationPlanCompetency;
use App\Models\EducationPlanVersion;
use App\Models\Organization;
use App\Models\ReportPeriod;
use App\Models\ReportPeriodEvaluationTemplate;
use App\Models\ScheduledLesson;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentAssessmentResult;
use App\Models\StudentEvaluationObservationScale;
use App\Models\TeachingGroup;
use App\Models\TeachingUnitCompetency;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('remembers the last selected evaluation group', function () {
    $org = Organization::create(['name' => 'Gruppenvoreinstellung']);
    $user = User::factory()->create(['organization_id' => $org->id]);
    $school = School::create(['organization_id' => $org->id, 'name' => 'Schule']);
    $year = SchoolYear::create(['organization_id' => $org->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    TeachingGroup::create(['organization_id' => $org->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4a']);
    $second = TeachingGroup::create(['organization_id' => $org->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '5a']);

    $this->actingAs($user)->get("/bewertungen?group={$second->id}")->assertInertia(fn ($page) => $page->where('group.id', $second->id));
    expect(UserPreference::where('user_id', $user->id)->where('key', 'evaluations.last_group')->first()->value)->toBe(['group_id' => $second->id]);

    $this->actingAs($user)->get('/bewertungen')->assertInertia(fn ($page) => $page->where('group.id', $second->id));
});
it('legt einen Bewertungszeitraum an und trennt den Entwurf vom Bestätigungsstatus', function () {
    $org = Organization::create(['name' => 'Evaluation']);
    $user = User::factory()->create(['organization_id' => $org->id]);
    $school = School::create(['organization_id' => $org->id, 'name' => 'Schule']);
    $year = SchoolYear::create(['organization_id' => $org->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $org->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4a']);
    $this->actingAs($user)->post("/unterrichtsgruppen/{$group->id}/bewertungen/zeiträume", ['label' => '1. Halbjahr', 'starts_on' => '2026-09-01', 'ends_on' => '2027-02-01'])->assertRedirect();
    expect(ReportPeriod::first()->label)->toBe('1. Halbjahr');
});

it('erzeugt und bearbeitet Vorlagen für Kompetenztexte und Noten', function () {
    $org = Organization::create(['name' => 'Vorlagen']);
    $user = User::factory()->create(['organization_id' => $org->id]);
    $school = School::create(['organization_id' => $org->id, 'name' => 'Vorlagenschule']);
    $year = SchoolYear::create(['organization_id' => $org->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $org->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4a', 'grading_model' => 'competency_texts_and_grades']);

    $this->actingAs($user)->post("/unterrichtsgruppen/{$group->id}/bewertungen/zeiträume", ['label' => '1. Halbjahr', 'starts_on' => '2026-09-01', 'ends_on' => '2027-02-01'])->assertRedirect("/unterrichtsgruppen/{$group->id}?tab=evaluations");
    $period = $group->reportPeriods()->first();
    $template = $period->evaluationTemplates()->create(['level' => 'G', 'original_text' => '[Vorname] kann etwas.', 'text' => '[Vorname] kann etwas.']);

    $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}/bewertungen/zeiträume/{$period->id}/vorlage")->assertInertia(fn ($page) => $page->component('Evaluations/TemplateEdit')->where('period.evaluation_templates.0.id', $template->id));
    $this->actingAs($user)->put("/unterrichtsgruppen/{$group->id}/bewertungen/zeiträume/{$period->id}/vorlage", ['templates' => [['id' => $template->id, 'text' => 'Bearbeiteter Text.']]])->assertRedirect("/unterrichtsgruppen/{$group->id}?tab=evaluations");

    expect(ReportPeriodEvaluationTemplate::find($template->id)->text)->toBe('Bearbeiteter Text.')
        ->and(ReportPeriodEvaluationTemplate::find($template->id)->original_text)->toBe('[Vorname] kann etwas.');
});

it('generates differentiated proposal sentences from treated content competences', function () {
    $org = Organization::create(['name' => 'Satzvorschläge']);
    $user = User::factory()->create(['organization_id' => $org->id]);
    $school = School::create(['organization_id' => $org->id, 'name' => 'Satzschule']);
    $year = SchoolYear::create(['organization_id' => $org->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $org->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '5a', 'grading_model' => 'competency_texts_and_grades']);
    $plan = EducationPlan::create(['external_identifier' => 'BP2016BW_ALLG_SEK1_RAK', 'subject' => 'Religion', 'title' => 'Bildungsplan']);
    $version = EducationPlanVersion::create(['education_plan_id' => $plan->id, 'external_identifier' => '2026', 'schema_version' => '1', 'title' => '2026', 'raw_payload' => []]);
    $area = EducationPlanCompetenceArea::create(['education_plan_version_id' => $version->id, 'kind' => 'content', 'external_identifier' => '3.3.1', 'title' => 'Inhalt', 'position' => 1]);
    $competency = EducationPlanCompetency::create(['education_plan_competence_area_id' => $area->id, 'external_identifier' => '3.3.1.1', 'text' => null, 'position' => 1]);
    $secondCompetency = EducationPlanCompetency::create(['education_plan_competence_area_id' => $area->id, 'external_identifier' => '3.3.1.2', 'text' => null, 'position' => 2]);
    foreach (['G' => 'auf G-Niveau beschreiben', 'M' => 'auf M-Niveau erklären', 'E' => 'auf E-Niveau beurteilen'] as $position => $text) {
        EducationPlanCompetenceVariant::create(['education_plan_competency_id' => $competency->id, 'education_plan_level_id' => null, 'text' => $text, 'position' => array_search($position, ['G', 'M', 'E'], true)]);
        EducationPlanCompetenceVariant::create(['education_plan_competency_id' => $secondCompetency->id, 'education_plan_level_id' => null, 'text' => $text, 'position' => array_search($position, ['G', 'M', 'E'], true)]);
    }
    $unit = $group->teachingUnits()->create(['organization_id' => $org->id, 'education_plan_id' => $plan->id, 'title' => 'Einheit', 'position' => 1]);
    $lesson = $unit->lessons()->create(['title' => 'Stunde', 'position' => 1]);
    $unitCompetency = TeachingUnitCompetency::create(['teaching_unit_id' => $unit->id, 'education_plan_competency_id' => $competency->id]);
    $secondUnitCompetency = TeachingUnitCompetency::create(['teaching_unit_id' => $unit->id, 'education_plan_competency_id' => $secondCompetency->id]);
    $lesson->competencies()->attach($unitCompetency->id);
    $lesson->competencies()->attach($secondUnitCompetency->id);
    $slot = ScheduleSlot::create(['teaching_group_id' => $group->id, 'date' => '2026-09-08', 'period_number' => 1, 'starts_at' => '08:00', 'ends_at' => '08:45']);
    ScheduledLesson::create(['lesson_id' => $lesson->id, 'schedule_slot_id' => $slot->id]);

    $group->reportPeriods()->create(['organization_id' => $org->id, 'label' => 'September', 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30']);
    $period = $group->reportPeriods()->first();
    $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}/bewertungen/zeiträume/{$period->id}/vorlage")->assertInertia(fn ($page) => $page->has('period.evaluation_templates', 3));

    $templates = $period->fresh()->evaluationTemplates;
    $text = $templates->firstWhere('level', 'G')->original_text;
    expect($templates)->toHaveCount(3)
        ->and($templates->pluck('level')->all())->toBe(['G', 'M', 'E'])
        ->and($text)->toContain('[Vorname]')
        ->and($text)->toContain('[Pronomen]')
        ->and(strpos($text, '[Vorname]'))->toBeLessThan(strpos($text, '[Pronomen]'));

    $template = $templates->firstWhere('level', 'G');
    $template->update(['original_text' => 'Veralteter Vorschlag.', 'text' => 'Manuell geändert.']);

    $this->actingAs($user)->post("/unterrichtsgruppen/{$group->id}/bewertungen/zeiträume/{$period->id}/vorlage/{$template->id}/zurücksetzen")
        ->assertRedirect(route('evaluations.templates.edit', [$group, $period]));

    expect($template->fresh()->text)->toBe($text)
        ->and($template->fresh()->text)->not->toBe('Veralteter Vorschlag.');
});

it('shows competency scales and the evaluation draft for competency text grading', function () {
    $org = Organization::create(['name' => 'Kompetenzbewertung']);
    $user = User::factory()->create(['organization_id' => $org->id]);
    $school = School::create(['organization_id' => $org->id, 'name' => 'Kompetenzschule']);
    $year = SchoolYear::create(['organization_id' => $org->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $org->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '5b', 'grading_model' => 'competency_texts_and_grades']);
    $group->gradeComponents()->createMany([
        ['type' => 'observations', 'label' => 'Beobachtungen im Unterricht', 'percentage' => 50, 'position' => 1],
        ['type' => 'written_assessments', 'label' => 'Schriftliche Leistungen', 'percentage' => 50, 'position' => 2],
    ]);
    $student = Student::create(['organization_id' => $org->id, 'school_id' => $school->id, 'first_name' => 'Mia', 'last_name' => 'Muster', 'class_name' => '5b']);
    $group->students()->attach($student->id);
    $plan = EducationPlan::create(['external_identifier' => 'KOMPETENZBEWERTUNG', 'subject' => 'Religion', 'title' => 'Bildungsplan']);
    $version = EducationPlanVersion::create(['education_plan_id' => $plan->id, 'external_identifier' => '2026', 'schema_version' => '1', 'title' => '2026', 'raw_payload' => []]);
    $area = EducationPlanCompetenceArea::create(['education_plan_version_id' => $version->id, 'kind' => 'content', 'external_identifier' => '3.1.1', 'title' => 'Inhalt', 'position' => 1]);
    $competency = EducationPlanCompetency::create(['education_plan_competence_area_id' => $area->id, 'external_identifier' => '3.1.1.1', 'text' => 'Menschliche Erfahrungen beschreiben', 'position' => 1]);
    foreach (['G' => 'auf G-Niveau beschreiben', 'M' => 'auf M-Niveau erklären', 'E' => 'auf E-Niveau beurteilen'] as $position => $text) {
        EducationPlanCompetenceVariant::create(['education_plan_competency_id' => $competency->id, 'education_plan_level_id' => null, 'text' => $text, 'position' => array_search($position, ['G', 'M', 'E'], true)]);
    }
    $unit = $group->teachingUnits()->create(['organization_id' => $org->id, 'education_plan_id' => $plan->id, 'title' => 'Einheit', 'position' => 1]);
    $lesson = $unit->lessons()->create(['title' => 'Stunde', 'position' => 1]);
    $unitCompetency = TeachingUnitCompetency::create(['teaching_unit_id' => $unit->id, 'education_plan_competency_id' => $competency->id]);
    $lesson->competencies()->attach($unitCompetency->id);
    $slot = ScheduleSlot::create(['teaching_group_id' => $group->id, 'date' => '2026-09-08', 'period_number' => 1, 'starts_at' => '08:00', 'ends_at' => '08:45']);
    $scheduledLesson = ScheduledLesson::create(['lesson_id' => $lesson->id, 'schedule_slot_id' => $slot->id]);
    CompetenceEvidence::create(['scheduled_lesson_id' => $scheduledLesson->id, 'student_id' => $student->id, 'teaching_unit_competency_id' => $unitCompetency->id, 'scale' => '2']);
    $secondSlot = ScheduleSlot::create(['teaching_group_id' => $group->id, 'date' => '2026-09-15', 'period_number' => 1, 'starts_at' => '08:00', 'ends_at' => '08:45']);
    $secondScheduledLesson = ScheduledLesson::create(['lesson_id' => $lesson->id, 'schedule_slot_id' => $secondSlot->id]);
    CompetenceEvidence::create(['scheduled_lesson_id' => $secondScheduledLesson->id, 'student_id' => $student->id, 'teaching_unit_competency_id' => $unitCompetency->id, 'scale' => '4']);
    $period = $group->reportPeriods()->create(['organization_id' => $org->id, 'label' => 'September', 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30']);
    $evaluation = $period->evaluations()->create(['student_id' => $student->id, 'draft_text' => 'Bewertungsentwurf']);
    $assessment = Assessment::create(['organization_id' => $org->id, 'teaching_group_id' => $group->id, 'report_period_id' => $period->id, 'title' => 'LSE September', 'assessed_on' => '2026-09-08']);
    $task = AssessmentTask::create(['organization_id' => $org->id, 'education_plan_id' => $plan->id, 'education_plan_competency_id' => $competency->id, 'title' => 'Aufgabe', 'max_points' => 10]);
    $task->levels()->create(['level' => 'G']);
    $assessment->tasks()->attach($task->id);
    StudentAssessmentResult::create(['assessment_id' => $assessment->id, 'assessment_task_id' => $task->id, 'student_id' => $student->id, 'level' => 'G', 'points' => 8]);

    $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}/bewertungen/{$evaluation->id}/bearbeiten")
        ->assertInertia(fn ($page) => $page
            ->where('evaluation.draft_text', 'Bewertungsentwurf')
            ->where('competencies.0.id', $unitCompetency->id)
            ->where('competencies.0.text', 'auf G-Niveau beschreiben')
            ->where('competencies.0.level_texts.G', 'auf G-Niveau beschreiben')
            ->where('competencies.0.level_texts.M', 'auf M-Niveau erklären')
            ->where('competenceAverages.0.teaching_unit_competency_id', $unitCompetency->id)
            ->where('competenceAverages.0.average', 3.5)
            ->where('competenceAverages.0.rounded_level', 4)
            ->where('lses.0.title', 'LSE September')
            ->where('lses.0.date', '2026-09-08')
            ->where('lses.0.student_levels.0', 'G')
            ->where('lses.0.percentage', 80)
            ->where('lses.0.grade', '2')
            ->where('lses.0.receives_grades', false)
            ->where('competenceAverages.0.percentage', 70)
            ->where('competenceAverages.0.sources.0.percentage', 80)
            ->where('competenceAverages.0.sources.1.percentage', 40)
            ->where('competenceAverages.0.sources.2.percentage', 80)
            ->where('periodLevel', 'G'));

    $this->actingAs($user)->put("/unterrichtsgruppen/{$group->id}/bewertungen/{$evaluation->id}", ['draft_text' => 'Bewertungsentwurf', 'teacher_note' => '', 'level' => 'M', 'status' => 'draft', 'competence_ratings' => [['education_plan_competency_id' => $unitCompetency->education_plan_competency_id, 'rating' => 4]]])->assertRedirect();
    expect($evaluation->fresh()->level)->toBe('M');
    expect(DB::table('student_evaluation_competence_ratings')->where('student_evaluation_id', $evaluation->id)->value('rating'))->toBe(4);
});

it('uses live school scale definitions in drafts and snapshots them on confirmation', function () {
    $org = Organization::create(['name' => 'Skalenbewertung']);
    $user = User::factory()->create(['organization_id' => $org->id]);
    $school = School::create(['organization_id' => $org->id, 'name' => 'Skalenschule']);
    $year = SchoolYear::create(['organization_id' => $org->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $org->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4a', 'grading_model' => 'observation_scales']);
    $student = Student::create(['organization_id' => $org->id, 'school_id' => $school->id, 'first_name' => 'Mia', 'last_name' => 'Muster', 'class_name' => '4a']);
    $group->students()->attach($student->id);
    $competence = CustomProcessCompetence::create(['school_id' => $school->id, 'text' => 'Religiöse Fragen besprechen', 'position' => 1]);

    $this->actingAs($user)->post("/unterrichtsgruppen/{$group->id}/bewertungen/zeiträume", ['label' => '1. Halbjahr', 'starts_on' => '2026-09-01', 'ends_on' => '2027-02-01'])->assertRedirect();
    $evaluation = ReportPeriod::first()->evaluations()->first();

    $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}/bewertungen/{$evaluation->id}/bearbeiten")->assertInertia(fn ($page) => $page
        ->where('customProcessCompetences.0.text', 'Religiöse Fragen besprechen')
        ->where('customProcessCompetenceScaleIntervalCount', 4));

    $school->update(['observation_scale_interval_count' => 5]);
    $competence->update(['text' => 'Religiöse Fragen reflektieren']);
    $this->actingAs($user)->put("/unterrichtsgruppen/{$group->id}/bewertungen/{$evaluation->id}", [
        'draft_text' => 'Bewertungstext',
        'teacher_note' => '',
        'status' => 'confirmed',
        'observation_scales' => [['custom_process_competence_id' => $competence->id, 'custom_scale_level' => 4]],
    ])->assertRedirect();

    expect(StudentEvaluationObservationScale::first()->competence_text_snapshot)->toBe('Religiöse Fragen reflektieren')
        ->and(StudentEvaluationObservationScale::first()->interval_count_snapshot)->toBe(5)
        ->and(StudentEvaluationObservationScale::first()->custom_scale_level)->toBe(4);
});

it('rejects a level that no longer exists after the school scale is reduced', function () {
    $org = Organization::create(['name' => 'Skalenänderung']);
    $user = User::factory()->create(['organization_id' => $org->id]);
    $school = School::create(['organization_id' => $org->id, 'name' => 'Änderungsschule', 'observation_scale_interval_count' => 4]);
    $year = SchoolYear::create(['organization_id' => $org->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $org->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4a', 'grading_model' => 'observation_scales']);
    $student = Student::create(['organization_id' => $org->id, 'school_id' => $school->id, 'first_name' => 'Mia', 'last_name' => 'Muster', 'class_name' => '4a']);
    $group->students()->attach($student->id);
    $competence = CustomProcessCompetence::create(['school_id' => $school->id, 'text' => 'Wahrnehmen', 'position' => 1]);
    $this->actingAs($user)->post("/unterrichtsgruppen/{$group->id}/bewertungen/zeiträume", ['label' => '1. Halbjahr', 'starts_on' => '2026-09-01', 'ends_on' => '2027-02-01']);
    $evaluation = ReportPeriod::first()->evaluations()->first();
    $school->update(['observation_scale_interval_count' => 3]);

    $this->actingAs($user)->put("/unterrichtsgruppen/{$group->id}/bewertungen/{$evaluation->id}", [
        'status' => 'confirmed',
        'observation_scales' => [['custom_process_competence_id' => $competence->id, 'custom_scale_level' => 4]],
    ])->assertStatus(422);
});

it('calculates competence averages from numeric observations in the evaluation period', function () {
    $org = Organization::create(['name' => 'Durchschnitt']);
    $user = User::factory()->create(['organization_id' => $org->id]);
    $school = School::create(['organization_id' => $org->id, 'name' => 'Durchschnittsschule']);
    $year = SchoolYear::create(['organization_id' => $org->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $org->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4a', 'grading_model' => 'observation_scales']);
    $student = Student::create(['organization_id' => $org->id, 'school_id' => $school->id, 'first_name' => 'Mia', 'last_name' => 'Muster', 'class_name' => '4a']);
    $group->students()->attach($student->id);
    $competence = CustomProcessCompetence::create(['school_id' => $school->id, 'text' => 'Wahrnehmen', 'position' => 1]);
    $period = $group->reportPeriods()->create(['organization_id' => $org->id, 'label' => 'September', 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30']);
    $evaluation = $period->evaluations()->create(['student_id' => $student->id]);
    $lesson = $group->teachingUnits()->create(['organization_id' => $org->id, 'title' => 'Beobachtungen', 'position' => 1])->lessons()->create(['title' => 'Stunde', 'position' => 1]);

    foreach ([['2026-09-08', 2], ['2026-09-15', 3], ['2026-09-22', null], ['2026-10-01', 4]] as [$date, $level]) {
        $slot = ScheduleSlot::create(['teaching_group_id' => $group->id, 'date' => $date, 'period_number' => 1, 'starts_at' => '08:00', 'ends_at' => '08:45']);
        $scheduledLesson = ScheduledLesson::create(['lesson_id' => $lesson->id, 'schedule_slot_id' => $slot->id]);
        if ($level === null) {
            CompetenceEvidence::create(['scheduled_lesson_id' => $scheduledLesson->id, 'student_id' => $student->id, 'custom_process_competence_id' => $competence->id, 'custom_scale_status' => 'ne']);
        } else {
            CompetenceEvidence::create(['scheduled_lesson_id' => $scheduledLesson->id, 'student_id' => $student->id, 'custom_process_competence_id' => $competence->id, 'custom_scale_level' => $level]);
        }
    }

    $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}/bewertungen/{$evaluation->id}/bearbeiten")->assertInertia(fn ($page) => $page
        ->where('competenceAverages.0.custom_process_competence_id', $competence->id)
        ->where('competenceAverages.0.average', 2.5)
        ->where('competenceAverages.0.rounded_level', 3)
    );
});

it('provides sorted evaluation navigation and saves to the next student', function () {
    $org = Organization::create(['name' => 'Bewertungsnavigation']);
    $user = User::factory()->create(['organization_id' => $org->id]);
    $school = School::create(['organization_id' => $org->id, 'name' => 'Navigationsschule']);
    $year = SchoolYear::create(['organization_id' => $org->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['organization_id' => $org->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '4a', 'grading_model' => 'observation_scales']);
    $firstStudent = Student::create(['organization_id' => $org->id, 'school_id' => $school->id, 'first_name' => 'Zoe', 'last_name' => 'Zimmer', 'class_name' => '4a']);
    $secondStudent = Student::create(['organization_id' => $org->id, 'school_id' => $school->id, 'first_name' => 'Anna', 'last_name' => 'Albrecht', 'class_name' => '4a']);
    $group->students()->attach([$firstStudent->id, $secondStudent->id]);
    $period = $group->reportPeriods()->create(['organization_id' => $org->id, 'label' => '1. Halbjahr', 'starts_on' => '2026-09-01', 'ends_on' => '2027-02-01']);
    $firstEvaluation = $period->evaluations()->create(['student_id' => $firstStudent->id]);
    $secondEvaluation = $period->evaluations()->create(['student_id' => $secondStudent->id]);

    $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}/bewertungen/{$secondEvaluation->id}/bearbeiten")->assertInertia(fn ($page) => $page
        ->where('previousEvaluation', null)
        ->where('nextEvaluation.id', $firstEvaluation->id)
    );

    $this->actingAs($user)->put("/unterrichtsgruppen/{$group->id}/bewertungen/{$secondEvaluation->id}", [
        'status' => 'draft',
        'observation_scales' => [],
    ])->assertRedirect("/unterrichtsgruppen/{$group->id}/bewertungen/{$firstEvaluation->id}/bearbeiten");
});
