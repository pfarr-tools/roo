<?php

use App\Models\Assessment;
use App\Models\AssessmentBooklet;
use App\Models\AssessmentBookletFragment;
use App\Models\AssessmentTask;
use App\Models\AssessmentTaskExpectation;
use App\Models\AssessmentTaskReview;
use App\Models\AssessmentTaskReviewItem;
use App\Models\AssessmentTaskReviewOption;
use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentAssessmentResult;
use App\Models\TeachingGroup;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function assessmentEvaluationDataFixture(): array
{
    $organization = Organization::create(['name' => 'Auswertungsorganisation']);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Auswertungsschule']);
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
    $student = Student::create([
        'organization_id' => $organization->id,
        'school_id' => $school->id,
        'first_name' => 'Mara',
        'last_name' => 'Muster',
        'class_name' => '4a',
    ]);
    $task = AssessmentTask::withoutEvents(fn (): AssessmentTask => AssessmentTask::create([
        'organization_id' => $organization->id,
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

    return compact('assessment', 'student', 'task', 'expectation');
}

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
