<?php

namespace App\Services\AssessmentEvaluation;

use App\Models\AssessmentTask;

final class AssessmentTaskEvaluatorRegistry
{
    public function __construct(
        private readonly CheckboxTaskEvaluator $checkbox,
        private readonly ImageMatchingTaskEvaluator $imageMatching,
        private readonly ImageLabelingTaskEvaluator $imageLabeling,
        private readonly MatchingTableTaskEvaluator $matchingTable,
        private readonly SortingTaskEvaluator $sorting,
        private readonly SentenceBuilderTaskEvaluator $sentenceBuilder,
    ) {}

    public function for(AssessmentTask $task): CheckboxTaskEvaluator|ImageMatchingTaskEvaluator|ImageLabelingTaskEvaluator|MatchingTableTaskEvaluator|SortingTaskEvaluator|SentenceBuilderTaskEvaluator|null
    {
        if ($this->checkbox->supports($task)) {
            return $this->checkbox;
        }

        if ($this->imageLabeling->supports($task)) {
            return $this->imageLabeling;
        }

        if ($this->matchingTable->supports($task)) {
            return $this->matchingTable;
        }

        if ($this->sorting->supports($task)) {
            return $this->sorting;
        }

        if ($this->sentenceBuilder->supports($task)) {
            return $this->sentenceBuilder;
        }

        return $this->imageMatching->supports($task) ? $this->imageMatching : null;
    }
}
