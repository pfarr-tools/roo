<?php

namespace App\Services\AssessmentEvaluation;

use App\Models\AssessmentTask;

final class AssessmentTaskEvaluatorRegistry
{
    public function __construct(private readonly CheckboxTaskEvaluator $checkbox) {}

    public function for(AssessmentTask $task): ?CheckboxTaskEvaluator
    {
        return $this->checkbox->supports($task) ? $this->checkbox : null;
    }
}
