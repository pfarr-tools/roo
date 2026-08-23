<?php

use App\Models\AssessmentTask;
use App\Models\AssessmentTaskExpectation;
use App\Models\Organization;
use App\Services\AssessmentEvaluation\ExpectationOccurrences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('expands every expectation repetition into an independently scoreable occurrence', function () {
    $organization = Organization::create(['name' => 'Erwartungsausprägungen']);
    $task = AssessmentTask::withoutEvents(fn (): AssessmentTask => AssessmentTask::create([
        'organization_id' => $organization->id,
        'title' => 'Schöpfung beschreiben',
    ]));
    $firstExpectation = AssessmentTaskExpectation::create([
        'assessment_task_id' => $task->id,
        'text' => 'Nenne Beispiele.',
        'points' => 2,
        'repetitions' => 3,
        'position' => 1,
    ]);
    $secondExpectation = AssessmentTaskExpectation::create([
        'assessment_task_id' => $task->id,
        'text' => 'Begründe deine Auswahl.',
        'points' => 1.5,
        'repetitions' => 1,
        'position' => 2,
    ]);

    expect(ExpectationOccurrences::forTask($task)->all())->toBe([
        ['expectation_id' => $firstExpectation->id, 'occurrence' => 1, 'text' => 'Nenne Beispiele.', 'points' => '2.00'],
        ['expectation_id' => $firstExpectation->id, 'occurrence' => 2, 'text' => 'Nenne Beispiele.', 'points' => '2.00'],
        ['expectation_id' => $firstExpectation->id, 'occurrence' => 3, 'text' => 'Nenne Beispiele.', 'points' => '2.00'],
        ['expectation_id' => $secondExpectation->id, 'occurrence' => 1, 'text' => 'Begründe deine Auswahl.', 'points' => '1.50'],
    ]);
});
