<?php

use App\Models\Assessment;
use App\Models\AssessmentBooklet;
use App\Models\AssessmentBookletFragment;
use App\Models\AssessmentStudentResultStatus;
use App\Models\AssessmentTask;
use App\Models\AssessmentTaskExpectation;
use App\Models\AssessmentTaskReview;
use App\Models\AssessmentTaskReviewItem;
use App\Models\AssessmentTaskReviewOption;
use App\Models\EducationPlan;
use App\Models\EducationPlanCompetenceArea;
use App\Models\EducationPlanCompetenceVariant;
use App\Models\EducationPlanCompetency;
use App\Models\EducationPlanLevel;
use App\Models\EducationPlanVersion;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentAssessmentResult;
use App\Models\TeachingGroup;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function assessmentEvaluationDataFixture(): array
{
    $user = User::factory()->create();
    $school = School::create(['user_id' => $user->id, 'name' => 'Auswertungsschule']);
    $schoolYear = SchoolYear::create([
        'user_id' => $user->id,
        'school_id' => $school->id,
        'name' => '2026/27',
        'starts_on' => '2026-09-01',
        'ends_on' => '2027-07-31',
    ]);
    $group = TeachingGroup::create([
        'user_id' => $user->id,
        'school_id' => $school->id,
        'school_year_id' => $schoolYear->id,
        'name' => '4a Religion',
    ]);
    $assessment = Assessment::create([
        'user_id' => $user->id,
        'teaching_group_id' => $group->id,
        'title' => 'Lernstandserhebung Schöpfung',
    ]);
    $student = Student::create([
        'user_id' => $user->id,
        'school_id' => $school->id,
        'first_name' => 'Mara',
        'last_name' => 'Muster',
        'class_name' => '4a',
    ]);
    $group->students()->attach($student);
    $task = AssessmentTask::withoutEvents(fn (): AssessmentTask => AssessmentTask::create([
        'user_id' => $user->id,
        'title' => 'Aufgabe eins',
    ]));
    $assessment->tasks()->attach($task, ['position' => 1]);
    $expectation = AssessmentTaskExpectation::create([
        'assessment_task_id' => $task->id,
        'text' => 'Nennt Beispiele.',
        'points' => 3,
        'repetitions' => 3,
        'position' => 1,
    ]);

    return compact('user', 'assessment', 'student', 'task', 'expectation');
}

it('provides capped weighted student results for the evaluation tab', function () {
    $fixture = assessmentEvaluationDataFixture();
    $user = $fixture['user'];
    $fixture['assessment']->tasks()->updateExistingPivot($fixture['task']->id, ['weight' => 50]);
    $booklet = $fixture['assessment']->booklets()->create(['student_id' => $fixture['student']->id, 'number' => 1, 'status' => 'open', 'source' => 'manual']);
    StudentAssessmentResult::create(['assessment_id' => $fixture['assessment']->id, 'assessment_task_id' => $fixture['task']->id, 'student_id' => $fixture['student']->id, 'points' => 12, 'level' => 'M']);

    $this->actingAs($user)->get("/unterrichtsgruppen/{$fixture['assessment']->teaching_group_id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung")
        ->assertInertia(fn ($page) => $page
            ->where('results.0.first_name', 'Mara')
            ->where('results.0.level', 'M')
            ->where('results.0.has_results', true)
            ->where('results.0.competencies.0.tasks.0.percentage', 100)
            ->where('results.0.competencies.0.tasks.0.weight', 50)
            ->where('results.0.competencies.0.percentage', 100));
});

it('provides the calculated total percentage and grade for evaluation results', function () {
    $fixture = assessmentEvaluationDataFixture();
    $fixture['expectation']->update(['points' => 10, 'repetitions' => 1]);
    $fixture['assessment']->booklets()->create(['student_id' => $fixture['student']->id, 'number' => 1, 'status' => 'open', 'source' => 'manual']);
    StudentAssessmentResult::create(['assessment_id' => $fixture['assessment']->id, 'assessment_task_id' => $fixture['task']->id, 'student_id' => $fixture['student']->id, 'points' => 8]);
    $user = $fixture['user'];

    $this->actingAs($user)->get("/unterrichtsgruppen/{$fixture['assessment']->teaching_group_id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung")
        ->assertInertia(fn ($page) => $page
            ->where('results.0.percentage', 80)
            ->where('results.0.grade', '2')
            ->where('results.0.receives_grades', false));
});

it('lists every group student and allows a missing result decision to count as zero', function () {
    $fixture = assessmentEvaluationDataFixture();
    $secondStudent = Student::create([
        'user_id' => $fixture['user']->id,
        'school_id' => $fixture['assessment']->group->school_id,
        'first_name' => 'Alex',
        'last_name' => 'Beispiel',
        'class_name' => '4a',
    ]);
    $fixture['assessment']->group->students()->syncWithoutDetaching([$secondStudent->id]);
    $user = $fixture['user'];

    $this->actingAs($user)->get("/unterrichtsgruppen/{$fixture['assessment']->teaching_group_id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung")
        ->assertInertia(fn ($page) => $page
            ->has('results', 2)
            ->where('results.0.needs_result_decision', true)
            ->where('results.1.needs_result_decision', true));

    $this->actingAs($user)->putJson("/unterrichtsgruppen/{$fixture['assessment']->teaching_group_id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/students/{$secondStudent->id}/result-status", ['status' => 'missing'])
        ->assertRedirect();

    expect(AssessmentStudentResultStatus::query()->where('assessment_id', $fixture['assessment']->id)->where('student_id', $secondStudent->id)->value('status'))->toBe('missing');

    $this->actingAs($user)->get("/unterrichtsgruppen/{$fixture['assessment']->teaching_group_id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung")
        ->assertInertia(fn ($page) => $page
            ->where('results.0.result_status', 'missing')
            ->where('results.0.competencies.0.percentage', 0));
});

it('uses the assessment task education plan competency in result groups', function () {
    $fixture = assessmentEvaluationDataFixture();
    $plan = EducationPlan::create(['user_id' => $fixture['user']->id, 'external_identifier' => 'TEST', 'subject' => 'Religion', 'title' => 'Testplan']);
    $version = EducationPlanVersion::create(['education_plan_id' => $plan->id, 'external_identifier' => 'TEST-1', 'schema_version' => '1', 'title' => 'Version', 'raw_payload' => []]);
    $area = EducationPlanCompetenceArea::create(['education_plan_version_id' => $version->id, 'kind' => 'content', 'external_identifier' => '3.1', 'title' => 'Inhalt', 'position' => 1]);
    $competency = EducationPlanCompetency::create(['education_plan_competence_area_id' => $area->id, 'external_identifier' => '3.1.1', 'text' => 'Kompetenz direkt aus dem Bildungsplan', 'position' => 1, 'is_active' => true]);
    $fixture['task']->updateQuietly(['education_plan_id' => $plan->id, 'education_plan_competency_id' => $competency->id]);
    $fixture['assessment']->booklets()->create(['student_id' => $fixture['student']->id, 'number' => 1, 'status' => 'open', 'source' => 'manual']);
    StudentAssessmentResult::create(['assessment_id' => $fixture['assessment']->id, 'assessment_task_id' => $fixture['task']->id, 'student_id' => $fixture['student']->id, 'points' => 1]);
    $user = $fixture['user'];

    $this->actingAs($user)->get("/unterrichtsgruppen/{$fixture['assessment']->teaching_group_id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung")
        ->assertInertia(fn ($page) => $page->where('results.0.competencies.0.title', 'Du kannst Kompetenz direkt aus dem Bildungsplan.'));
});

it('uses only the selected level competency variant without its identifier in the result report', function () {
    $fixture = assessmentEvaluationDataFixture();
    $plan = EducationPlan::create(['user_id' => $fixture['user']->id, 'external_identifier' => 'VARIANT', 'subject' => 'Religion', 'title' => 'Variantenplan']);
    $version = EducationPlanVersion::create(['education_plan_id' => $plan->id, 'external_identifier' => 'VARIANT-1', 'schema_version' => '1', 'title' => 'Version', 'raw_payload' => []]);
    $area = EducationPlanCompetenceArea::create(['education_plan_version_id' => $version->id, 'kind' => 'content', 'external_identifier' => '3.1', 'title' => 'Inhalt', 'position' => 1]);
    $competency = EducationPlanCompetency::create(['education_plan_competence_area_id' => $area->id, 'external_identifier' => '3.1.1', 'text' => null, 'position' => 1, 'is_active' => true]);
    $level = EducationPlanLevel::create(['education_plan_version_id' => $version->id, 'external_identifier' => 'M', 'label' => 'Mittleres Niveau', 'position' => 2]);
    EducationPlanCompetenceVariant::create(['education_plan_competency_id' => $competency->id, 'education_plan_level_id' => $level->id, 'text' => 'die M-Kompetenzvariante', 'position' => 1]);
    $fixture['task']->updateQuietly(['education_plan_id' => $plan->id, 'education_plan_competency_id' => $competency->id]);
    StudentAssessmentResult::create(['assessment_id' => $fixture['assessment']->id, 'assessment_task_id' => $fixture['task']->id, 'student_id' => $fixture['student']->id, 'points' => 6, 'level' => 'M']);
    $user = $fixture['user'];

    $response = $this->actingAs($user)->get("/unterrichtsgruppen/{$fixture['assessment']->teaching_group_id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/ergebnisbericht?student={$fixture['student']->id}");
    $path = tempnam(sys_get_temp_dir(), 'roo-test-variant-report-');
    file_put_contents($path, $response->getContent());
    $archive = new ZipArchive;
    $archive->open($path);
    $content = $archive->getFromName('content.xml');
    $archive->close();
    unlink($path);

    expect($content)->toContain('die M-Kompetenzvariante')
        ->not->toContain('3.1.1')
        ->not->toContain('G-Kompetenz');
});

it('uses the student level competency variant in evaluation result groups', function () {
    $fixture = assessmentEvaluationDataFixture();
    $plan = EducationPlan::create(['user_id' => $fixture['user']->id, 'external_identifier' => 'LEVEL', 'subject' => 'Religion', 'title' => 'Niveausplan']);
    $version = EducationPlanVersion::create(['education_plan_id' => $plan->id, 'external_identifier' => 'LEVEL-1', 'schema_version' => '1', 'title' => 'Version', 'raw_payload' => []]);
    $area = EducationPlanCompetenceArea::create(['education_plan_version_id' => $version->id, 'kind' => 'content', 'external_identifier' => '3.1', 'title' => 'Inhalt', 'position' => 1]);
    $competency = EducationPlanCompetency::create(['education_plan_competence_area_id' => $area->id, 'external_identifier' => '3.1.1', 'text' => 'die Grundkompetenz', 'position' => 1, 'is_active' => true]);
    $level = EducationPlanLevel::create(['education_plan_version_id' => $version->id, 'external_identifier' => 'M', 'label' => 'Mittleres Niveau', 'position' => 2]);
    EducationPlanCompetenceVariant::create(['education_plan_competency_id' => $competency->id, 'education_plan_level_id' => $level->id, 'text' => 'die M-Kompetenzvariante', 'position' => 1]);
    $fixture['task']->updateQuietly(['education_plan_id' => $plan->id, 'education_plan_competency_id' => $competency->id]);
    $fixture['assessment']->booklets()->create(['student_id' => $fixture['student']->id, 'number' => 1, 'status' => 'open', 'source' => 'manual']);
    StudentAssessmentResult::create(['assessment_id' => $fixture['assessment']->id, 'assessment_task_id' => $fixture['task']->id, 'student_id' => $fixture['student']->id, 'points' => 1, 'level' => 'M']);
    $user = $fixture['user'];

    $this->actingAs($user)->get("/unterrichtsgruppen/{$fixture['assessment']->teaching_group_id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung")
        ->assertInertia(fn ($page) => $page->where('results.0.competencies.0.title', 'Du kannst die M-Kompetenzvariante.'));
});

it('includes the scanned student level, task details, notes, and branded page furniture in the result report', function () {
    $fixture = assessmentEvaluationDataFixture();
    $fixture['task']->updateQuietly([
        'task_type' => 'sorting',
        'max_points' => 4,
        'content' => ['questions' => [['id' => 'first', 'label' => 'Ich'], ['id' => 'second', 'label' => 'bin']], 'points_per_sentence' => 2],
    ]);
    $booklet = $fixture['assessment']->booklets()->create([
        'student_id' => $fixture['student']->id,
        'number' => 1,
        'status' => 'open',
        'level' => 'E',
    ]);
    AssessmentTaskReview::create([
        'assessment_booklet_id' => $booklet->id,
        'assessment_task_id' => $fixture['task']->id,
        'extra_note' => 'Zusätzliche Anmerkung',
        'sorting_sequence' => ['first' => 2, 'second' => 1],
    ]);
    AssessmentTaskReviewItem::create([
        'assessment_task_review_id' => $booklet->reviews()->sole()->id,
        'assessment_task_expectation_id' => $fixture['expectation']->id,
        'occurrence' => 1,
        'awarded_points' => 2,
        'note' => 'Hier genauer prüfen',
    ]);
    StudentAssessmentResult::create([
        'assessment_id' => $fixture['assessment']->id,
        'assessment_task_id' => $fixture['task']->id,
        'student_id' => $fixture['student']->id,
        'points' => 2,
        'level' => 'E',
    ]);
    $user = $fixture['user'];

    $response = $this->actingAs($user)->get("/unterrichtsgruppen/{$fixture['assessment']->teaching_group_id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/ergebnisbericht?student={$fixture['student']->id}");
    $path = tempnam(sys_get_temp_dir(), 'roo-test-result-report-');
    file_put_contents($path, $response->getContent());
    $archive = new ZipArchive;
    $archive->open($path);
    $content = $archive->getFromName('content.xml');
    $styles = $archive->getFromName('styles.xml');
    $archive->close();
    unlink($path);

    expect($content)->not->toContain('Lernstandserhebung Schöpfung (E)')
        ->toContain('Hier genauer prüfen')
        ->toContain('Zusätzliche Anmerkung')
        ->toContain('Richtige Lösung: Ich · bin.')
        ->toContain('Deine Lösung: bin · Ich')
        ->toContain('Erreicht: 2 / 4 VP')
        ->not->toContain('Mara Muster</text:span></text:p>')
        ->and($styles)->toContain('Lernstandserhebung Schöpfung (E)')
        ->toContain('assessmentFooterText')
        ->toContain('Mara Muster')
        ->toContain('FirstPage')
        ->toContain('resultHeader')
        ->toContain('24pt')
        ->toContain('Resultate')
        ->toContain('Seite')
        ->toContain('text:page-number')
        ->not->toContain('text:page-count')
        ->toContain('style:column-width="10.29cm"')
        ->toContain('style:column-width="1.69cm"')
        ->toContain('style:column-width="5.12cm"')
        ->and($content.$styles)->toContain('fo:background-color="#5B9BD5"')
        ->toContain('fo:background-color="#E7E6E6"')
        ->not->toContain('████')
        ->toContain('style:width="17.1cm"')
        ->toContain('color="#000000"')
        ->and($content)->toMatch('/Auswertungsschule, [^<]+<\/text:span><\/text:p><text:p[^>]*><text:span[^>]*>[^<]+<\/text:span>/s')
        ->and($content)->toContain('style:page-number="1"');
});

it('druckt nur aktive Aufgaben, Gewichtungen und Noten nur für benotete Schüler', function () {
    $fixture = assessmentEvaluationDataFixture();
    $plan = EducationPlan::create(['user_id' => $fixture['user']->id, 'external_identifier' => 'REPORT', 'subject' => 'Religion', 'title' => 'Berichtsplan']);
    $version = EducationPlanVersion::create(['education_plan_id' => $plan->id, 'external_identifier' => 'REPORT-1', 'schema_version' => '1', 'title' => 'Version', 'raw_payload' => []]);
    $area = EducationPlanCompetenceArea::create(['education_plan_version_id' => $version->id, 'kind' => 'content', 'external_identifier' => '3.1', 'title' => 'Inhalt', 'position' => 1]);
    $competency = EducationPlanCompetency::create(['education_plan_competence_area_id' => $area->id, 'external_identifier' => '3.1.1', 'text' => 'Du kannst Inhalte (Zusatz) vergleichen', 'position' => 1, 'is_active' => true]);
    $fixture['task']->updateQuietly(['education_plan_id' => $plan->id, 'education_plan_competency_id' => $competency->id, 'title' => 'Aufgabe M und E']);
    $fixture['task']->levels()->createMany([['level' => 'M'], ['level' => 'E']]);
    $fixture['assessment']->tasks()->updateExistingPivot($fixture['task']->id, ['weight' => 75]);
    $activeTask = AssessmentTask::withoutEvents(fn (): AssessmentTask => AssessmentTask::create(['user_id' => $fixture['user']->id, 'education_plan_id' => $plan->id, 'education_plan_competency_id' => $competency->id, 'title' => 'Aufgabe ebenfalls M']));
    $activeTask->levels()->create(['level' => 'M']);
    $activeTask->expectations()->create(['text' => 'Ebenfalls drucken', 'points' => 1, 'position' => 1]);
    $fixture['assessment']->tasks()->attach($activeTask, ['position' => 2, 'weight' => 25]);
    $inactiveTask = AssessmentTask::withoutEvents(fn (): AssessmentTask => AssessmentTask::create(['user_id' => $fixture['user']->id, 'education_plan_id' => $plan->id, 'education_plan_competency_id' => $competency->id, 'title' => 'Aufgabe nur G']));
    $inactiveTask->levels()->create(['level' => 'G']);
    $inactiveTask->expectations()->create(['text' => 'Nicht drucken', 'points' => 1, 'position' => 1]);
    $fixture['assessment']->tasks()->attach($inactiveTask, ['position' => 3, 'weight' => 10]);
    $booklet = $fixture['assessment']->booklets()->create(['student_id' => $fixture['student']->id, 'number' => 1, 'status' => 'open', 'level' => 'M']);
    StudentAssessmentResult::create(['assessment_id' => $fixture['assessment']->id, 'assessment_task_id' => $fixture['task']->id, 'student_id' => $fixture['student']->id, 'points' => 6, 'level' => 'M']);
    $user = $fixture['user'];

    $response = $this->actingAs($user)->get("/unterrichtsgruppen/{$fixture['assessment']->teaching_group_id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/ergebnisbericht?student={$fixture['student']->id}");
    $path = tempnam(sys_get_temp_dir(), 'roo-test-filtered-report-');
    file_put_contents($path, $response->getContent());
    $archive = new ZipArchive;
    $archive->open($path);
    $content = $archive->getFromName('content.xml');
    $archive->close();
    unlink($path);

    expect($content)->toContain('Aufgabe M und E')
        ->not->toContain('Aufgabe nur G')
        ->toContain('75 %')
        ->toContain('Du kannst Inhalte vergleichen.')
        ->not->toContain('Insgesamt hast du')
        ->not->toContain('Für diese LSE erhältst du die Note');

    $fixture['student']->update(['receives_grades' => true]);
    $gradedResponse = $this->actingAs($user)->get("/unterrichtsgruppen/{$fixture['assessment']->teaching_group_id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/ergebnisbericht?student={$fixture['student']->id}");
    $gradedPath = tempnam(sys_get_temp_dir(), 'roo-test-graded-report-');
    file_put_contents($gradedPath, $gradedResponse->getContent());
    $gradedArchive = new ZipArchive;
    $gradedArchive->open($gradedPath);
    $gradedContent = $gradedArchive->getFromName('content.xml');
    $gradedArchive->close();
    unlink($gradedPath);

    expect($gradedContent)->toContain('Insgesamt hast du')
        ->toContain('Für diese LSE erhältst du die Note');
});

it('uses ordered unlabelled level variants for the student result report', function () {
    $fixture = assessmentEvaluationDataFixture();
    $plan = EducationPlan::create(['user_id' => $fixture['user']->id, 'external_identifier' => 'ORDERED', 'subject' => 'Religion', 'title' => 'Reihenfolgeplan']);
    $version = EducationPlanVersion::create(['education_plan_id' => $plan->id, 'external_identifier' => 'ORDERED-1', 'schema_version' => '1', 'title' => 'Version', 'raw_payload' => []]);
    $area = EducationPlanCompetenceArea::create(['education_plan_version_id' => $version->id, 'kind' => 'content', 'external_identifier' => '3.1', 'title' => 'Inhalt', 'position' => 1]);
    $competency = EducationPlanCompetency::create(['education_plan_competence_area_id' => $area->id, 'external_identifier' => '3.1.2', 'text' => null, 'position' => 1, 'is_active' => true]);
    foreach (['G' => 'G-Text', 'M' => 'M-Text', 'E' => 'E-Text'] as $text) {
        EducationPlanCompetenceVariant::create(['education_plan_competency_id' => $competency->id, 'text' => $text, 'position' => array_search($text, ['G-Text', 'M-Text', 'E-Text'], true) + 1]);
    }
    $fixture['task']->updateQuietly(['education_plan_id' => $plan->id, 'education_plan_competency_id' => $competency->id]);
    StudentAssessmentResult::create(['assessment_id' => $fixture['assessment']->id, 'assessment_task_id' => $fixture['task']->id, 'student_id' => $fixture['student']->id, 'points' => 6, 'level' => 'E']);
    $user = $fixture['user'];

    $response = $this->actingAs($user)->get("/unterrichtsgruppen/{$fixture['assessment']->teaching_group_id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/ergebnisbericht?student={$fixture['student']->id}");
    $path = tempnam(sys_get_temp_dir(), 'roo-test-ordered-variants-');
    file_put_contents($path, $response->getContent());
    $archive = new ZipArchive;
    $archive->open($path);
    $content = $archive->getFromName('content.xml');
    $archive->close();
    unlink($path);

    expect($content)->toContain('Du kannst E-Text')
        ->not->toContain('Du kannst G-Text')
        ->not->toContain('Du kannst M-Text');
});

it('keeps already assigned legacy tasks editable before competency backfill runs', function () {
    $fixture = assessmentEvaluationDataFixture();
    $user = $fixture['user'];

    $this->actingAs($user)->put("/unterrichtsgruppen/{$fixture['assessment']->teaching_group_id}/lernstandserhebungen/{$fixture['assessment']->id}", [
        'title' => $fixture['assessment']->title,
        'grade_component_id' => null,
        'tasks' => [['task_id' => $fixture['task']->id, 'weight' => 75]],
    ])->assertRedirect();

    expect($fixture['assessment']->tasks()->first()->pivot->weight)->toBe(75);
});

it('persists a booklet with its fragment and task review', function () {
    $fixture = assessmentEvaluationDataFixture();

    $booklet = AssessmentBooklet::create([
        'assessment_id' => $fixture['assessment']->id,
        'student_id' => $fixture['student']->id,
        'number' => 1,
        'status' => 'open',
        'name_fragment_path' => 'assessment-booklets/1/name.png',
    ]);
    $fragment = AssessmentBookletFragment::create([
        'assessment_booklet_id' => $booklet->id,
        'assessment_task_id' => $fixture['task']->id,
        'image_path' => 'assessment-booklets/1/task-1.png',
        'page' => 2,
        'start_y_cm' => 4.5,
        'end_y_cm' => 12.25,
    ]);
    $review = AssessmentTaskReview::create([
        'assessment_booklet_id' => $booklet->id,
        'assessment_task_id' => $fixture['task']->id,
        'extra_points' => -2,
        'extra_note' => 'Formfehler',
    ]);

    expect($fixture['assessment']->booklets()->sole()->id)->toBe($booklet->id)
        ->and($booklet->fragments()->sole()->id)->toBe($fragment->id)
        ->and($booklet->reviews()->sole()->extra_points)->toBe(-2)
        ->and($fixture['task']->fragments()->sole()->image_path)->toBe('assessment-booklets/1/task-1.png')
        ->and($fixture['task']->reviews()->sole()->id)->toBe($review->id)
        ->and($fixture['student']->assessmentBooklets()->sole()->id)->toBe($booklet->id);
});

it('stores repeated expectation review rows separately', function () {
    $fixture = assessmentEvaluationDataFixture();
    $booklet = AssessmentBooklet::create([
        'assessment_id' => $fixture['assessment']->id,
        'number' => 1,
        'status' => 'open',
    ]);
    $review = AssessmentTaskReview::create([
        'assessment_booklet_id' => $booklet->id,
        'assessment_task_id' => $fixture['task']->id,
    ]);

    foreach ([1, 2, 3] as $occurrence) {
        AssessmentTaskReviewItem::create([
            'assessment_task_review_id' => $review->id,
            'assessment_task_expectation_id' => $fixture['expectation']->id,
            'occurrence' => $occurrence,
            'awarded_points' => 2.5,
        ]);
    }

    expect($review->items()->orderBy('occurrence')->pluck('occurrence')->all())->toBe([1, 2, 3])
        ->and($review->items()->firstOrFail()->awarded_points)->toBe('2.50');
});

it('stores checkbox option selections separately from expectation review rows', function () {
    $fixture = assessmentEvaluationDataFixture();
    $booklet = AssessmentBooklet::create([
        'assessment_id' => $fixture['assessment']->id,
        'number' => 1,
        'status' => 'open',
    ]);
    $review = AssessmentTaskReview::create([
        'assessment_booklet_id' => $booklet->id,
        'assessment_task_id' => $fixture['task']->id,
    ]);

    $review->options()->createMany([
        ['option_id' => 'a1', 'selected' => true],
        ['option_id' => 'a2', 'selected' => false],
    ]);

    expect($review->options()->orderBy('option_id')->get()->map(fn (AssessmentTaskReviewOption $option): array => [$option->option_id, $option->selected])->all())
        ->toBe([['a1', true], ['a2', false]]);
});

it('allows an assigned student only once among open booklets of an assessment', function () {
    $fixture = assessmentEvaluationDataFixture();

    AssessmentBooklet::create([
        'assessment_id' => $fixture['assessment']->id,
        'student_id' => $fixture['student']->id,
        'number' => 1,
        'status' => 'open',
    ]);
    $discarded = AssessmentBooklet::create([
        'assessment_id' => $fixture['assessment']->id,
        'student_id' => $fixture['student']->id,
        'number' => 2,
        'status' => 'discarded',
    ]);

    expect(fn () => AssessmentBooklet::create([
        'assessment_id' => $fixture['assessment']->id,
        'student_id' => $fixture['student']->id,
        'number' => 3,
        'status' => 'open',
    ]))->toThrow(QueryException::class);

    expect(fn () => $discarded->update(['status' => 'open']))->toThrow(QueryException::class);
});

it('allows only one legacy result for the same task and student', function () {
    $fixture = assessmentEvaluationDataFixture();

    StudentAssessmentResult::create([
        'assessment_id' => null,
        'assessment_task_id' => $fixture['task']->id,
        'student_id' => $fixture['student']->id,
        'points' => 3,
    ]);

    expect(fn () => StudentAssessmentResult::create([
        'assessment_id' => null,
        'assessment_task_id' => $fixture['task']->id,
        'student_id' => $fixture['student']->id,
        'points' => 4,
    ]))->toThrow(QueryException::class);
});
