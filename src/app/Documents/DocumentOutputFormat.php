<?php

namespace App\Documents;

enum DocumentOutputFormat: string
{
    case DOCX = 'docx';
    case ODT = 'odt';

    public function writerName(): string
    {
        return match ($this) {
            self::DOCX => 'Word2007',
            self::ODT => 'ODText',
        };
    }
}
