<?php

namespace App\Documents;

final class AssessmentResultDocument extends Document
{
    /** @param list<array<string, mixed>> $reports */
    public function __construct(
        string $title,
        public readonly array $reports,
    ) {
        parent::__construct($title);
    }

    public function templateKey(): string
    {
        return 'assessment-result.default';
    }
}
