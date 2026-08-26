<?php

namespace App\Documents;

use PfarrTools\RooRuling\RulingDefinition;
use PfarrTools\RooRuling\RulingPreset;

final class AssessmentDocument extends Document
{
    /** @param list<array<string, mixed>> $tasks */
    public function __construct(
        string $title,
        public readonly array $tasks,
        public readonly string $gradeLevel = '',
        array $metadata = [],
    ) {
        parent::__construct($title, $metadata);
    }

    public function templateKey(): string
    {
        return 'assessment.primary-school-lower-secondary';
    }

    /** @return array{rulings: list<RulingDefinition>, counts: list<int>} */
    public function odtRulings(): array
    {
        $rulings = [];
        $counts = [];

        foreach ($this->tasks as $task) {
            if (in_array($task['task_type'] ?? '', ['checkbox', 'image_labeling', 'heading_table', 'matching_table'], true)) {
                continue;
            }

            $content = is_array($task['content'] ?? null) ? $task['content'] : [];
            $preset = ! empty($content['lineated']) ? $this->rulingForGrade() : RulingPreset::Grade4Plus;
            $definition = $preset->definition();
            $rulings[] = new RulingDefinition(
                zonesMm: $definition->zonesMm,
                gapMm: $definition->gapMm,
                leftBorder: $definition->leftBorder,
                rightBorder: $definition->rightBorder,
                topBorder: $definition->topBorder,
                lineColor: '000000',
                lineSize: 8,
                textZoneIndex: $definition->textZoneIndex,
                lineIndexes: $definition->lineIndexes,
                sideBorderZoneIndexes: $definition->sideBorderZoneIndexes,
            );
            $counts[] = max(1, (int) ($content['lines'] ?? 5));
        }

        return ['rulings' => $rulings, 'counts' => $counts];
    }

    private function rulingForGrade(): RulingPreset
    {
        preg_match_all('/\d+/', $this->gradeLevel, $matches);
        $grade = $matches[0] === [] ? 4 : min(array_map('intval', $matches[0]));

        return match (true) {
            $grade <= 1 => RulingPreset::Grade1,
            $grade === 2 => RulingPreset::Grade2,
            $grade === 3 => RulingPreset::Grade3,
            default => RulingPreset::Grade4Plus,
        };
    }
}
