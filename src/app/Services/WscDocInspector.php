<?php

namespace App\Services;

use Peregrinus\WscDoc\WscDocument;

class WscDocInspector
{
    public function pageCount(string $path): ?int
    {
        return WscDocument::open($path)->info()->pageCount();
    }

    public function previewBytes(string $path): string
    {
        return WscDocument::open($path)->previewBytes();
    }
}
