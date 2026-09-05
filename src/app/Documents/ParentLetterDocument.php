<?php

namespace App\Documents;

final class ParentLetterDocument extends Document
{
    /** @param list<string> $contentCompetencies  @param list<string> $processCompetencies  @param list<array{date: string, time: string, title: string}> $scheduledLessons */
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
    ) {
        parent::__construct($title);
    }

    public function templateKey(): string
    {
        return 'parent-letter.default';
    }
}
