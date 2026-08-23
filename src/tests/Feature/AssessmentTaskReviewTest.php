<?php

use App\Models\Assessment;
use App\Models\AssessmentBooklet;
use App\Models\AssessmentBookletFragment;
use App\Models\AssessmentTask;
use App\Models\AssessmentTaskExpectation;
use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentAssessmentResult;
use App\Models\TeachingGroup;
use App\Models\User;
use App\Services\AssessmentScan\AssessmentScanSessionStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function assessmentTaskReviewFixture(): array
{
    $organization = Organization::create(['name' => 'Aufgabenbewertung Organisation']);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    $school = School::create(['organization_id' => $organization->id, 'name' => 'Aufgabenbewertung Schule']);
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
    $replacementStudent = Student::create([
        'organization_id' => $organization->id,
        'school_id' => $school->id,
        'first_name' => 'Noah',
        'last_name' => 'Nachname',
        'class_name' => '4a',
    ]);
    $group->students()->attach([$student->id, $replacementStudent->id]);
    $booklet = AssessmentBooklet::create([
        'assessment_id' => $assessment->id,
        'number' => 1,
        'status' => 'open',
        'student_id' => $student->id,
    ]);
    AssessmentBookletFragment::create([
        'assessment_booklet_id' => $booklet->id,
        'assessment_task_id' => $task->id,
        'image_path' => "assessment-booklets/{$assessment->id}/{$booklet->id}/task.png",
        'page' => 1,
        'start_y_cm' => 4,
        'end_y_cm' => 10,
    ]);

    return compact('user', 'organization', 'group', 'assessment', 'task', 'expectation', 'student', 'replacementStudent', 'booklet');
}

function taskReviewUrl(array $fixture, ?AssessmentTask $task = null): string
{
    $task ??= $fixture['task'];

    return "/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/booklets/{$fixture['booklet']->id}/tasks/{$task->id}/review";
}

it('synchronizes full partial and zero occurrence scores with notes and signed extra points', function () {
    $fixture = assessmentTaskReviewFixture();

    $this->actingAs($fixture['user'])->put(taskReviewUrl($fixture), [
        'items' => [
            ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 1, 'awarded_points' => 2, 'note' => null],
            ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 2, 'awarded_points' => 1.5, 'note' => 'teilweise erfüllt'],
            ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 3, 'awarded_points' => 0, 'note' => 'fehlt'],
        ],
        'extra_points' => -1.25,
        'extra_note' => 'Formfehler',
    ])->assertRedirect();

    $result = StudentAssessmentResult::query()->sole();
    $review = $fixture['booklet']->reviews()->with('items')->sole();

    expect($result->student_id)->toBe($fixture['student']->id)
        ->and($result->assessment_task_id)->toBe($fixture['task']->id)
        ->and($result->points)->toBe('2.25')
        ->and($review->items->pluck('note')->all())->toBe([null, 'teilweise erfüllt', 'fehlt'])
        ->and($review->extra_note)->toBe('Formfehler');
});

it('synchronizes a no-expectation task from signed extra points alone', function () {
    $fixture = assessmentTaskReviewFixture();
    $task = AssessmentTask::withoutEvents(fn (): AssessmentTask => AssessmentTask::create([
        'organization_id' => $fixture['organization']->id,
        'title' => 'Zusatzaufgabe',
    ]));
    $fixture['assessment']->tasks()->attach($task, ['position' => 2]);
    AssessmentBookletFragment::create([
        'assessment_booklet_id' => $fixture['booklet']->id,
        'assessment_task_id' => $task->id,
        'image_path' => "assessment-booklets/{$fixture['assessment']->id}/{$fixture['booklet']->id}/task-{$task->id}.png",
        'page' => 1,
        'start_y_cm' => 10,
        'end_y_cm' => 16,
    ]);

    $this->actingAs($fixture['user'])->put(taskReviewUrl($fixture, $task), [
        'items' => [],
        'extra_points' => -2.5,
        'extra_note' => 'Zusätzlicher Abzug',
    ])->assertRedirect();

    expect(StudentAssessmentResult::query()->sole()->points)->toBe('-2.50');
});

it('moves and removes synchronized results when a booklet is reassigned or discarded', function () {
    $fixture = assessmentTaskReviewFixture();
    $reviewPayload = [
        'items' => [
            ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 1, 'awarded_points' => 2],
            ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 2, 'awarded_points' => 2],
            ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 3, 'awarded_points' => 2],
        ],
        'extra_points' => 0,
    ];
    $assignmentUrl = "/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/booklets/{$fixture['booklet']->id}/zuordnung";
    $statusUrl = "/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswertung/booklets/{$fixture['booklet']->id}/status";

    $this->actingAs($fixture['user'])->put(taskReviewUrl($fixture), $reviewPayload)->assertRedirect();
    expect(StudentAssessmentResult::query()->where('student_id', $fixture['student']->id)->value('points'))->toBe('6.00');

    $this->actingAs($fixture['user'])->put($assignmentUrl, ['student_id' => $fixture['replacementStudent']->id])->assertRedirect();
    expect(StudentAssessmentResult::query()->where('student_id', $fixture['student']->id)->exists())->toBeFalse()
        ->and(StudentAssessmentResult::query()->where('student_id', $fixture['replacementStudent']->id)->value('points'))->toBe('6.00');

    $this->actingAs($fixture['user'])->patch($statusUrl, ['status' => 'discarded'])->assertRedirect();
    expect(StudentAssessmentResult::query()->count())->toBe(0);

    $this->actingAs($fixture['user'])->patch($statusUrl, ['status' => 'open'])->assertRedirect();
    expect(StudentAssessmentResult::query()->sole()->student_id)->toBe($fixture['replacementStudent']->id)
        ->and(StudentAssessmentResult::query()->sole()->points)->toBe('6.00');

    $this->actingAs($fixture['user'])->put($assignmentUrl, ['student_id' => null])->assertRedirect();
    expect(StudentAssessmentResult::query()->count())->toBe(0);
});

it('recalculates a result after obsolete expectation occurrences are removed', function () {
    $fixture = assessmentTaskReviewFixture();
    $initialPayload = [
        'items' => [
            ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 1, 'awarded_points' => 2],
            ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 2, 'awarded_points' => 2],
            ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 3, 'awarded_points' => 2],
        ],
        'extra_points' => 0,
    ];

    $this->actingAs($fixture['user'])->put(taskReviewUrl($fixture), $initialPayload)->assertRedirect();
    $fixture['expectation']->update(['repetitions' => 1]);

    $this->actingAs($fixture['user'])->put(taskReviewUrl($fixture), [
        'items' => [
            ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 1, 'awarded_points' => 1],
        ],
        'extra_points' => 0,
    ])->assertRedirect();

    expect($fixture['booklet']->reviews()->sole()->items()->count())->toBe(1)
        ->and(StudentAssessmentResult::query()->sole()->points)->toBe('1.00');
});

it('rejects negative awarded points for an expectation occurrence', function () {
    $fixture = assessmentTaskReviewFixture();

    $this->actingAs($fixture['user'])->put(taskReviewUrl($fixture), [
        'items' => [
            ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 1, 'awarded_points' => -0.01],
            ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 2, 'awarded_points' => 2],
            ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 3, 'awarded_points' => 2],
        ],
        'extra_points' => -1,
    ])->assertSessionHasErrors('items.0.awarded_points');
});

it('rejects awarded points above an expectation occurrence maximum', function () {
    $fixture = assessmentTaskReviewFixture();

    $this->actingAs($fixture['user'])->put(taskReviewUrl($fixture), [
        'items' => [
            ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 1, 'awarded_points' => 2.01],
            ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 2, 'awarded_points' => 2],
            ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 3, 'awarded_points' => 2],
        ],
        'extra_points' => -1,
    ])->assertSessionHasErrors('items.0.awarded_points');
});

it('keeps synchronized results separate when two assessments reuse the same task', function () {
    $fixture = assessmentTaskReviewFixture();
    $secondAssessment = Assessment::create([
        'organization_id' => $fixture['organization']->id,
        'teaching_group_id' => $fixture['group']->id,
        'title' => 'Zweite Lernstandserhebung',
    ]);
    $secondAssessment->tasks()->attach($fixture['task'], ['position' => 1]);
    $secondBooklet = AssessmentBooklet::create([
        'assessment_id' => $secondAssessment->id,
        'number' => 1,
        'status' => 'open',
        'student_id' => $fixture['student']->id,
    ]);
    AssessmentBookletFragment::create([
        'assessment_booklet_id' => $secondBooklet->id,
        'assessment_task_id' => $fixture['task']->id,
        'image_path' => "assessment-booklets/{$secondAssessment->id}/{$secondBooklet->id}/task.png",
        'page' => 1,
        'start_y_cm' => 4,
        'end_y_cm' => 10,
    ]);
    $firstPayload = [
        'items' => [
            ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 1, 'awarded_points' => 2],
            ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 2, 'awarded_points' => 2],
            ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 3, 'awarded_points' => 2],
        ],
        'extra_points' => 0,
    ];
    $secondPayload = [...$firstPayload, 'extra_points' => -1];

    $this->actingAs($fixture['user'])->put(taskReviewUrl($fixture), $firstPayload)->assertRedirect();
    $this->actingAs($fixture['user'])->put("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$secondAssessment->id}/auswertung/booklets/{$secondBooklet->id}/tasks/{$fixture['task']->id}/review", $secondPayload)->assertRedirect();

    expect(StudentAssessmentResult::query()->where('student_id', $fixture['student']->id)->orderBy('assessment_id')->get()->map(fn (StudentAssessmentResult $result): array => [
        'assessment_id' => $result->assessment_id,
        'points' => $result->points,
    ])->all())->toBe([
        ['assessment_id' => $fixture['assessment']->id, 'points' => '6.00'],
        ['assessment_id' => $secondAssessment->id, 'points' => '5.00'],
    ]);
});

it('rejects a task review when the booklet has no corresponding task fragment', function () {
    $fixture = assessmentTaskReviewFixture();
    $fixture['booklet']->fragments()->delete();

    $this->actingAs($fixture['user'])->put(taskReviewUrl($fixture), [
        'items' => [
            ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 1, 'awarded_points' => 2],
            ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 2, 'awarded_points' => 2],
            ['expectation_id' => $fixture['expectation']->id, 'occurrence' => 3, 'awarded_points' => 2],
        ],
        'extra_points' => 0,
    ])->assertNotFound();

    expect($fixture['booklet']->reviews()->count())->toBe(0);
});

it('deletes a booklet name and task crop when the booklet is deleted', function () {
    Storage::fake('documents');
    $fixture = assessmentTaskReviewFixture();
    $booklet = $fixture['booklet'];
    $fragment = $booklet->fragments()->sole();
    $namePath = "assessment-booklets/{$fixture['assessment']->id}/{$booklet->id}/name.png";
    Storage::disk('documents')->put($namePath, 'name crop');
    Storage::disk('documents')->put($fragment->image_path, 'task crop');
    $booklet->update(['name_fragment_path' => $namePath]);

    $booklet->delete();

    Storage::disk('documents')->assertMissing($namePath);
    Storage::disk('documents')->assertMissing($fragment->image_path);
});

it('deletes all durable booklet crops when an assessment is deleted', function () {
    Storage::fake('documents');
    $fixture = assessmentTaskReviewFixture();
    $booklet = $fixture['booklet'];
    $fragment = $booklet->fragments()->sole();
    $namePath = "assessment-booklets/{$fixture['assessment']->id}/{$booklet->id}/name.png";
    Storage::disk('documents')->put($namePath, 'name crop');
    Storage::disk('documents')->put($fragment->image_path, 'task crop');
    $booklet->update(['name_fragment_path' => $namePath]);

    $fixture['assessment']->delete();

    Storage::disk('documents')->assertMissing($namePath);
    Storage::disk('documents')->assertMissing($fragment->image_path);
});

it('prunes expired temporary scan sessions through the scheduled command', function () {
    Storage::fake('temporary');
    $fixture = assessmentTaskReviewFixture();
    $sessions = app(AssessmentScanSessionStore::class);
    $session = $sessions->create($fixture['assessment'])['session_id'];
    $sessions->storePage($session, 1, UploadedFile::fake()->image('page.png', 2480, 3508));

    $this->travel(61)->minutes();
    $this->artisan('assessments:prune-scan-sessions')
        ->expectsOutput('1 abgelaufene Scan-Session wurde gelöscht.')
        ->assertSuccessful();

    expect($sessions->manifest($session))->toBeNull();
    $this->travelBack();
});
