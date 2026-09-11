<?php

namespace App\Documents;

final class ParentLetterDocument extends LayoutDocument
{
    /** @param list<string> $contentCompetencies  @param list<string> $processCompetencies  @param list<array{date: string, time: string, title: string}> $scheduledLessons  @param list<array{label: string, value: string}> $contacts */
    public function __construct(
        string $title,
        public readonly string $group,
        public readonly string $school,
        public readonly string $creator,
        public readonly string $introduction,
        public readonly array $contentCompetencies,
        public readonly array $processCompetencies,
        public readonly array $scheduledLessons,
        public readonly ?string $publicUrl = null,
        public readonly ?string $qrPng = null,
        public readonly array $contacts = [],
        public readonly ?string $place = null,
        public readonly ?string $letterDate = null,
        DocumentLayout $layout = DocumentLayout::PRIMARY_SCHOOL_LOWER_SECONDARY,
    ) {
        parent::__construct($title, layout: $layout);
    }

    public function templateKey(): string
    {
        return 'parent-letter.default';
    }
}
