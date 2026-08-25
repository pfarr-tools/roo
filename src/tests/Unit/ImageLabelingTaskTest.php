<?php

use App\Models\AssessmentTask;
use App\Models\AssessmentTaskImage;
use App\Models\AssessmentTaskImageLabel;

it('orders image labels and keeps their normalized coordinates', function () {
    $task = new AssessmentTask([
        'task_type' => 'image_labeling',
        'content' => [
            'image_label_width_cm' => 6.5,
            'points_per_correct_answer' => 2,
            'show_solutions' => true,
        ],
    ]);
    $image = new AssessmentTaskImage;
    $image->setRelation('labels', collect([
        new AssessmentTaskImageLabel(['position' => 1, 'x_percent' => 70.25, 'y_percent' => 20.5, 'solution' => 'Zweig', 'lines' => 3]),
        new AssessmentTaskImageLabel(['position' => 0, 'x_percent' => 12.5, 'y_percent' => 80.75, 'solution' => 'Stamm']),
    ]));
    $task->setRelation('images', collect([$image]));
    $task->setRelation('expectations', collect());

    expect($task->imageLabelWidthCm())->toBe(6.5)
        ->and($task->maximumPoints())->toBe(4)
        ->and($image->labels->sortBy('position')->pluck('solution')->all())->toBe(['Stamm', 'Zweig'])
        ->and($image->labels->first()->lines)->toBe(3)
        ->and($image->labels->last()->lines)->toBe(1)
        ->and((float) $image->labels->first()->x_percent)->toBe(70.25);
});
