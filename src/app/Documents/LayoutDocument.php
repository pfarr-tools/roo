<?php

namespace App\Documents;

abstract class LayoutDocument extends Document
{
    public function __construct(
        string $title,
        array $metadata = [],
        public readonly DocumentLayout $layout = DocumentLayout::PRIMARY_SCHOOL_LOWER_SECONDARY,
    ) {
        parent::__construct($title, $metadata);
    }

    public function layoutProfile(): DocumentLayoutProfile
    {
        return DocumentLayoutProfile::for($this->layout);
    }
}
