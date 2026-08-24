<?php

use App\Models\AssessmentTask;
use App\Services\AssessmentEvaluation\CheckboxTaskEvaluator;

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
    $evaluator = new CheckboxTaskEvaluator();
    expect($evaluator->score(checkboxTask(), [
        ['id' => 'a1', 'selected' => true],
        ['id' => 'a2', 'selected' => true],
        ['id' => 'a3', 'selected' => false],
    ]))->toBe(2.0);
});

it('rejects duplicate and unknown checkbox options', function () {
    $evaluator = new CheckboxTaskEvaluator();
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

it('does not specialize legacy checkbox tasks', function () {
    $task = checkboxTask(['evaluation_mode' => 'legacy_checkbox']);

    expect((new CheckboxTaskEvaluator())->supports($task))->toBeFalse();
});
