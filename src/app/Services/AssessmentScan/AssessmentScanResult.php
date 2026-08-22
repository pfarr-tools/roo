<?php

namespace App\Services\AssessmentScan;

final readonly class AssessmentScanResult
{
    /** @param list<array{number:int, start_page:int, markers:list<array<string, mixed>>}> $booklets */
    /** @param list<string> $warnings */
    public function __construct(public array $booklets, public array $warnings = []) {}

    /** @return array{booklets:list<array{number:int, start_page:int, markers:list<array<string, mixed>>}>, warnings:list<string>} */
    public function toArray(): array
    {
        return ['booklets' => $this->booklets, 'warnings' => $this->warnings];
    }
}
