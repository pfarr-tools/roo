<?php

use App\Models\AssessmentTask;
use App\Services\AssessmentEvaluation\MatchingTableTaskEvaluator;

it('scores each correctly selected category in a matching table', function () {
    $task = new AssessmentTask([
        'task_type' => 'matching_table',
        'content' => [
            'points_per_correct_answer' => 2,
            'matching_scoring_mode' => 'per_category',
            'categories' => [['id' => 'c1', 'text' => 'Ja'], ['id' => 'c2', 'text' => 'Nein']],
            'rows' => [['id' => 'r1', 'text' => 'Aussage', 'category_ids' => ['c1', 'c2']]],
        ],
    ]);

    expect(app(MatchingTableTaskEvaluator::class)->score($task, [
        ['id' => 'r1:c1', 'selected' => true],
        ['id' => 'r1:c2', 'selected' => false],
    ]))->toBe(2.0);
});

it('scores a complete matching table row only when the row option is selected', function () {
    $task = new AssessmentTask([
        'task_type' => 'matching_table',
        'content' => [
            'points_per_correct_answer' => 3,
            'matching_scoring_mode' => 'complete_row',
            'categories' => [['id' => 'c1', 'text' => 'Ja']],
            'rows' => [['id' => 'r1', 'text' => 'Aussage', 'category_ids' => ['c1']]],
        ],
    ]);

    expect(app(MatchingTableTaskEvaluator::class)->score($task, [
        ['id' => 'r1', 'selected' => true],
    ]))->toBe(3.0);
});
