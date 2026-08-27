<?php

use App\Models\AssessmentTask;
use App\Services\AssessmentEvaluation\SortingTaskEvaluator;
use App\Services\AssessmentEvaluation\SortingTaskOrder;

function sortingTask(array $content = []): AssessmentTask
{
    return new AssessmentTask([
        'task_type' => 'sorting',
        'content' => array_merge([
            'points_per_sentence' => 2,
            'questions' => [
                ['id' => 'a', 'label' => 'A'],
                ['id' => 'b', 'label' => 'B'],
                ['id' => 'c', 'label' => 'C'],
                ['id' => 'd', 'label' => 'D'],
                ['id' => 'e', 'label' => 'E'],
            ],
        ], $content),
    ]);
}

it('scores sorting by pairwise relationships instead of exact positions', function () {
    $task = sortingTask();

    expect(app(SortingTaskEvaluator::class)->score($task, [
        'a' => 1,
        'b' => 2,
        'c' => 4,
        'd' => 3,
        'e' => 5,
    ]))->toBe(9.0)
        ->and(app(SortingTaskEvaluator::class)->percentage($task, [
            'a' => 1,
            'b' => 2,
            'c' => 4,
            'd' => 3,
            'e' => 5,
        ]))->toBe(90.0);
});

it('returns zero without dividing by zero for fewer than two sentences', function () {
    $task = sortingTask(['questions' => [['id' => 'a', 'label' => 'A']]]);

    expect(app(SortingTaskEvaluator::class)->score($task, ['a' => 1]))->toBe(0.0)
        ->and(app(SortingTaskEvaluator::class)->percentage($task, ['a' => 1]))->toBe(0.0);
});

it('scores only valid pair relationships when answers are incomplete or invalid', function () {
    $evaluator = app(SortingTaskEvaluator::class);

    expect($evaluator->percentage(sortingTask(), ['a' => 1, 'b' => 2, 'd' => 4, 'e' => 5]))->toBe(60.0)
        ->and($evaluator->percentage(sortingTask(), ['a' => 1, 'b' => 1, 'c' => 3, 'd' => 4, 'e' => 5]))->toBe(90.0)
        ->and($evaluator->percentage(sortingTask(), ['a' => 0, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5]))->toBe(60.0);
});

it('rejects unknown sorting sentence identifiers', function () {
    $evaluator = app(SortingTaskEvaluator::class);

    expect(fn () => $evaluator->validate(sortingTask(), ['unknown' => 1]))
        ->toThrow(InvalidArgumentException::class);
});

it('preserves the persisted print order when a sorting task is edited', function () {
    $content = app(SortingTaskOrder::class)->apply(sortingTask()->content, [
        'sorting_order' => ['d', 'b', 'a', 'e', 'c'],
    ]);

    expect($content['sorting_order'])->toBe(['d', 'b', 'a', 'e', 'c']);
    expect(app(SortingTaskEvaluator::class)->displayQuestions(new AssessmentTask([
        'task_type' => 'sorting',
        'content' => $content,
    ]))->pluck('id')->all())->toBe(['d', 'b', 'a', 'e', 'c']);

    expect(app(SortingTaskEvaluator::class)->score(new AssessmentTask([
        'task_type' => 'sorting',
        'content' => $content,
    ]), ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5]))->toBe(10.0);
});
