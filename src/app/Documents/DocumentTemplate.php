<?php

namespace App\Documents;

use PhpOffice\PhpWord\PhpWord;

interface DocumentTemplate
{
    public function key(): string;

    public function render(Document $document): PhpWord;
}
