<?php

namespace App\Services\AssessmentEvaluation;

use App\Models\AssessmentTask;

final class AssessmentTaskEvaluatorRegistry
{
    public function __construct(
        private readonly CheckboxTaskEvaluator $checkbox,
        private readonly ImageMatchingTaskEvaluator $imageMatching,
    ) {}

    public function for(AssessmentTask $task): CheckboxTaskEvaluator|ImageMatchingTaskEvaluator|null
    {
        if ($this->checkbox->supports($task)) {
            return $this->checkbox;
        }

        return $this->imageMatching->supports($task) ? $this->imageMatching : null;
    }
}
