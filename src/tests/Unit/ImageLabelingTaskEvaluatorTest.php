<?php

use App\Models\AssessmentTask;
use App\Models\AssessmentTaskImage;
use App\Models\AssessmentTaskImageLabel;
use App\Services\AssessmentEvaluation\ImageLabelingTaskEvaluator;

function imageLabelingTaskForEvaluation(): AssessmentTask
{
    $task = new AssessmentTask(['task_type' => 'image_labeling', 'content' => ['points_per_correct_answer' => 1.5]]);
    $image = new AssessmentTaskImage;
    $image->setRelation('labels', collect([
        (new AssessmentTaskImageLabel(['solution' => 'Stamm']))->setAttribute('id', 11),
        (new AssessmentTaskImageLabel(['solution' => 'Zweig']))->setAttribute('id', 12),
    ]));
    $task->setRelation('images', collect([$image]));

    return $task;
}

it('scores one checkbox per saved image label', function () {
    expect((new ImageLabelingTaskEvaluator)->score(imageLabelingTaskForEvaluation(), [
        ['id' => '11', 'selected' => true],
        ['id' => '12', 'selected' => false],
    ]))->toBe(1.5);
});

it('rejects incomplete image-label evaluations', function () {
    expect(fn () => (new ImageLabelingTaskEvaluator)->validate(imageLabelingTaskForEvaluation(), [
        ['id' => '11', 'selected' => true],
    ]))->toThrow(InvalidArgumentException::class);
});
