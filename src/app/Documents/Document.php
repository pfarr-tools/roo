<?php

namespace App\Documents;

abstract class Document
{
    public function __construct(
        public readonly string $title,
        public readonly array $metadata = [],
    ) {}

    abstract public function templateKey(): string;
}
