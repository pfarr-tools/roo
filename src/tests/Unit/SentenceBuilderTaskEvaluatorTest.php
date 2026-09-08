<?php

use App\Models\AssessmentTask;
use App\Services\AssessmentEvaluation\SentenceBuilderTaskEvaluator;
use Tests\TestCase;

uses(TestCase::class);

function sentenceBuilderTask(array $content = [], ?string $solution = 'Die Katze schläft'): AssessmentTask
{
    return new AssessmentTask([
        'task_type' => 'sentence_builder',
        'solution' => $solution,
        'max_points' => 6,
        'content' => array_merge([
            'words' => 'Die, Katze, schläft',
            'shuffled_words' => ['schläft', 'Die', 'Katze'],
        ], $content),
    ]);
}

it('scores the extracted words by pairwise relationships', function () {
    $evaluator = app(SentenceBuilderTaskEvaluator::class);

    expect($evaluator->percentage(sentenceBuilderTask(), 'die katze schläft.'))->toBe(100.0)
        ->and($evaluator->score(sentenceBuilderTask(), 'Schläft die Katze?'))->toBe(2.0)
        ->and(round($evaluator->percentage(sentenceBuilderTask(), 'Schläft die Katze?'), 4))->toBe(33.3333);
});

it('ignores words that are not in the word list', function () {
    $evaluator = app(SentenceBuilderTaskEvaluator::class);

    expect($evaluator->score(sentenceBuilderTask(), 'Die kleine Katze schläft heute.'))->toBe(6.0);
});

it('handles sentences with fewer than two words', function () {
    $task = sentenceBuilderTask(['words' => 'Hallo'], 'Hallo');

    expect(app(SentenceBuilderTaskEvaluator::class)->score($task, 'Hallo!'))->toBe(0.0)
        ->and(app(SentenceBuilderTaskEvaluator::class)->percentage($task, 'Hallo!'))->toBe(0.0);
});
