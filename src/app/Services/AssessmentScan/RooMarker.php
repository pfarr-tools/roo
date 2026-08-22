<?php

namespace App\Services\AssessmentScan;

final readonly class RooMarker
{
    public function __construct(
        public string $kind,
        public int $page,
        public float $yCm,
        public ?string $assessmentId = null,
        public ?string $taskId = null,
        public ?string $level = null,
        public string $payload = '',
    ) {}

    /** @return array<string, int|float|string|null> */
    public function toArray(): array
    {
        return [
            'page' => $this->page,
            'y_cm' => $this->yCm,
            'kind' => $this->kind,
            'assessment_id' => $this->assessmentId,
            'task_id' => $this->taskId,
            'level' => $this->level,
            'payload' => $this->payload,
        ];
    }
}
