<?php

use App\Models\AssessmentTask;
use App\Services\AssessmentEvaluation\CheckboxTaskEvaluator;
use Tests\TestCase;

uses(TestCase::class);

function checkboxTask(array $content = []): AssessmentTask
{
    return new AssessmentTask([
        'task_type' => 'checkbox',
        'content' => $content + [
            'points_per_correct_answer' => 2,
            'options' => [
                ['id' => 'a1', 'text' => 'Richtig', 'correct' => true],
                ['id' => 'a2', 'text' => 'Falsch', 'correct' => false],
                ['id' => 'a3', 'text' => 'Auch richtig', 'correct' => true],
            ],
        ],
    ]);
}

it('scores only selected correct checkbox options', function () {
    $evaluator = new CheckboxTaskEvaluator;
    expect($evaluator->score(checkboxTask(), [
        ['id' => 'a1', 'selected' => true],
        ['id' => 'a2', 'selected' => true],
        ['id' => 'a3', 'selected' => false],
    ]))->toBe(2.0);
});

it('scores every option with a correct current state when configured', function () {
    $task = checkboxTask(['checkbox_scoring_mode' => 'correct_states']);

    expect((new CheckboxTaskEvaluator)->score($task, [
        ['id' => 'a1', 'selected' => true],
        ['id' => 'a2', 'selected' => false],
        ['id' => 'a3', 'selected' => false],
    ]))->toBe(4.0);
});

it('rejects duplicate and unknown checkbox options', function () {
    $evaluator = new CheckboxTaskEvaluator;
    expect(fn () => $evaluator->validate(checkboxTask(), [
        ['id' => 'a1', 'selected' => true],
        ['id' => 'a1', 'selected' => false],
        ['id' => 'a2', 'selected' => false],
        ['id' => 'a3', 'selected' => false],
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn () => $evaluator->validate(checkboxTask(), [
        ['id' => 'a1', 'selected' => true],
        ['id' => 'a2', 'selected' => false],
        ['id' => 'missing', 'selected' => false],
    ]))->toThrow(InvalidArgumentException::class);
});

it('scores legacy checkbox tasks without an explicit points setting', function () {
    $task = new AssessmentTask([
        'task_type' => 'checkbox',
        'max_points' => 2,
        'content' => [
            'options' => [
                ['id' => 'a1', 'text' => 'Richtig', 'correct' => true],
                ['id' => 'a2', 'text' => 'Auch richtig', 'correct' => true],
            ],
        ],
    ]);

    expect((new CheckboxTaskEvaluator)->score($task, [
        ['id' => 'a1', 'selected' => true],
        ['id' => 'a2', 'selected' => false],
    ]))->toBe(1.0);
});

it('accepts generated ids for legacy checkbox options', function () {
    $task = new AssessmentTask([
        'task_type' => 'checkbox',
        'content' => [
            'options' => [['text' => 'Richtig', 'correct' => true]],
        ],
    ]);

    expect((new CheckboxTaskEvaluator)->score($task, [
        ['id' => 'option-1', 'selected' => true],
    ]))->toBe(1.0);
});

it('specializes migrated legacy checkbox tasks', function () {
    $task = checkboxTask(['evaluation_mode' => 'legacy_checkbox']);

    expect((new CheckboxTaskEvaluator)->supports($task))->toBeTrue();
});
