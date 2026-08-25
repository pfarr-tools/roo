<?php

namespace App\Services\AssessmentEvaluation;

use App\Models\AssessmentTask;

final class AssessmentTaskEvaluatorRegistry
{
    public function __construct(
        private readonly CheckboxTaskEvaluator $checkbox,
        private readonly ImageMatchingTaskEvaluator $imageMatching,
        private readonly ImageLabelingTaskEvaluator $imageLabeling,
    ) {}

    public function for(AssessmentTask $task): CheckboxTaskEvaluator|ImageMatchingTaskEvaluator|ImageLabelingTaskEvaluator|null
    {
        if ($this->checkbox->supports($task)) {
            return $this->checkbox;
        }

        if ($this->imageLabeling->supports($task)) {
            return $this->imageLabeling;
        }

        return $this->imageMatching->supports($task) ? $this->imageMatching : null;
    }
}
