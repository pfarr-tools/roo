<?php

namespace App\Documents;

final class AssessmentResultDocument extends LayoutDocument
{
    /** @param list<array<string, mixed>> $reports */
    public function __construct(
        string $title,
        public readonly array $reports,
        DocumentLayout $layout = DocumentLayout::PRIMARY_SCHOOL_LOWER_SECONDARY,
    ) {
        parent::__construct($title, layout: $layout);
    }

    public function templateKey(): string
    {
        return 'assessment-result.default';
    }
}
