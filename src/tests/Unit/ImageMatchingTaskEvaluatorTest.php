<?php

use App\Models\AssessmentTask;
use App\Models\AssessmentTaskImage;
use App\Services\AssessmentEvaluation\ImageMatchingTaskEvaluator;

function imageMatchingTask(array $content = []): AssessmentTask
{
    $task = new AssessmentTask([
        'task_type' => 'image_matching',
        'content' => $content + ['points_per_correct_answer' => 2],
    ]);
    $task->setRelation('images', collect([
        new AssessmentTaskImage(['identifier' => 'pair-1', 'label' => 'Löwe', 'answer' => 'Mut']),
        new AssessmentTaskImage(['identifier' => 'pair-2', 'label' => 'Taube', 'answer' => 'Frieden']),
    ]));

    return $task;
}

it('scores checked image-text matches with the configured points', function () {
    expect((new ImageMatchingTaskEvaluator)->score(imageMatchingTask(), [
        ['id' => 'pair-1', 'selected' => true],
        ['id' => 'pair-2', 'selected' => false],
    ]))->toBe(2.0);
});

it('rejects missing, duplicate, or unknown image matches', function () {
    $evaluator = new ImageMatchingTaskEvaluator;

    expect(fn () => $evaluator->validate(imageMatchingTask(), [
        ['id' => 'pair-1', 'selected' => true],
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn () => $evaluator->validate(imageMatchingTask(), [
        ['id' => 'pair-1', 'selected' => true],
        ['id' => 'pair-1', 'selected' => false],
    ]))->toThrow(InvalidArgumentException::class);
});

it('uses one configured image width for the task', function () {
    $task = imageMatchingTask(['image_width_cm' => 3.4]);

    expect($task->imageWidthCm())->toBe(3.4);
});
