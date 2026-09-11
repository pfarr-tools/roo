<?php

use App\Models\Assessment;
use App\Models\AssessmentTask;
use App\Models\AssessmentTaskExpectation;
use App\Models\AssessmentTaskImage;
use App\Models\EducationPlan;
use App\Models\EducationPlanCompetenceArea;
use App\Models\EducationPlanCompetency;
use App\Models\EducationPlanVersion;
use App\Models\ResourceReference;
use App\Models\ScheduledLesson;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentAssessmentResult;
use App\Models\TeachingGroup;
use App\Models\TeachingGroupGradeLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('legt eine Lernstandserhebung zunächst ohne Aufgaben an', function () {
    $user = User::factory()->create();
    $school = School::create(['user_id' => $user->id, 'name' => 'Aufgabenlose Schule']);
    $year = SchoolYear::create(['user_id' => $user->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['user_id' => $user->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '5a']);

    $this->actingAs($user)->post("/unterrichtsgruppen/{$group->id}/lernstandserhebungen", ['title' => 'LSE ohne Aufgaben', 'grade_component_id' => null])->assertRedirect();

    expect(Assessment::first()->tasks)->toBeEmpty();
});

it('legt eine Lernstandserhebung mit differenzierten Aufgaben an', function () {
    $user = User::factory()->create();
    $school = School::create(['user_id' => $user->id, 'name' => 'Bewertungsschule']);
    $year = SchoolYear::create(['user_id' => $user->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['user_id' => $user->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '5a']);
    $unit = $group->teachingUnits()->create(['user_id' => $user->id, 'title' => 'Einheit', 'position' => 1]);
    $competency = officialCompetency($unit, 'Kann erklären');

    $this->actingAs($user)->post("/unterrichtsgruppen/{$group->id}/lernstandserhebungen", ['title' => 'LSE Schöpfung', 'grade_component_id' => null, 'assessed_on' => '2026-11-12', 'tasks' => [['title' => 'Erkläre den Begriff', 'max_points' => 10, 'level' => 'M', 'competency_id' => $competency->id]]])->assertRedirect();

    expect(Assessment::first()->tasks)->toHaveCount(1)
        ->and(Assessment::first()->tasks->first()->level)->toBe('M')
        ->and(Assessment::first()->is_differentiated)->toBeTrue();
});

it('verwendet eine Bibliotheksaufgabe in mehreren Erhebungen und verlangt mehrere G/M/E-Niveaus', function () {
    $user = User::factory()->create();
    $school = School::create(['user_id' => $user->id, 'name' => 'Differenzierte Schule']);
    $year = SchoolYear::create(['user_id' => $user->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['user_id' => $user->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '5 G/M/E']);
    TeachingGroupGradeLevel::create(['teaching_group_id' => $group->id, 'grade_level' => 'G/M/E']);
    $unit = $group->teachingUnits()->create(['user_id' => $user->id, 'title' => 'Einheit', 'position' => 1]);
    $competency = officialCompetency($unit, 'Kann vergleichen');
    $task = AssessmentTask::create(['user_id' => $user->id, 'education_plan_id' => $competency->area->version->education_plan_id, 'education_plan_competency_id' => $competency->id, 'title' => 'Vergleiche', 'max_points' => 6]);

    $payload = ['title' => 'LSE', 'grade_component_id' => null, 'tasks' => [['task_id' => $task->id, 'levels' => ['G', 'M']]]];
    $this->actingAs($user)->post("/unterrichtsgruppen/{$group->id}/lernstandserhebungen", $payload)->assertRedirect();
    $this->actingAs($user)->post("/unterrichtsgruppen/{$group->id}/lernstandserhebungen", $payload)->assertRedirect();

    expect($task->fresh()->assessments)->toHaveCount(2)->and($task->fresh()->levels->pluck('level')->all())->toBe(['G', 'M']);
});

it('speichert Reihenfolge und Gewichtung der Aufgaben assessmentbezogen', function () {
    $user = User::factory()->create();
    $school = School::create(['user_id' => $user->id, 'name' => 'Gewichtungsschule']);
    $year = SchoolYear::create(['user_id' => $user->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['user_id' => $user->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => 'Gewichtungsgruppe']);
    $unit = $group->teachingUnits()->create(['user_id' => $user->id, 'title' => 'Einheit', 'position' => 1]);
    $competency = officialCompetency($unit, 'Kann anwenden');
    $lesson = $unit->lessons()->create(['title' => 'Aufgabenstunde', 'position' => 1]);
    $first = AssessmentTask::create(['user_id' => $user->id, 'education_plan_id' => $competency->area->version->education_plan_id, 'education_plan_competency_id' => $competency->id, 'title' => 'Erste Aufgabe', 'max_points' => 4]);
    $second = AssessmentTask::create(['user_id' => $user->id, 'education_plan_id' => $competency->area->version->education_plan_id, 'education_plan_competency_id' => $competency->id, 'title' => 'Zweite Aufgabe', 'max_points' => 8]);
    $lesson->assessmentTasks()->attach([$first->id, $second->id]);
    $assessment = Assessment::create(['user_id' => $user->id, 'teaching_group_id' => $group->id, 'title' => 'LSE Gewichtung']);

    $this->actingAs($user)->put("/unterrichtsgruppen/{$group->id}/lernstandserhebungen/{$assessment->id}", [
        'title' => 'LSE Gewichtung',
        'grade_component_id' => null,
        'tasks' => [
            ['task_id' => $second->id, 'weight' => 100],
            ['task_id' => $first->id, 'weight' => 0],
        ],
    ])->assertRedirect();

    expect($assessment->fresh()->tasks->pluck('id')->all())->toBe([$second->id, $first->id])
        ->and($assessment->fresh()->tasks->pluck('pivot.weight')->all())->toBe([100, 0]);

    $this->actingAs($user)->put("/unterrichtsgruppen/{$group->id}/lernstandserhebungen/{$assessment->id}", [
        'title' => 'LSE Gewichtung',
        'grade_component_id' => null,
        'tasks' => [
            ['task_id' => $first->id, 'weight' => 25],
            ['task_id' => $second->id, 'weight' => 75],
        ],
    ])->assertRedirect();

    expect($assessment->fresh()->tasks->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($assessment->fresh()->tasks->pluck('pivot.weight')->all())->toBe([25, 75]);
});

it('liefert alle inhaltsbezogenen Kompetenzen des relevanten Zeitraums auch ohne Aufgabe', function () {
    $user = User::factory()->create();
    $school = School::create(['user_id' => $user->id, 'name' => 'Kompetenzgruppenschule']);
    $year = SchoolYear::create(['user_id' => $user->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['user_id' => $user->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '5a']);
    $plan = EducationPlan::create(['user_id' => $user->id, 'external_identifier' => 'BP', 'subject' => 'Religion', 'title' => 'Bildungsplan']);
    $version = EducationPlanVersion::create(['education_plan_id' => $plan->id, 'external_identifier' => '2026', 'schema_version' => '1', 'title' => '2026', 'is_complete' => true, 'raw_payload' => []]);
    $area = EducationPlanCompetenceArea::create(['education_plan_version_id' => $version->id, 'kind' => 'content', 'external_identifier' => '3.1', 'title' => 'Inhalt', 'position' => 1]);
    $withoutTask = EducationPlanCompetency::create(['education_plan_competence_area_id' => $area->id, 'external_identifier' => '3.1.1', 'text' => 'Ohne Aufgabe', 'position' => 1, 'is_active' => true]);
    $withTask = EducationPlanCompetency::create(['education_plan_competence_area_id' => $area->id, 'external_identifier' => '3.1.2', 'text' => 'Mit Aufgabe', 'position' => 2, 'is_active' => true]);
    $unit = $group->teachingUnits()->create(['user_id' => $user->id, 'title' => 'Einheit', 'position' => 1]);
    $lesson = $unit->lessons()->create(['title' => 'Stunde', 'position' => 1, 'duration' => 1]);
    $unit->educationPlanCompetencies()->attach([$withoutTask->id, $withTask->id]);
    $lesson->educationPlanCompetencies()->attach([$withoutTask->id, $withTask->id]);
    $task = AssessmentTask::create(['user_id' => $user->id, 'education_plan_id' => $plan->id, 'education_plan_competency_id' => $withTask->id, 'title' => 'Aufgabe']);
    $lesson->assessmentTasks()->attach($task);
    $assessment = Assessment::create(['user_id' => $user->id, 'teaching_group_id' => $group->id, 'title' => 'LSE', 'assessed_on' => '2026-10-01']);
    $slot = ScheduleSlot::create(['teaching_group_id' => $group->id, 'assessment_id' => $assessment->id, 'date' => '2026-10-01', 'period_number' => 1, 'starts_at' => '08:00', 'ends_at' => '08:45', 'status' => 'lse']);
    ScheduledLesson::create(['lesson_id' => $lesson->id, 'schedule_slot_id' => $slot->id]);

    $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}/lernstandserhebungen/{$assessment->id}/bearbeiten")
        ->assertInertia(fn ($page) => $page
            ->has('assessmentCompetencies', 2)
            ->where('assessmentCompetencies.0.title', 'Du kannst Mit Aufgabe (3.1.2)')
            ->where('assessmentCompetencies.1.title', 'Du kannst Ohne Aufgabe (3.1.1)'));
});

it('lädt eine Lernstandserhebung als ODT herunter', function () {
    $user = User::factory()->create();
    $school = School::create(['user_id' => $user->id, 'name' => 'ODT Schule']);
    $year = SchoolYear::create(['user_id' => $user->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['user_id' => $user->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '2a']);
    $group->gradeLevels()->create(['grade_level' => '2']);
    $assessment = Assessment::create(['user_id' => $user->id, 'teaching_group_id' => $group->id, 'title' => 'LSE Lesen', 'assessed_on' => '2026-10-01']);

    $response = $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}/lernstandserhebungen/{$assessment->id}/download")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.oasis.opendocument.text')
        ->assertHeader('Content-Disposition', 'attachment; filename="2026-27_2a_20261001 LSE Lesen.odt"');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    $docxResponse = $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}/lernstandserhebungen/{$assessment->id}/download?level=M&format=docx&template=primary-school-lower-secondary")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->assertHeader('Content-Disposition', 'attachment; filename="2026-27_2a_20261001 LSE Lesen.docx"');

    expect($docxResponse->getContent())->not->toBeEmpty();

    $secondaryResponse = $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}/lernstandserhebungen/{$assessment->id}/download?format=odt&template=secondary")
        ->assertOk();
    $secondaryPath = tempnam(sys_get_temp_dir(), 'roo-secondary-download-');
    file_put_contents($secondaryPath, $secondaryResponse->getContent());
    $secondaryArchive = new ZipArchive;
    $secondaryArchive->open($secondaryPath);
    $secondaryContent = (string) $secondaryArchive->getFromName('styles.xml').$secondaryArchive->getFromName('content.xml');
    $secondaryArchive->close();
    unlink($secondaryPath);
    expect($secondaryContent)->toContain('Atkinson Hyperlegible Next')
        ->toContain('D9D9D9')
        ->toContain('fo:font-size="10pt"')
        ->toContain('svg:x="0.3cm"')
        ->toContain('01.10.2026')
        ->toContain('Name: ________________________________________')
        ->toContain('assessmentHeaderMeta')
        ->toContain('assessmentHeaderBand')
        ->toContain('fo:background-color="#D9D9D9"')
        ->toContain('<text:tab/>')
        ->toContain('assessmentRooMark')
        ->toContain('translate (0.5cm 0.252236111111111cm)')
        ->not->toContain('assessmentHeaderLineObject')
        ->not->toContain('Comic Neue')
        ->not->toContain('M 1');
});

it('lädt bei einer differenzierten Lernstandserhebung nur das gewählte Niveau', function () {
    $user = User::factory()->create();
    $school = School::create(['user_id' => $user->id, 'name' => 'Niveau Schule']);
    $year = SchoolYear::create(['user_id' => $user->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['user_id' => $user->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '5a']);
    $unit = $group->teachingUnits()->create(['user_id' => $user->id, 'title' => 'Einheit', 'position' => 1]);
    $competency = officialCompetency($unit, 'Kann unterscheiden');

    $this->actingAs($user)->post("/unterrichtsgruppen/{$group->id}/lernstandserhebungen", [
        'title' => 'LSE Niveaus',
        'grade_component_id' => null,
        'tasks' => [
            ['title' => 'G-Aufgabe', 'max_points' => 4, 'level' => 'G', 'competency_id' => $competency->id],
            ['title' => 'E-Aufgabe', 'max_points' => 8, 'level' => 'E', 'competency_id' => $competency->id],
        ],
    ])->assertRedirect();
    $assessment = Assessment::firstOrFail();

    $response = $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}/lernstandserhebungen/{$assessment->id}/download?level=G")->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'roo-test-level-download-');
    file_put_contents($path, $response->getContent());
    $archive = new ZipArchive;
    $archive->open($path);
    $content = $archive->getFromName('content.xml');
    $archive->close();
    unlink($path);

    expect($content)->toContain('G-Aufgabe')->not->toContain('E-Aufgabe');
});

it('übernimmt Freitextbilder in den produktiven ODT-Download', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $school = School::create(['user_id' => $user->id, 'name' => 'Freitextbild Schule']);
    $year = SchoolYear::create(['user_id' => $user->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['user_id' => $user->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '2a']);
    $group->gradeLevels()->create(['grade_level' => '2']);
    $assessment = Assessment::create(['user_id' => $user->id, 'teaching_group_id' => $group->id, 'title' => 'LSE Freitext', 'assessed_on' => '2026-10-01']);
    $task = AssessmentTask::withoutEvents(fn (): AssessmentTask => AssessmentTask::create(['user_id' => $user->id, 'title' => 'Beschreibe das Bild', 'task_type' => 'free_text', 'content' => ['prompt' => 'Beschreibe das Bild.', 'lines' => 2, 'image_width_cm' => 3, 'optional_reading_text' => 'Lies den Begleittext.']]));
    $assessment->tasks()->attach($task);
    Storage::disk('local')->put('free-text.png', file_get_contents(base_path('resources/images/branding/roo-icon.png')));
    $resource = ResourceReference::create(['user_id' => $user->id, 'original_name' => 'Freitext.png', 'storage_path' => 'free-text.png', 'mime_type' => 'image/png', 'size' => 10]);
    AssessmentTaskImage::create(['assessment_task_id' => $task->id, 'resource_reference_id' => $resource->id, 'identifier' => 'free-text-image', 'position' => 0]);

    $response = $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}/lernstandserhebungen/{$assessment->id}/download")->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'roo-test-download-');
    file_put_contents($path, $response->getContent());
    $archive = new ZipArchive;
    $archive->open($path);
    $content = $archive->getFromName('content.xml');
    $archive->close();
    unlink($path);

    expect($content)->toContain('Pictures/section_image1.png')
        ->and($content)->toContain('Lies den Begleittext.');
});

it('druckt einen Ergebnisbericht für einen Schüler oder die gesamte Gruppe', function () {
    $user = User::factory()->create();
    $school = School::create(['user_id' => $user->id, 'name' => 'Ergebnisbericht Schule']);
    $year = SchoolYear::create(['user_id' => $user->id, 'school_id' => $school->id, 'name' => '2026/27', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31']);
    $group = TeachingGroup::create(['user_id' => $user->id, 'school_id' => $school->id, 'school_year_id' => $year->id, 'name' => '7ab', 'aktenzeichen' => '62.55']);
    $group->gradeLevels()->create(['grade_level' => '7 M']);
    $student = Student::create(['user_id' => $user->id, 'school_id' => $school->id, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'class_name' => '7ab']);
    $group->students()->attach($student);
    $assessment = Assessment::create(['user_id' => $user->id, 'teaching_group_id' => $group->id, 'title' => 'Test LSE', 'assessed_on' => '2026-09-06']);
    $unit = $group->teachingUnits()->create(['user_id' => $user->id, 'title' => 'Test Einheit', 'position' => 1]);
    $competency = officialCompetency($unit, 'Kann testen');
    $task = AssessmentTask::create(['user_id' => $user->id, 'education_plan_id' => $competency->area->version->education_plan_id, 'education_plan_competency_id' => $competency->id, 'title' => 'Mock-Aufgabe', 'task_type' => 'checkbox', 'max_points' => 5]);
    $assessment->tasks()->attach($task);
    AssessmentTaskExpectation::create(['assessment_task_id' => $task->id, 'text' => 'Erwartung erfüllt', 'points' => 5, 'position' => 1]);
    StudentAssessmentResult::create(['assessment_id' => $assessment->id, 'assessment_task_id' => $task->id, 'student_id' => $student->id, 'points' => 5, 'numeric_grade' => '1']);

    $single = $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}/lernstandserhebungen/{$assessment->id}/auswertung/ergebnisbericht?student={$student->id}&format=odt");
    $single->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.oasis.opendocument.text')
        ->assertHeader('Content-Disposition', 'attachment; filename="62.55_7ab_20260906 Test LSE Lovelace, Ada Ergebnis.odt"');
    $singlePath = tempnam(sys_get_temp_dir(), 'roo-test-result-report-');
    file_put_contents($singlePath, $single->getContent());
    $singleArchive = new ZipArchive;
    $singleArchive->open($singlePath);
    $singleContent = $singleArchive->getFromName('content.xml');
    $singleArchive->close();
    unlink($singlePath);
    expect($singleContent)->toContain('Mock-Aufgabe')->toContain('Erwartung erfüllt');

    $all = $this->actingAs($user)->get("/unterrichtsgruppen/{$group->id}/lernstandserhebungen/{$assessment->id}/auswertung/ergebnisbericht?format=docx");
    $all->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->assertHeader('Content-Disposition', 'attachment; filename="62.55_7ab_20260906 Test LSE Ergebnisse.docx"');
    expect($all->getContent())->not->toBeEmpty();
});
