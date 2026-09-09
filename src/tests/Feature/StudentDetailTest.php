<?php

use App\Models\Assessment;
use App\Models\AssessmentTask;
use App\Models\EducationPlan;
use App\Models\EducationPlanCompetenceArea;
use App\Models\EducationPlanCompetency;
use App\Models\EducationPlanVersion;
use App\Models\Observation;
use App\Models\ObservationType;
use App\Models\ReportPeriod;
use App\Models\ScheduledLesson;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentAssessmentResult;
use App\Models\StudentEvaluation;
use App\Models\StudentEvaluationCompetenceRating;
use App\Models\TeachingGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('zeigt die Schülerin im gewählten Schuljahr mit Beobachtungen, LSE-Ergebnis und Evaluation', function () {
    $user = User::factory()->create();
    $school = School::create(['user_id' => $user->id, 'name' => 'Detail-Schule']);
    $year = SchoolYear::create(['user_id' => $user->id, 'school_id' => $school->id, 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['user_id' => $user->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '7a']);
    $student = Student::create(['user_id' => $user->id, 'school_id' => $school->id, 'first_name' => 'Mara', 'last_name' => 'Schäberle', 'class_name' => '7a']);
    $group->students()->attach($student->id);
    $unit = $group->teachingUnits()->create(['user_id' => $user->id, 'title' => 'Gleichnisse', 'position' => 1]);
    $lesson = $unit->lessons()->create(['title' => 'Der barmherzige Samariter', 'position' => 1]);
    $slot = ScheduleSlot::create(['teaching_group_id' => $group->id, 'date' => '2026-10-01', 'period_number' => 2, 'starts_at' => '08:00', 'ends_at' => '08:45']);
    $scheduledLesson = ScheduledLesson::create(['lesson_id' => $lesson->id, 'schedule_slot_id' => $slot->id]);
    $type = ObservationType::create(['user_id' => $user->id, 'label' => 'Beteiligt']);
    Observation::create(['scheduled_lesson_id' => $scheduledLesson->id, 'student_id' => $student->id, 'observation_type_id' => $type->id, 'note' => 'Gute Mitarbeit.']);
    $competency = officialCompetency($unit, 'Kann Gleichnisse erklären');
    $task = AssessmentTask::create(['user_id' => $user->id, 'education_plan_id' => $competency->area->version->education_plan_id, 'education_plan_competency_id' => $competency->id, 'title' => 'Gleichnis erklären', 'task_type' => 'free_text', 'max_points' => 10]);
    $assessment = Assessment::create(['user_id' => $user->id, 'teaching_group_id' => $group->id, 'title' => 'LSE Gleichnisse', 'assessed_on' => '2026-11-01']);
    $assessment->tasks()->attach($task->id);
    StudentAssessmentResult::create(['assessment_id' => $assessment->id, 'assessment_task_id' => $task->id, 'student_id' => $student->id, 'points' => 8, 'level' => 'M']);
    $period = ReportPeriod::create(['user_id' => $user->id, 'teaching_group_id' => $group->id, 'label' => '1. Halbjahr', 'starts_on' => '2026-09-01', 'ends_on' => '2027-01-31']);
    $evaluation = StudentEvaluation::create(['report_period_id' => $period->id, 'student_id' => $student->id, 'draft_text' => 'Sehr gute Entwicklung.']);
    $plan = EducationPlan::create(['external_identifier' => 'TEST', 'subject' => 'Religion', 'title' => 'Testplan']);
    $version = EducationPlanVersion::create(['education_plan_id' => $plan->id, 'external_identifier' => '2026', 'schema_version' => '1.0', 'title' => 'Testfassung', 'raw_payload' => []]);
    $area = EducationPlanCompetenceArea::create(['education_plan_version_id' => $version->id, 'kind' => 'process', 'external_identifier' => '2.1', 'title' => 'Deuten', 'position' => 1]);
    $planCompetency = EducationPlanCompetency::create(['education_plan_competence_area_id' => $area->id, 'external_identifier' => '2.1.1', 'text' => 'Biblische Texte deuten', 'position' => 1]);
    StudentEvaluationCompetenceRating::create(['student_evaluation_id' => $evaluation->id, 'education_plan_competency_id' => $planCompetency->id, 'rating' => 3]);

    $this->actingAs($user)->get('/schueler:innen/'.$student->id.'?school_year='.$year->id)
        ->assertInertia(fn ($page) => $page
            ->where('student.first_name', 'Mara')
            ->where('selectedSchoolYear.id', $year->id)
            ->where('observations.0.type.label', 'Beteiligt')
            ->where('assessmentResults.0.assessment.title', 'LSE Gleichnisse')
            ->where('evaluations.0.draft_text', 'Sehr gute Entwicklung.')
            ->where('ratings.0.competence.text', 'Biblische Texte deuten')
            ->where('ratings.0.rating', 3)
            ->where('availableSchoolYears.0.id', $year->id));
});

it('schützt die Schülerdetailansicht vor fremden Organisationen', function () {
    $foreignUser = User::factory()->create();
    $user = User::factory()->create();
    $school = School::create(['user_id' => $foreignUser->id, 'name' => 'Fremde Schule']);
    $student = Student::create(['user_id' => $foreignUser->id, 'school_id' => $school->id, 'first_name' => 'Fremd', 'last_name' => 'Kind', 'class_name' => '7a']);

    $this->actingAs($user)->get('/schueler:innen/'.$student->id)->assertForbidden();
});
