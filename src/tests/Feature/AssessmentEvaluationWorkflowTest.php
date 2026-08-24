<?php

use App\Models\Assessment;
use App\Models\AssessmentBooklet;
use App\Models\AssessmentBookletFragment;
use App\Models\AssessmentTask;
use App\Models\AssessmentTaskExpectation;
use App\Models\AssessmentTaskImage;
use App\Models\AssessmentTaskReview;
use App\Models\AssessmentTaskReviewOption;
use App\Models\Organization;
use App\Models\ResourceReference;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentAssessmentResult;
use App\Models\TeachingGroup;
use App\Models\User;
use App\Services\AssessmentEvaluation\MaterializeAssessmentScan;
use App\Services\AssessmentEvaluation\SaveAssessmentTaskReview;
use App\Services\AssessmentScan\AssessmentScanSessionStore;
use App\Services\AssessmentScan\DataMatrixDecoder;
use App\Services\AssessmentScan\DmtxReadDecoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function assessmentEvaluationWorkflowFixture(int $bookletCount = 1): array
{
    $organization = Organization::create(['name' => 'Auswertungsworkflow Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Auswertungsworkflow Schule']);
    $schoolYear = SchoolYear::create([
        'organization_id' => $organization->id,
        'school_id' => $school->id,
        'name' => '2026/27',
        'starts_on' => '2026-09-01',
        'ends_on' => '2027-07-31',
    ]);
    $group = TeachingGroup::create([
        'organization_id' => $organization->id,
        'school_id' => $school->id,
        'school_year_id' => $schoolYear->id,
        'name' => '4a Religion',
    ]);
    $assessment = Assessment::create([
        'organization_id' => $organization->id,
        'teaching_group_id' => $group->id,
        'title' => 'Lernstandserhebung Schöpfung',
    ]);
    $task = AssessmentTask::withoutEvents(fn (): AssessmentTask => AssessmentTask::create([
        'organization_id' => $organization->id,
        'title' => 'Aufgabe eins',
    ]));
    $assessment->tasks()->attach($task, ['position' => 1]);
    $expectation = AssessmentTaskExpectation::create([
        'assessment_task_id' => $task->id,
        'text' => 'Nennt drei Beispiele.',
        'points' => 2,
        'repetitions' => 3,
        'position' => 1,
    ]);
    $student = Student::create([
        'organization_id' => $organization->id,
        'school_id' => $school->id,
        'first_name' => 'Mara',
        'last_name' => 'Muster',
        'class_name' => '4a',
    ]);
    $secondStudent = Student::create([
        'organization_id' => $organization->id,
        'school_id' => $school->id,
        'first_name' => 'Noah',
        'last_name' => 'Nachname',
        'class_name' => '4a',
    ]);
    $nonMember = Student::create([
        'organization_id' => $organization->id,
        'school_id' => $school->id,
        'first_name' => 'Tina',
        'last_name' => 'Fremd',
        'class_name' => '4b',
    ]);
    $group->students()->attach([$student->id, $secondStudent->id]);

    $booklets = collect(range(1, $bookletCount))->map(function (int $number) use ($assessment, $task): AssessmentBooklet {
        $booklet = AssessmentBooklet::create([
            'assessment_id' => $assessment->id,
            'number' => $number,
            'status' => 'open',
            'name_fragment_path' => "assessment-booklets/{$assessment->id}/{$number}/name.png",
        ]);
        AssessmentBookletFragment::create([
            'assessment_booklet_id' => $booklet->id,
            'assessment_task_id' => $task->id,
            'image_path' => "assessment-booklets/{$assessment->id}/{$number}/task.png",
            'page' => $number,
            'start_y_cm' => 4.0,
            'end_y_cm' => 10.0,
        ]);

        return $booklet;
    });

    return compact('user', 'organization', 'school', 'schoolYear', 'group', 'assessment', 'task', 'expectation', 'student', 'secondStudent', 'nonMember', 'booklets');
}

it('renders the evaluation workspace with group students, booklet progress, and task fragments', function () {
    $fixture = assessmentEvaluationWorkflowFixture(1);

    $this->actingAs($fixture['user'])
        ->get("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Assessment/Assess')
            ->where('assessment.id', $fixture['assessment']->id)
            ->where('scan.booklets.0.number', 1)
            ->where('fragments.0.booklet', 1)
            ->where('students.0.id', $fixture['student']->id)
            ->where('students.1.id', $fixture['secondStudent']->id)
            ->missing('students.2')
            ->where('booklets.0.id', $fixture['booklets'][0]->id)
            ->where('progress.total_booklets', 1)
            ->where('progress.unassigned_booklets', 1)
            ->where('progress.reviewable_fragments', 1)
            ->where('tasks.0.expectations.0.repetitions', 3)
            ->where('taskFragments.0.assessment_task_id', $fixture['task']->id));
});

it('stores checkbox selections and synchronizes option and manual points', function () {
    $fixture = assessmentEvaluationWorkflowFixture(1);
    $task = AssessmentTask::withoutEvents(fn (): AssessmentTask => AssessmentTask::create([
        'organization_id' => $fixture['organization']->id,
        'title' => 'Checkbox-Aufgabe',
        'task_type' => 'checkbox',
        'content' => [
            'options' => [
                ['id' => 'a1', 'text' => 'Richtig', 'correct' => true],
                ['id' => 'a2', 'text' => 'Falsch', 'correct' => false],
            ],
            'points_per_correct_answer' => 2,
        ],
    ]));
    $fixture['assessment']->tasks()->attach($task, ['position' => 2]);
    $expectation = AssessmentTaskExpectation::create([
        'assessment_task_id' => $task->id,
        'text' => 'Zusatzmerkmal',
        'points' => 1,
        'repetitions' => 1,
        'position' => 1,
    ]);
    $fixture['booklets'][0]->update(['student_id' => $fixture['student']->id]);

    app(SaveAssessmentTaskReview::class)->handle($fixture['booklets'][0], $task, [
        'options' => [
            ['id' => 'a1', 'selected' => true],
            ['id' => 'a2', 'selected' => true],
        ],
        'items' => [[
            'expectation_id' => $expectation->id,
            'occurrence' => 1,
            'awarded_points' => 1,
        ]],
        'extra_points' => 0,
    ]);

    expect($task->reviews()->sole()->options()->pluck('selected', 'option_id')->all())->toBe(['a1' => true, 'a2' => true])
        ->and(StudentAssessmentResult::where('assessment_task_id', $task->id)->where('student_id', $fixture['student']->id)->value('points'))->toBe('3.00');
});

it('stores image matching selections and synchronizes their points', function () {
    $fixture = assessmentEvaluationWorkflowFixture(1);
    $task = AssessmentTask::withoutEvents(fn (): AssessmentTask => AssessmentTask::create([
        'organization_id' => $fixture['organization']->id,
        'title' => 'Bildzuordnung',
        'task_type' => 'image_matching',
        'content' => ['points_per_correct_answer' => 2],
    ]));
    AssessmentTaskImage::create(['assessment_task_id' => $task->id, 'resource_reference_id' => ResourceReference::create(['organization_id' => $fixture['organization']->id, 'original_name' => 'Löwe.png', 'storage_path' => 'library/loewe.png', 'mime_type' => 'image/png'])->id, 'identifier' => 'pair-1', 'position' => 0, 'label' => 'Löwe', 'answer' => 'Mut']);
    AssessmentTaskImage::create(['assessment_task_id' => $task->id, 'resource_reference_id' => ResourceReference::create(['organization_id' => $fixture['organization']->id, 'original_name' => 'Taube.png', 'storage_path' => 'library/taube.png', 'mime_type' => 'image/png'])->id, 'identifier' => 'pair-2', 'position' => 1, 'label' => 'Taube', 'answer' => 'Frieden']);
    $fixture['assessment']->tasks()->attach($task, ['position' => 2]);
    $fixture['booklets'][0]->update(['student_id' => $fixture['student']->id]);

    app(SaveAssessmentTaskReview::class)->handle($fixture['booklets'][0], $task, [
        'options' => [['id' => 'pair-1', 'selected' => true], ['id' => 'pair-2', 'selected' => false]],
        'items' => [],
        'extra_points' => 0,
    ]);

    expect(StudentAssessmentResult::where('assessment_task_id', $task->id)->where('student_id', $fixture['student']->id)->value('points'))->toBe('2.00');
});

it('exposes checkbox definitions and saved selections in evaluation props', function () {
    $fixture = assessmentEvaluationWorkflowFixture(1);
    AssessmentTask::withoutEvents(fn () => $fixture['task']->update([
        'task_type' => 'checkbox',
        'content' => [
            'options' => [['id' => 'a1', 'text' => 'Richtig', 'correct' => true]],
            'points_per_correct_answer' => 2,
        ],
    ]));
    $review = AssessmentTaskReview::create([
        'assessment_booklet_id' => $fixture['booklets'][0]->id,
        'assessment_task_id' => $fixture['task']->id,
    ]);
    AssessmentTaskReviewOption::create([
        'assessment_task_review_id' => $review->id,
        'option_id' => 'a1',
        'selected' => true,
    ]);

    $this->actingAs($fixture['user'])
        ->get("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung")
        ->assertInertia(fn ($page) => $page
            ->where('tasks.0.task_type', 'checkbox')
            ->where('tasks.0.max_points', 8)
            ->where('tasks.0.checkbox_scoring_mode', 'correct_selections')
            ->where('tasks.0.content.options.0.id', 'a1')
            ->where('tasks.0.content.points_per_correct_answer', 2)
            ->where('taskFragments.0.review.options.0.option_id', 'a1')
            ->where('taskFragments.0.review.options.0.selected', true));
});

it('rejects assessment booklets and fragments outside the requested group', function () {
    Storage::fake('documents');
    $fixture = assessmentEvaluationWorkflowFixture();
    $otherGroup = TeachingGroup::create([
        'organization_id' => $fixture['organization']->id,
        'school_id' => $fixture['school']->id,
        'school_year_id' => $fixture['schoolYear']->id,
        'name' => '4b Religion',
    ]);
    $otherAssessment = Assessment::create([
        'organization_id' => $fixture['organization']->id,
        'teaching_group_id' => $otherGroup->id,
        'title' => 'Andere Lernstandserhebung',
    ]);
    $foreignBooklet = AssessmentBooklet::create([
        'assessment_id' => $otherAssessment->id,
        'number' => 1,
        'status' => 'open',
    ]);
    $foreignFragment = AssessmentBookletFragment::create([
        'assessment_booklet_id' => $foreignBooklet->id,
        'assessment_task_id' => $fixture['task']->id,
        'image_path' => 'assessment-booklets/foreign/task.png',
        'page' => 1,
        'start_y_cm' => 4,
        'end_y_cm' => 10,
    ]);
    Storage::disk('documents')->put($foreignFragment->image_path, 'private image');

    $this->actingAs($fixture['user'])
        ->get("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$otherAssessment->id}/auswertung")
        ->assertNotFound();

    $this->actingAs($fixture['user'])
        ->put("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/booklets/{$foreignBooklet->id}/zuordnung", ['student_id' => $fixture['student']->id])
        ->assertNotFound();

    $this->actingAs($fixture['user'])
        ->get("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/fragments/{$foreignFragment->id}")
        ->assertNotFound();
});

it('rejects assignments to students outside the teaching group', function () {
    $fixture = assessmentEvaluationWorkflowFixture();

    $this->actingAs($fixture['user'])
        ->put("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/booklets/{$fixture['booklets'][0]->id}/zuordnung", ['student_id' => $fixture['nonMember']->id])
        ->assertSessionHasErrors('student_id');

    expect($fixture['booklets'][0]->fresh()->student_id)->toBeNull();
});

it('changes and removes a booklet assignment while rejecting duplicate active assignments', function () {
    $fixture = assessmentEvaluationWorkflowFixture(2);

    $this->actingAs($fixture['user'])
        ->put("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/booklets/{$fixture['booklets'][0]->id}/zuordnung", ['student_id' => $fixture['student']->id])
        ->assertRedirect();

    $this->actingAs($fixture['user'])
        ->put("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/booklets/{$fixture['booklets'][0]->id}/zuordnung", ['student_id' => $fixture['secondStudent']->id])
        ->assertRedirect();

    $this->actingAs($fixture['user'])
        ->put("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/booklets/{$fixture['booklets'][1]->id}/zuordnung", ['student_id' => $fixture['secondStudent']->id])
        ->assertSessionHasErrors('student_id');

    $this->actingAs($fixture['user'])
        ->put("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/booklets/{$fixture['booklets'][0]->id}/zuordnung", ['student_id' => null])
        ->assertRedirect();

    expect($fixture['booklets'][0]->fresh()->student_id)->toBeNull()
        ->and($fixture['booklets'][1]->fresh()->student_id)->toBeNull();
});

it('discards and restores a booklet', function () {
    $fixture = assessmentEvaluationWorkflowFixture();
    $url = "/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/booklets/{$fixture['booklets'][0]->id}/status";

    $this->actingAs($fixture['user'])->patch($url, ['status' => 'discarded'])->assertRedirect();
    expect($fixture['booklets'][0]->fresh()->status)->toBe('discarded');

    $this->actingAs($fixture['user'])->patch($url, ['status' => 'open'])->assertRedirect();
    expect($fixture['booklets'][0]->fresh()->status)->toBe('open');
});

it('allows a new open booklet for a student after the previously assigned booklet was discarded', function () {
    $fixture = assessmentEvaluationWorkflowFixture(2);
    $firstBooklet = $fixture['booklets'][0];
    $secondBooklet = $fixture['booklets'][1];
    $assignmentUrl = "/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/booklets/{$firstBooklet->id}/zuordnung";
    $statusUrl = "/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/booklets/{$firstBooklet->id}/status";
    $secondAssignmentUrl = "/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/booklets/{$secondBooklet->id}/zuordnung";

    $this->actingAs($fixture['user'])
        ->put($assignmentUrl, ['student_id' => $fixture['student']->id])
        ->assertRedirect();

    $this->actingAs($fixture['user'])
        ->patch($statusUrl, ['status' => 'discarded'])
        ->assertRedirect();

    $this->actingAs($fixture['user'])
        ->put($secondAssignmentUrl, ['student_id' => $fixture['student']->id])
        ->assertRedirect();

    expect($firstBooklet->fresh()->status)->toBe('discarded')
        ->and($firstBooklet->fresh()->student_id)->toBe($fixture['student']->id)
        ->and($secondBooklet->fresh()->status)->toBe('open')
        ->and($secondBooklet->fresh()->student_id)->toBe($fixture['student']->id);
});

it('persists a complete review for every expectation occurrence', function () {
    $fixture = assessmentEvaluationWorkflowFixture();

    $this->actingAs($fixture['user'])
        ->put("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/booklets/{$fixture['booklets'][0]->id}/tasks/{$fixture['task']->id}/review", [
            'items' => [
                ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 1, 'awarded_points' => 2, 'note' => null],
                ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 2, 'awarded_points' => 1.5, 'note' => 'teilweise'],
                ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 3, 'awarded_points' => 0, 'note' => null],
            ],
            'extra_points' => -1,
            'extra_note' => 'Formfehler',
        ])
        ->assertRedirect();

    $review = AssessmentTaskReview::query()->sole();

    expect($review->extra_points)->toBe(-1)
        ->and($review->extra_note)->toBe('Formfehler')
        ->and($review->items()->orderBy('occurrence')->pluck('occurrence')->all())->toBe([1, 2, 3])
        ->and($review->items()->orderBy('occurrence')->pluck('awarded_points')->all())->toBe(['2.00', '1.50', '0.00']);
});

it('requires a review row for every expectation occurrence', function () {
    $fixture = assessmentEvaluationWorkflowFixture();

    $this->actingAs($fixture['user'])
        ->put("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/booklets/{$fixture['booklets'][0]->id}/tasks/{$fixture['task']->id}/review", [
            'items' => [
                ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 1, 'awarded_points' => 2],
                ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 2, 'awarded_points' => 2],
            ],
            'extra_points' => 0,
        ])
        ->assertSessionHasErrors('items');

    expect(AssessmentTaskReview::query()->count())->toBe(0);
});

it('saves signed extra points for a task without expectations', function () {
    $fixture = assessmentEvaluationWorkflowFixture();
    $taskWithoutExpectations = AssessmentTask::withoutEvents(fn (): AssessmentTask => AssessmentTask::create([
        'organization_id' => $fixture['organization']->id,
        'title' => 'Aufgabe ohne Erwartung',
    ]));
    $fixture['assessment']->tasks()->attach($taskWithoutExpectations, ['position' => 2]);
    AssessmentBookletFragment::create([
        'assessment_booklet_id' => $fixture['booklets'][0]->id,
        'assessment_task_id' => $taskWithoutExpectations->id,
        'image_path' => "assessment-booklets/{$fixture['assessment']->id}/{$fixture['booklets'][0]->id}/task-{$taskWithoutExpectations->id}.png",
        'page' => 1,
        'start_y_cm' => 10,
        'end_y_cm' => 16,
    ]);

    $this->actingAs($fixture['user'])
        ->put("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/booklets/{$fixture['booklets'][0]->id}/tasks/{$taskWithoutExpectations->id}/review", [
            'items' => [],
            'extra_points' => -2,
            'extra_note' => 'Zusätzlicher Abzug',
        ])
        ->assertRedirect();

    $review = AssessmentTaskReview::query()->sole();

    expect($review->assessment_task_id)->toBe($taskWithoutExpectations->id)
        ->and($review->extra_points)->toBe(-2)
        ->and($review->items)->toHaveCount(0);
});

it('rejects fractional extra points', function () {
    $fixture = assessmentEvaluationWorkflowFixture();

    $this->actingAs($fixture['user'])
        ->put("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/booklets/{$fixture['booklets'][0]->id}/tasks/{$fixture['task']->id}/review", [
            'items' => [
                ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 1, 'awarded_points' => 0],
                ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 2, 'awarded_points' => 0],
                ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 3, 'awarded_points' => 0],
            ],
            'extra_points' => -1.5,
        ])
        ->assertSessionHasErrors('extra_points');
});

it('deletes obsolete review occurrences when repetitions decrease', function () {
    $fixture = assessmentEvaluationWorkflowFixture();
    $reviewUrl = "/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/booklets/{$fixture['booklets'][0]->id}/tasks/{$fixture['task']->id}/review";

    $this->actingAs($fixture['user'])
        ->put($reviewUrl, [
            'items' => [
                ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 1, 'awarded_points' => 2],
                ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 2, 'awarded_points' => 2],
                ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 3, 'awarded_points' => 2],
            ],
            'extra_points' => 0,
        ])
        ->assertRedirect();

    $fixture['expectation']->update(['repetitions' => 1]);

    $this->actingAs($fixture['user'])
        ->put($reviewUrl, [
            'items' => [
                ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 1, 'awarded_points' => 1],
            ],
            'extra_points' => 0,
        ])
        ->assertRedirect();

    $review = AssessmentTaskReview::query()->sole();

    expect($review->items()->pluck('occurrence')->all())->toBe([1])
        ->and($review->items()->sole()->awarded_points)->toBe('1.00');
});

it('randomizes task fragments on each evaluation request', function () {
    $fixture = assessmentEvaluationWorkflowFixture(5);
    $url = "/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung";

    $orders = collect(range(1, 4))->map(fn (): array => collect(
        $this->actingAs($fixture['user'])->get($url)->inertiaProps('taskFragments'),
    )->pluck('id')->all());

    expect($orders->unique(fn (array $order): string => implode(',', $order))->count())->toBeGreaterThan(1);
});

it('streams a booklet fragment only through an authorized private route', function () {
    Storage::fake('documents');
    $fixture = assessmentEvaluationWorkflowFixture();
    $fragment = $fixture['booklets'][0]->fragments()->sole();
    Storage::disk('documents')->put($fragment->image_path, 'private image');

    $this->actingAs($fixture['user'])
        ->get("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/fragments/{$fragment->id}")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeaderContains('Cache-Control', 'private')
        ->assertHeaderContains('Cache-Control', 'no-store');
});

it('materializes PAGE markers from repeated PDF uploads as distinct booklets', function () {
    Storage::fake('temporary');
    Storage::fake('documents');
    $fixture = assessmentEvaluationWorkflowFixture();
    $this->app->bind(DataMatrixDecoder::class, fn () => new class($fixture['assessment']->id, $fixture['task']->id) implements DataMatrixDecoder
    {
        public function __construct(private readonly int $assessmentId, private readonly int $taskId) {}

        public function decode(string $imagePath): iterable
        {
            yield ['payload' => "ROO1|A={$this->assessmentId}|K=PAGE", 'x_px' => 20, 'y_px' => 100, 'width_px' => 10, 'height_px' => 10];
            yield ['payload' => "ROO1|T={$this->taskId}|K=START", 'x_px' => 20, 'y_px' => 500, 'width_px' => 10, 'height_px' => 10];
            yield ['payload' => "ROO1|T={$this->taskId}|K=END", 'x_px' => 20, 'y_px' => 1200, 'width_px' => 10, 'height_px' => 10];
        }
    });
    $sessionUrl = "/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/session";
    $firstSession = $this->actingAs($fixture['user'])->postJson($sessionUrl)->assertCreated()->json('session_id');
    $secondSession = $this->actingAs($fixture['user'])->postJson($sessionUrl)->assertCreated()->json('session_id');

    foreach ([1, 2] as $page) {
        $this->actingAs($fixture['user'])
            ->post("{$sessionUrl}/{$firstSession}/pages", [
                'image' => UploadedFile::fake()->image("first-pdf-page-{$page}.png", 2480, 3508),
                'page' => $page,
            ])
            ->assertCreated()
            ->assertJsonCount(3, 'markers');
    }
    $this->actingAs($fixture['user'])
        ->post("{$sessionUrl}/{$secondSession}/pages", [
            'image' => UploadedFile::fake()->image('second-pdf-page-1.png', 2480, 3508),
            'page' => 1,
        ])
        ->assertCreated()
        ->assertJsonCount(3, 'markers');

    $this->actingAs($fixture['user'])->postJson("{$sessionUrl}/{$firstSession}/complete")->assertOk();
    $this->actingAs($fixture['user'])->postJson("{$sessionUrl}/{$secondSession}/complete")->assertOk();

    $booklets = $fixture['assessment']->booklets()->with('fragments')->orderBy('number')->get();

    expect($booklets->pluck('number')->all())->toBe([1, 2, 3, 4])
        ->and($booklets->map(fn (AssessmentBooklet $booklet): int => $booklet->fragments->count())->all())->toBe([1, 1, 1, 1]);
});

it('keeps a booklet but omits its task fragment when a marker pair is incomplete', function () {
    Storage::fake('temporary');
    Storage::fake('documents');
    $fixture = assessmentEvaluationWorkflowFixture();
    $this->app->bind(DataMatrixDecoder::class, fn () => new class($fixture['assessment']->id, $fixture['task']->id) implements DataMatrixDecoder
    {
        public function __construct(private readonly int $assessmentId, private readonly int $taskId) {}

        public function decode(string $imagePath): iterable
        {
            yield ['payload' => "ROO1|A={$this->assessmentId}|K=PAGE", 'x_px' => 20, 'y_px' => 100, 'width_px' => 10, 'height_px' => 10];
            yield ['payload' => "ROO1|T={$this->taskId}|K=START", 'x_px' => 20, 'y_px' => 500, 'width_px' => 10, 'height_px' => 10];
        }
    });
    $sessionUrl = "/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/session";
    $session = $this->actingAs($fixture['user'])->postJson($sessionUrl)->assertCreated()->json('session_id');

    $this->actingAs($fixture['user'])
        ->post("{$sessionUrl}/{$session}/pages", [
            'image' => UploadedFile::fake()->image('incomplete-markers.png', 2480, 3508),
            'page' => 1,
        ])
        ->assertCreated()
        ->assertJsonCount(2, 'markers');
    $this->actingAs($fixture['user'])->postJson("{$sessionUrl}/{$session}/complete")->assertOk();

    $booklets = $fixture['assessment']->booklets()->with('fragments')->orderBy('number')->get();

    expect($booklets->pluck('number')->all())->toBe([1, 2])
        ->and($booklets[1]->fragments)->toHaveCount(0);
});

it('returns the original booklet when a completed scan materialization is retried', function () {
    Storage::fake('temporary');
    Storage::fake('documents');
    $fixture = assessmentEvaluationWorkflowFixture();
    $sessions = app(AssessmentScanSessionStore::class);
    $session = $sessions->create($fixture['assessment'])['session_id'];
    $sessions->storePage($session, 1, UploadedFile::fake()->image('retry-page.png', 2480, 3508));
    $sessions->storePageMarkers($session, 1, [
        ['kind' => 'PAGE', 'page' => 1, 'y_cm' => 1.0, 'y_px' => 118, 'assessment_id' => $fixture['assessment']->id],
    ]);
    $sessions->complete($session, ['booklets' => [], 'warnings' => []], []);
    $temporary = Storage::disk('temporary');
    $sessionFiles = collect($temporary->allFiles("assessment-scans/{$session}"))
        ->mapWithKeys(fn (string $path): array => [$path => $temporary->get($path)]);

    $first = app(MaterializeAssessmentScan::class)->handle($fixture['assessment'], $session);
    $sessionFiles->each(fn (string $contents, string $path) => $temporary->put($path, $contents));
    $retried = app(MaterializeAssessmentScan::class)->handle($fixture['assessment'], $session);

    expect($retried->pluck('id')->all())->toBe($first->pluck('id')->all())
        ->and($fixture['assessment']->booklets()->count())->toBe(2)
        ->and($sessions->manifest($session))->toBeNull();
});

it('decodes only the two-centimetre left margin while retaining the original y coordinate', function () {
    $sourcePath = tempnam(sys_get_temp_dir(), 'roo-dmtx-feature-');
    if ($sourcePath === false) {
        throw new RuntimeException('Temporäre Scan-Datei konnte nicht angelegt werden.');
    }
    $source = imagecreatetruecolor(2480, 3508);
    imagepng($source, $sourcePath);
    imagedestroy($source);
    $cropPath = null;
    $cropWidth = null;
    $cropHeight = null;

    try {
        $decoder = new DmtxReadDecoder(processRunner: function (array $arguments) use (&$cropPath, &$cropWidth, &$cropHeight): array {
            $cropPath = $arguments[array_key_last($arguments)];
            $crop = imagecreatefrompng($cropPath);
            $cropWidth = imagesx($crop);
            $cropHeight = imagesy($crop);
            imagedestroy($crop);

            return [
                'exit_code' => 1,
                'output' => "ROO1|A=7|K=PAGE\n",
                'position_output' => '50,1181.1:100,1181.1:100,1231.1:50,1231.1:',
            ];
        });

        $markers = iterator_to_array($decoder->decode($sourcePath));

        expect($cropWidth)->toBe(236)
            ->and($cropHeight)->toBe(3508)
            ->and($markers)->toMatchArray([
                ['payload' => 'ROO1|A=7|K=PAGE', 'x_px' => 50.0, 'y_px' => 1181.1, 'width_px' => 50.0, 'height_px' => 50.0],
            ])
            ->and(file_exists($cropPath))->toBeFalse();
    } finally {
        if (file_exists($sourcePath)) {
            unlink($sourcePath);
        }
    }
});
