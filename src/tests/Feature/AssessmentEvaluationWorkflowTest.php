<?php

use App\Models\Assessment;
use App\Models\AssessmentBooklet;
use App\Models\AssessmentBookletFragment;
use App\Models\AssessmentTask;
use App\Models\AssessmentTaskExpectation;
use App\Models\AssessmentTaskReview;
use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\TeachingGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

it('persists a complete review for every expectation occurrence', function () {
    $fixture = assessmentEvaluationWorkflowFixture();

    $this->actingAs($fixture['user'])
        ->put("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/booklets/{$fixture['booklets'][0]->id}/tasks/{$fixture['task']->id}/review", [
            'items' => [
                ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 1, 'awarded_points' => 2, 'note' => null],
                ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 2, 'awarded_points' => 1.5, 'note' => 'teilweise'],
                ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 3, 'awarded_points' => 0, 'note' => null],
            ],
            'extra_points' => -1.25,
            'extra_note' => 'Formfehler',
        ])
        ->assertRedirect();

    $review = AssessmentTaskReview::query()->sole();

    expect($review->extra_points)->toBe('-1.25')
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

    $this->actingAs($fixture['user'])
        ->put("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/booklets/{$fixture['booklets'][0]->id}/tasks/{$taskWithoutExpectations->id}/review", [
            'items' => [],
            'extra_points' => -2.5,
            'extra_note' => 'Zusätzlicher Abzug',
        ])
        ->assertRedirect();

    $review = AssessmentTaskReview::query()->sole();

    expect($review->assessment_task_id)->toBe($taskWithoutExpectations->id)
        ->and($review->extra_points)->toBe('-2.50')
        ->and($review->items)->toHaveCount(0);
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
